# ePaper SaaS v1.0.1.4 Build Manifest

Release type: targeted cumulative runtime hotfix over v1.0.1.3.

Changed runtime files:
- `app/Services/RabbitMq.php` — one-time publisher-confirm initialization + channel reset on transport failure.
- `app/Services/QueueService.php` — recovery of known failed publisher-confirm outbox rows.
- `app/App.php` — Processing Center visibility for recoverable confirm failures.
- `VERSION`

Included unchanged compatibility file:
- `app/Services/OutboxPublisher.php`

Schema changes: none.
Route changes: none.
Reader/media/PWA changes: none.
Rollback: restore the four runtime files from the v1.0.1.3 backup and restart Supervisor. Do not roll back after redispatched jobs have started unless processing is first quiesced.
