# Build Manifest — EP-DI v2.0.1.9

Release: **Performance & Delivery Engine**
Base: **2.0.1.8.1**
Schema migration: **none**

Primary changes:

- lazy/request-aware PHP sessions
- session-free anonymous public rendering
- signed-media pre-auth fast path
- Platform domain/tenant control-plane cache
- change-only local tenant reconciliation
- X-Accel/PHP media delivery telemetry
- aggregated Subscribers trusted-session query
- aggregated edition processing summary query
- rolling Redis request telemetry and Core Web Vitals
- Service Worker asset-version alignment

Validation is performed by `bin/release-check.php`, PHP lint, JavaScript syntax check, YAML parse, SHA256 manifest verification and overlay simulation.
