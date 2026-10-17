# Release Test Results — ePaper SaaS v1.1.6

State: **TESTED**

## Passed
- PHP syntax across packaged PHP/tests
- v1.1.6 build guard
- v1.1.5.1 Settings POST normalization regression
- v1.1.4.1 QrCode.php exact SHA-256 preservation
- v1.1.4.1 ClipService.php exact SHA-256 preservation
- Source Page QR behavior/source-link markers retained
- Social thumbnail route prefers existing THUMB with PREVIEW fallback
- Social route contains no Imagick/file write/mkdir/copy generation path
- Open Graph title/description/url/image/image-alt markup
- Twitter card/title/image/image-alt markup
- Public Clip social image / Private Clip non-exposure policy
- Service worker/runtime asset version alignment
- Desktop 1440x900 header browser geometry
- Mobile 390x844 header browser geometry
- Publisher logo Site-name alt text
- Duplicate accessible brand-name suppression

## Storage result
No `SOCIAL_CARD` asset or 1200x630 derivative is created. Page social sharing streams the already-generated THUMB WebP (PREVIEW fallback), so additional page-share image storage is **0 bytes**.

## Live checks still required
- WhatsApp unfurl on a newly shared published page URL
- Telegram/Facebook/X unfurl
- Existing-cached social URL refresh behavior
- Real tenant uploaded light/dark wordmark on desktop and mobile
