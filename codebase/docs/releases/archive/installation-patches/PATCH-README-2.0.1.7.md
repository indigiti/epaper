# EP-DI v2.0.1.7 Overlay Patch

Use this patch only on the verified **EP-DI v2.0.1.6 Mobile Production Hardening** source tree.

1. Back up application files and MariaDB.
2. Preserve live config and storage.
3. Overlay the patch at the EP-DI project root.
4. Run `database/upgrade-v2.0.1.7-flexible-reader-identity.sql` once.
5. Restart EP-DI PHP/Supervisor workers as applicable.
6. Hard refresh/PWA reload.
7. Run `php bin/release-check.php`.
8. Open Admin → Settings → Reader access and configure SMS / WhatsApp / SNA / Temporary Device Account.
9. Test Android fallback with all effective verification methods disabled, then test subscriber Disable/Enable.

Rollback: restore the v2.0.1.6 application backup. The new `ep_local_device_accounts` table is additive and may remain during application rollback, or be removed only after confirming no v2.0.1.7 rollback data is required.
