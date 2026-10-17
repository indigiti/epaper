# Rollback — ePaper SaaS v1.1.1

No database migration is included. To roll back, restore these files from the verified v1.1.0 package:

- `app/App.php`
- `app/Core/View.php`
- `public_html/assets/app.css`
- `public_html/assets/app.js`
- `public_html/assets/reader.css`
- `public_html/assets/reader.js`
- `public_html/service-worker.js`
- `VERSION`

Then purge Cloudways/Varnish cache and hard-refresh. RabbitMQ/Supervisor do not require rollback because processing business logic is unchanged.
