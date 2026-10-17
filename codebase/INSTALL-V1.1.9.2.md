# Install v1.1.9.2

Requires ePaper SaaS v1.1.9.1.

1. Take a Cloudways on-demand backup.
2. Extract this hotfix into the existing outer `public_html` directory and overwrite matching files.
3. Keep the webroot as `public_html/public_html`.
4. Do not run `install.php`; there is no database migration.
5. Purge Varnish/application cache and hard-refresh `/login`.
6. Verify the full sign-in form is visible on desktop and mobile.
