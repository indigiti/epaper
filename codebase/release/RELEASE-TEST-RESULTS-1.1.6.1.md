# Release Test Results — ePaper SaaS v1.1.6.1

State: **TESTED**

## Header behavior
- Logo configured → logo rendered, no repeated visible Site name: PASS
- Logo alt text = configured Site name: PASS
- No logo configured → Site name text fallback: PASS
- Accessible brand-home label retained: PASS
- Desktop 1440×900 responsive masthead: PASS
- Mobile 390×844 responsive masthead: PASS

## Regression protection
- v1.1.4.1 QrCode.php protected hash: PASS
- v1.1.4.1 ClipService.php protected hash: PASS
- Source Page QR behavior guards: PASS
- v1.1.5 Brand/CRUD guards: PASS
- v1.1.5.1 Settings normalization tests: PASS
- v1.1.6 thumbnail-based Smart Social Sharing guards: PASS
- Service worker/version alignment: PASS

## Scope
No DB migration, route/API change, RabbitMQ/Supervisor change, clipping change, QR change, media change or social-sharing change.
