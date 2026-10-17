# Build Manifest — EP-DI 2.0.1.8.1

Base: 2.0.1.8 Trusted App & Desktop Login.

Purpose: schema preflight/repair coverage and graceful Subscribers recovery when current reader-auth tables are missing.

No new schema is introduced. Existing packaged migrations for 2.0.1.5, 2.0.1.7 and 2.0.1.8 are now part of Schema Doctor detection/repair.
