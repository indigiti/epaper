# Acceptance testing

- [Staging authentication, Platform/Redis failure and browser cache isolation](STAGING-ACCEPTANCE-HARNESS-2026-10-17.md)
- [Existing regression test entry point](../../bin/release-check.php)
- [Processing integration test guidance](../../tests/README.md)

CI validates source and mock simulations. Staging tests requiring Cloudways, private test accounts or Playwright state files must run in a private non-production operator environment.
