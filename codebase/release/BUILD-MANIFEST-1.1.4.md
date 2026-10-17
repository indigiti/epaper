# Build Manifest — ePaper SaaS v1.1.4

## Release
- Version: 1.1.4
- Type: cumulative Cloudways release + overwrite hotfix
- Database migration: none
- Platform migration: none
- RabbitMQ/Supervisor reconfiguration: none

## Primary changed runtime files from v1.1.3
- app/App.php
- app/Core/View.php
- app/Services/AnalyticsCatalog.php
- app/Services/ClipService.php
- app/Services/SettingsCatalog.php
- public_html/assets/app.css
- public_html/assets/app.js
- public_html/assets/reader.css
- public_html/assets/reader.js
- public_html/service-worker.js
- bin/release-check.php
- VERSION
- manifest.json

## Release/test additions
- UPDATE-1.1.4.md
- tests/build-guard-v1.1.4.php
- release/*-1.1.4 manifests and reports

## Protected capabilities retained
Reader, media, Deep Zoom, Clip Mode, Smart Article, account library, tenant isolation, Platform binding, Redis, RabbitMQ, Supervisor, transactional outbox, health, audit and error/request tracing.
