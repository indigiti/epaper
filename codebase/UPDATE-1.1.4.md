# ePaper SaaS v1.1.4 — Premium Clipping Experience

## Upgrade from v1.1.3

1. Take an on-demand Cloudways backup.
2. Extract the v1.1.4 hotfix into the existing outer `public_html` and overwrite matching files.
3. Keep Webroot `public_html/public_html`.
4. Do not run `install.php`; there is no database migration.
5. RabbitMQ/Supervisor do not need reconfiguration.
6. Purge Cloudways/Varnish cache and hard-refresh the Reader.

## New clipping flow

Reader Clip Mode → select area → Save icon → Create clipping dialog → Title + Private/Public → Save clipping → real progress → clipping page.

New clips always expire after seven days. There is no extension field or Organisation visibility field.

## Clipping page

The dedicated page contains publisher branding, views/shares/downloads, social sharing, QR scan-to-open, progressive format preparation and download cards. Private links require the owner to be signed in on the target device.

## Share formats

- Original image (branded WebP)
- PDF
- Instagram Portrait 1080×1350
- Instagram Landscape 1080×566
- Facebook Image 1200×630
- X/Twitter Image 1200×675
- WhatsApp Image 1080×1080
- WebP
- AVIF when the live ImageMagick build supports AVIF

## Verification

Run `php bin/release-check.php`, create one Private clip and one Public clip, confirm the seven-day expiry, verify the QR on a second device, and download at least Original, PDF and one social image.
