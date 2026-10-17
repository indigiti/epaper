<?php
namespace EpaperSaas\Services;

use EpaperSaas\Core\Config;

/**
 * RabbitMQ transport for durable ePaper processing jobs.
 *
 * MariaDB remains authoritative for job state. RabbitMQ only transports job GUIDs.
 * The implementation uses php-amqplib when queue.driver=rabbitmq.
 */
final class RabbitMq
{
    private static ?self $instance = null;
    private $connection = null;
    private $channel = null;
    private bool $confirmMode = false;
    private ?string $publishReturned = null;
    private bool $publishNacked = false;

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    public function enabled(): bool
    {
        return strtolower((string)Config::get('queue.driver', 'database')) === 'rabbitmq';
    }

    public function dependencyAvailable(): bool
    {
        return class_exists('PhpAmqpLib\\Connection\\AMQPStreamConnection')
            && class_exists('PhpAmqpLib\\Message\\AMQPMessage')
            && class_exists('PhpAmqpLib\\Wire\\AMQPTable');
    }

    public function connect(): void
    {
        if (!$this->enabled()) {
            throw new \RuntimeException('RabbitMQ queue driver is not enabled');
        }
        if (!$this->dependencyAvailable()) {
            throw new \RuntimeException('php-amqplib is not installed. Run composer install --no-dev --optimize-autoloader in the application root.');
        }
        if ($this->connection && $this->channel) {
            try {
                if ($this->connection->isConnected() && $this->channel->is_open()) return;
            } catch (\Throwable) {}
            $this->close();
        }

        $host = (string)Config::get('queue.rabbitmq.host', '127.0.0.1');
        $port = (int)Config::get('queue.rabbitmq.port', 5672);
        $user = (string)Config::get('queue.rabbitmq.username', 'guest');
        $pass = (string)Config::get('queue.rabbitmq.password', '');
        $vhost = (string)Config::get('queue.rabbitmq.vhost', '/');
        $connectTimeout = (float)Config::get('queue.rabbitmq.connection_timeout', 3.0);
        $rwTimeout = (float)Config::get('queue.rabbitmq.read_write_timeout', 70.0);
        $heartbeat = (int)Config::get('queue.rabbitmq.heartbeat', 30);
        $keepalive = (bool)Config::get('queue.rabbitmq.keepalive', true);

        $class = 'PhpAmqpLib\\Connection\\AMQPStreamConnection';
        $this->connection = new $class(
            $host,
            $port,
            $user,
            $pass,
            $vhost,
            false,
            'AMQPLAIN',
            null,
            'en_US',
            $connectTimeout,
            $rwTimeout,
            null,
            $keepalive,
            $heartbeat
        );
        $this->channel = $this->connection->channel();
        $this->declareTopology();
    }

    public function declareTopology(): void
    {
        if (!$this->channel) return;
        $ch = $this->channel;
        $main = $this->mainExchange();
        $dead = $this->deadExchange();

        $ch->exchange_declare($main, 'topic', false, true, false);
        $ch->exchange_declare($dead, 'topic', false, true, false);

        foreach ($this->workQueues() as $queue => $routingKey) {
            $args = new \PhpAmqpLib\Wire\AMQPTable([
                'x-dead-letter-exchange' => $dead,
                'x-max-priority' => 10,
            ]);
            $ch->queue_declare($queue, false, true, false, false, false, $args);
            $ch->queue_bind($queue, $main, $routingKey);
        }

        $deadQueue = (string)Config::get('queue.rabbitmq.dead_queue', 'epaper.dead');
        $ch->queue_declare($deadQueue, false, true, false, false);
        $ch->queue_bind($deadQueue, $dead, '#');

        foreach ($this->retryDelays() as $name => $milliseconds) {
            $exchange = $this->retryExchange($name);
            $queue = 'epaper.retry.' . $name;
            $ch->exchange_declare($exchange, 'topic', false, true, false);
            $args = new \PhpAmqpLib\Wire\AMQPTable([
                'x-message-ttl' => $milliseconds,
                'x-dead-letter-exchange' => $main,
            ]);
            $ch->queue_declare($queue, false, true, false, false, false, $args);
            $ch->queue_bind($queue, $exchange, '#');
        }
    }

    /** Publish using publisher confirms. Confirm mode is enabled once per AMQP channel. */
    public function publish(string $exchange, string $routingKey, array $payload, string $messageId): void
    {
        $this->connect();
        $this->enablePublisherConfirms();
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $message = new \PhpAmqpLib\Message\AMQPMessage($body, [
            'content_type' => 'application/json',
            'content_encoding' => 'utf-8',
            'delivery_mode' => 2,
            'message_id' => $messageId,
            'timestamp' => time(),
            'app_id' => 'epaper-saas',
            'priority' => max(0, min(10, (int)($payload['priority'] ?? 0))),
        ]);
        $this->publishReturned = null;
        $this->publishNacked = false;
        try {
            $this->channel->basic_publish($message, $exchange, $routingKey, true, false);
            $this->channel->wait_for_pending_acks_returns((float)Config::get('queue.rabbitmq.confirm_timeout', 5.0));
            if ($this->publishReturned !== null) {
                throw new \RuntimeException('RabbitMQ returned an unroutable message: ' . $this->publishReturned);
            }
            if ($this->publishNacked) {
                throw new \RuntimeException('RabbitMQ negatively acknowledged published message ' . $messageId);
            }
        } catch (\Throwable $e) {
            // A publisher-confirm protocol/connection failure can leave channel sequence state unusable.
            // Force a fresh channel for the next outbox attempt instead of cascading failures.
            $this->close();
            throw $e;
        }
    }

    private function enablePublisherConfirms(): void
    {
        if ($this->confirmMode) return;
        if (!$this->channel) throw new \RuntimeException('RabbitMQ channel is unavailable');
        $this->publishReturned = null;
        $this->publishNacked = false;
        $this->channel->set_return_listener(function ($replyCode, $replyText, $returnedExchange, $returnedRoutingKey): void {
            $this->publishReturned = sprintf('%s %s (%s -> %s)', (string)$replyCode, (string)$replyText, (string)$returnedExchange, (string)$returnedRoutingKey);
        });
        $this->channel->set_nack_handler(function ($message = null): void {
            $this->publishNacked = true;
        });
        // php-amqplib resets its local publisher delivery-tag counter in confirm_select().
        // Calling confirm_select() repeatedly on the same RabbitMQ channel desynchronizes local
        // and broker confirm tags and can trigger "Server ack'ed unknown delivery_tag".
        $this->channel->confirm_select();
        $this->confirmMode = true;
    }

    public function publishJob(string $routingKey, array $payload, string $messageId): void
    {
        $this->publish($this->mainExchange(), $routingKey, $payload, $messageId);
    }

    public function publishRetry(string $delayName, string $routingKey, array $payload, string $messageId): void
    {
        if (!array_key_exists($delayName, $this->retryDelays())) $delayName = '30s';
        $this->publish($this->retryExchange($delayName), $routingKey, $payload, $messageId);
    }

    public function publishDead(string $routingKey, array $payload, string $messageId): void
    {
        $this->publish($this->deadExchange(), $routingKey, $payload, $messageId);
    }

    public function queueForLane(string $lane): string
    {
        $map = [
            'render' => (string)Config::get('queue.rabbitmq.queues.render', 'epaper.render'),
            'assets' => (string)Config::get('queue.rabbitmq.queues.assets', 'epaper.assets'),
            'ocr' => (string)Config::get('queue.rabbitmq.queues.ocr', 'epaper.ocr'),
            'finalize' => (string)Config::get('queue.rabbitmq.queues.finalize', 'epaper.finalize'),
        ];
        if (!isset($map[$lane])) throw new \InvalidArgumentException('Unknown RabbitMQ worker lane: ' . $lane);
        return $map[$lane];
    }

    public function channel()
    {
        $this->connect();
        return $this->channel;
    }

    /** Exposed to CLI workers so php-amqplib can maintain signal-based heartbeats during long OCR/assets callbacks. */
    public function connection()
    {
        $this->connect();
        return $this->connection;
    }

    public function ping(): array
    {
        if (!$this->enabled()) return ['ok'=>true,'detail'=>'Database queue driver'];
        if (!$this->dependencyAvailable()) return ['ok'=>false,'detail'=>'php-amqplib missing'];
        try {
            $this->connect();
            return ['ok'=>true,'detail'=>'Connected'];
        } catch (\Throwable $e) {
            return ['ok'=>false,'detail'=>substr($e->getMessage(), 0, 300)];
        }
    }

    public function queueStats(): array
    {
        if (!$this->enabled() || !$this->dependencyAvailable()) return [];
        try {
            $this->connect();
            $out = [];
            foreach ($this->workQueues() as $queue => $_) {
                $r = $this->channel->queue_declare($queue, true, true, false, false);
                $out[$queue] = ['ready'=>(int)($r[1]??0),'consumers'=>(int)($r[2]??0)];
            }
            foreach ($this->retryDelays() as $name => $_milliseconds) {
                $retry = 'epaper.retry.' . $name;
                $r = $this->channel->queue_declare($retry, true, true, false, false);
                $out[$retry] = ['ready'=>(int)($r[1]??0),'consumers'=>(int)($r[2]??0)];
            }
            $dead = (string)Config::get('queue.rabbitmq.dead_queue', 'epaper.dead');
            $r = $this->channel->queue_declare($dead, true, true, false, false);
            $out[$dead] = ['ready'=>(int)($r[1]??0),'consumers'=>(int)($r[2]??0)];
            return $out;
        } catch (\Throwable) {
            return [];
        }
    }

    public function close(): void
    {
        try { if ($this->channel) $this->channel->close(); } catch (\Throwable) {}
        try { if ($this->connection) $this->connection->close(); } catch (\Throwable) {}
        $this->channel = null;
        $this->connection = null;
        $this->confirmMode = false;
        $this->publishReturned = null;
        $this->publishNacked = false;
    }

    public function mainExchange(): string
    {
        return (string)Config::get('queue.rabbitmq.exchange', 'epaper.jobs');
    }

    public function deadExchange(): string
    {
        return (string)Config::get('queue.rabbitmq.dead_exchange', 'epaper.dead');
    }

    public function retryExchange(string $name): string
    {
        return (string)Config::get('queue.rabbitmq.retry_exchange_prefix', 'epaper.retry.') . $name;
    }

    public function retryDelays(): array
    {
        return ['30s'=>30000, '5m'=>300000, '30m'=>1800000];
    }

    private function workQueues(): array
    {
        return [
            (string)Config::get('queue.rabbitmq.queues.render', 'epaper.render') => 'edition.render',
            (string)Config::get('queue.rabbitmq.queues.assets', 'epaper.assets') => 'page.assets',
            (string)Config::get('queue.rabbitmq.queues.ocr', 'epaper.ocr') => 'page.ocr',
            (string)Config::get('queue.rabbitmq.queues.finalize', 'epaper.finalize') => 'edition.finalize',
        ];
    }
}
