# cw-platform: Publisher Onboarding Control Center

Branch: `cw-platform` (not `cw-epaper` or `main`).

- Dashboard: `/admin/onboarding` with publisher/domain lists, progress, blockers and 48-hour stale updates.
- Create a new EPAPER publisher tenant or attach an additional hostname to an existing tenant.
- Show TXT/CNAME records, retry DNS verification and assign an existing Platform user as publisher owner.
- Inspect FULL subscription access and confirm real HTTPS, Cloudways routing, publisher login and tenant isolation before activation.
- Go-live confirmation stores an immutable admin audit event. The legacy direct Activate button now routes through onboarding.
- Private configuration directory hardening from existing Platform source is included; existing private `config/config.php` remains supported.
- No schema migration and no worker restart are required by this change.

## Cloudways release checklist
1. Back up the existing Platform database, private configuration and runtime storage and verify restoration.
2. Stage this exact `cw-platform` commit on a protected clone. Do **not** deploy the `main` multi-app root to Platform hosting.
3. Confirm `public_html/` remains the document root, and no private configuration is web-accessible.
4. Ensure EPAPER product + `city_epaper` application are active and linked to the shared ePaper installation.
5. Test one unpublished onboarding record through owner, subscription, TXT/CNAME, TLS/edge, login and tenant isolation, then go-live confirmation.
6. Confirm suspended hosts are not unintentionally reactivated and no cross-tenant content is visible.
7. Deploy only the reviewed `cw-platform` commit, smoke-test `/health/ready`, `/admin/onboarding`, domain resolution and OAuth.
8. Restore previous commit/database on test failure. Do not touch `cw-epaper` deployment.

## Important limits
DNS recheck is an administrator-initiated operation, not continuous monitoring. 48-hour stalled flags are based on domain's `updated_at` timestamp. Cloudways domain registration and TLS installation are still external manual operations. The Platform domain resolver currently accepts routing-valid and SSL-provisioning domains before final activation: do not assume the onboarding approval is a network traffic gate. See subsequent hardening work for an explicit pre-live preview mode.
