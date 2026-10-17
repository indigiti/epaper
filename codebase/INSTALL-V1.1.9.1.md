# Install v1.1.9.1

1. Confirm ePaper SaaS v1.1.9 is already deployed.
2. Take a Cloudways on-demand backup.
3. Extract the v1.1.9.1 hotfix into the existing outer `public_html`, overwriting matching files.
4. Keep Webroot `public_html/public_html`.
5. Do not run `install.php`; there is no database migration.
6. No RabbitMQ/Supervisor restart is required.
7. Purge Cloudways/Varnish/browser cache and hard-refresh `/login`.
8. Verify the login card is compact/landscape on desktop and naturally stacked on mobile.
9. Complete one real sign-in to confirm authentication behavior is unchanged.
