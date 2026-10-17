# Build Manifest — ePaper SaaS v1.1.9

Base: v1.1.8

Changed/runtime files:
- app/Core/View.php
- public_html/assets/app.css
- public_html/service-worker.js
- VERSION
- manifest.json
- bin/release-check.php

Release/test documentation added:
- UPDATE-1.1.9.md
- INSTALL-V1.1.9.md
- tests/build-guard-v1.1.9.php
- tests/auth-v1.1.9-browser.py
- release/*1.1.9*

Protected and intentionally untouched:
- app/App.php
- app/Services/QrCode.php
- app/Services/ClipService.php
- public_html/assets/guest-clipping.js
- public_html/assets/reader-prefetch.js
- Reader/clipping/account/admin business logic from v1.1.8

No schema migration. No RabbitMQ/Supervisor topology change. Authentication protocol and POST handling are unchanged.
