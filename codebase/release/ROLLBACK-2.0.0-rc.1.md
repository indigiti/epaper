# EP-DI v2.0.0-rc.1 Rollback

Preferred rollback target: verified `v2.0.0-beta.2.1` application package.

1. Take a fresh database/application backup before rollback.
2. Restore beta.2.1 application files and restart Supervisor workers.
3. **Do not drop the rc.1 semantic tables as part of a normal application rollback.** They are additive and retaining them preserves article/author/topic/entity content for a later rc.1 redeploy.
4. Clear/purge versioned application/service-worker caches as appropriate.
5. Verify Reader, encrypted Offline Vault, Editions Operations, Processing and `/admin/performance`.

If a full database point-in-time rollback is required, use the Cloudways backup taken immediately before rc.1 migration. Destructive manual table drops are not part of the normal rollback procedure.
