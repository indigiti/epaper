# Editorial Action Center

The Publishing Desk includes role-filtered, read-only summaries for
NEEDS_ATTENTION editions, MANUAL READY editions, failed processing events,
and optional pending AI candidate pages. Each item links to the existing
authenticated route. It never approves, publishes, retries or changes records.

The feature is independently layered: EditorialActionRepository interface,
SqlEditorialActionRepository tenant-bound adapter, EditorialActionCenter
service, and EditorialActionView renderer. List queries are bounded to four
rows (hard maximum five), with no Redis cache write, Python, RabbitMQ or
external API calls from this feature.

AI candidates are queried only with the editor role AND enabled feature
flag. Missing optional migration returns UNAVAILABLE rather than zero.
Historical failed job events are labelled as such; they are not guaranteed
to still be actionable. Existing route authorization and CSRF remain intact.

This is not a substitute for actual interactive staging acceptance.
