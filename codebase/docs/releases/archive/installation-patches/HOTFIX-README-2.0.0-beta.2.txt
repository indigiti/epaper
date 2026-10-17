EP-DI v2.0.0-beta.2 — Reader OS + Encrypted Offline Vault
Base required: v2.0.0-beta.1.2
Database migration required: open /admin/system/upgrade after extracting.
No Supervisor worker restart required.
Hard-refresh once after upgrade so the beta.2 service worker activates.
Historical public backup assets carried by older overlay deployments are neutralized and denied by the updated .htaccess.
