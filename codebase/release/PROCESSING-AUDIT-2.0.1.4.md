# Processing-path audit — v2.0.1.4

Baseline: delivered v2.0.1.3 source. This is a processing-path audit and targeted fix set, not a full application security certification or a live-server diagnosis.

| Finding in baseline | Change | Evidence |
|---|---|---|
| Recover republishes already queued work | Durable direct request; no broker republish | Repeated-request/outbox-count regression |
| Recently RUNNING unowned delivery can be acknowledged without execution | Shared executor reclaims under exclusive job lock | Abandoned RUNNING and late duplicate regressions |
| RabbitMQ is the only practical route for configured installations | Direct database runner plus web-triggered CLI launch | Real PDF rendered/OCRed/published with RabbitMQ configured but absent |
| Child completion can request finalization before root completion | Require current root COMPLETED; serialize finalizer creation | Early-finalizer and duplicate-finalizer tests |
| Crash between completion and finalizer enqueue leaves edition waiting | Reconcile finalizer from direct runner and scheduler | Missing-finalizer regression |
| Root retry can rebuild already dispatched work | Preserve completed render handoff; retain protection for uncheckpointed partial work | Interrupted-render regression preserves page IDs |
| Retry eligibility mixed PHP local timezone and UTC strings | Database-relative retry time and due predicates | Test runs PHP in Asia/Kolkata against database time |
| Stalled detection ignores QUEUED jobs | Include queued/retry-wait age; compute ages in database | Queued-stall regression |
| Render 100% appears to imply edition completion | Batch current-pipeline stage summaries and overall percentage | PHP table/summary tests |
| Per-job execution reasons hard to follow | Bounded claim/stage/completion/error trace | Trace and actual workload tests |
| Recover/Cancel text expands action rows | Matching SVG icon buttons, title and aria-label; polling rows updated | Rendered PHP action assertions and JS syntax checks |

## Performance boundaries

Visible edition summaries use one additional batch query limited to current pipelines and visible edition IDs. Finalization uses an aggregate over the current pipeline, instead of returning all historical child jobs. Direct request scans are limited to active requests, process one job per selected request per tick, and one runner per installation limits extra concurrency. Trace history is bounded to 16 events per job. Recovery does not purge shared queues or create fresh copies of existing business jobs.

No production throughput/CPU benchmark was performed. The real fixture is a small, one-page English PDF; large newspaper PDFs and Marathi/Hindi OCR need host acceptance with their installed language packs. The screenshots alone do not prove the live root cause of the old backlog.
