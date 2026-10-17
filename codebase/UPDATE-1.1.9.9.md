# ePaper SaaS v1.1.9.9 — Compact Luxury Login Spacing Hotfix

Requires **v1.1.9.8**.

This hotfix removes the two excessive blank vertical zones visible on the branded login card. The authentication card now explicitly shrink-wraps its content, keeps the heading close to the floating sign-in icon, and positions the rotating editorial quote directly after the security note.

No database migration. No RabbitMQ or Supervisor restart.

After deployment, purge Cloudways/Varnish cache and hard-refresh `/login` once. The service-worker shell version and auth stylesheet URL are both changed to v1.1.9.9 to force the corrected layout to replace stale cached auth CSS.
