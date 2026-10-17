# Update to ePaper SaaS v1.1.4.1

Extract the hotfix into the existing outer Cloudways `public_html` and overwrite matching files. Keep Webroot `public_html/public_html`. Do not run `install.php` or migrations.

Purge Varnish/application cache and hard-refresh once so Service Worker v1.1.4.1 is active.

Create or open a clipping and verify:
1. No expiry text is printed on the clipping artwork.
2. The artwork QR opens the source ePaper page.
3. The clipping page `Scan to open` QR still opens the clipping share URL.
4. The fixed seven-day access rule still expires the clipping server-side.
