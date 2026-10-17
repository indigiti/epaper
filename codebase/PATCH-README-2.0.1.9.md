# EP-DI v2.0.1.9 Patch

Apply only over **EP-DI v2.0.1.8.1**.

No database migration is required. Overlay the patch preserving paths, clear/reload PHP opcode cache, and restart long-running Supervisor workers. Then verify `/admin/system/health` and `/admin/performance`.
