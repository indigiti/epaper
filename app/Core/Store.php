<?php
namespace EpaperSaas\Core;
final class Store
{
    private static int $queryCount=0;
    private static float $queryMs=0.0;
    private static float $maxQueryMs=0.0;
    private static int $slowQueryCount=0;
    /** @var array<int,array{ms:float,signature:string,verb:string}> */
    private static array $slowSamples=[];

    private static function statement(string $sql,array $params=[]): \PDOStatement
    {
        $started=microtime(true);$elapsed=0.0;
        try{$s=Database::pdo()->prepare($sql);$s->execute($params);return$s;}
        finally{
            $elapsed=(microtime(true)-$started)*1000;self::$queryCount++;self::$queryMs+=$elapsed;self::$maxQueryMs=max(self::$maxQueryMs,$elapsed);
            $threshold=max(25.0,(float)Config::get('observability.slow_query_ms',200));
            if($elapsed>=$threshold){self::$slowQueryCount++;if(count(self::$slowSamples)<5){$normalized=preg_replace('/\s+/',' ',trim($sql))?:trim($sql);$verb=strtoupper(strtok($normalized,' ')?:'SQL');self::$slowSamples[]=['ms'=>round($elapsed,2),'signature'=>substr(hash('sha256',$normalized),0,16),'verb'=>$verb];}}
        }
    }
    public static function one(string $sql,array $params=[]): ?array{$r=self::statement($sql,$params)->fetch();return $r?:null;}
    public static function all(string $sql,array $params=[]): array{return self::statement($sql,$params)->fetchAll()?:[];}
    public static function value(string $sql,array $params=[]): mixed{return self::statement($sql,$params)->fetchColumn();}
    public static function exec(string $sql,array $params=[]): int{return self::statement($sql,$params)->rowCount();}
    public static function tx(callable $fn): mixed{$pdo=Database::pdo();$pdo->beginTransaction();try{$r=$fn($pdo);$pdo->commit();return $r;}catch(\Throwable$e){if($pdo->inTransaction())$pdo->rollBack();throw$e;}}
    public static function metrics(): array{return['queries'=>self::$queryCount,'query_ms'=>round(self::$queryMs,2),'max_query_ms'=>round(self::$maxQueryMs,2),'slow_queries'=>self::$slowQueryCount,'slow_samples'=>self::$slowSamples];}
}
