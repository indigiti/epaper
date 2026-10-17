# Digiti Platform v1.0.1 Build Manifest

Release type: incremental admin-management update over v1.0.0.

Changed protected/runtime files:
- `app/App.php` — route dispatch integration, management action columns, commercial/subscription/billing controls, domain resolver hardening.
- `app/Core/View.php` — secondary management navigation and destructive-action confirmation.
- `public_html/assets/app.css` — management/action styling.
- `public_html/install.php` — fresh-install ePaper SaaS display naming.

Added:
- `app/Admin/Crud.php` — server-side CRUD/lifecycle handlers.
- `public_html/update.php` — authenticated one-time v1.0.1 data update.

Unchanged:
- Database table structure.
- Product key `EPAPER`.
- EPAPER application key `city_epaper`.
- OAuth client IDs and service-account tokens.
- Billing/payment historical tables and semantics.
- Internal product API contracts.
