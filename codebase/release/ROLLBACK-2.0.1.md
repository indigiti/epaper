# EP-DI v2.0.1 — Rollback

Rollback target: checksum-locked EP-DI v2.0.0 Production Stable.

1. Put the application into the normal controlled deployment window.
2. Restore the v2.0.0 application files or redeploy `EP-DI-v2.0.0.zip`.
3. No database rollback is required because v2.0.1 has no schema migration.
4. Hard-refresh clients so the v2.0.0 service-worker namespace is restored.
5. Verify Home, Archive, publication Newsstand, Reader, Account/Admin authorization, Processing and Production Readiness.

Existing `ep_settings` values for the new keys are harmless if the code is rolled back because v2.0.0 ignores unknown setting keys.
