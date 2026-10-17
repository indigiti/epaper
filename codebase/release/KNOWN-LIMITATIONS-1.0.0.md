# Known Limitations / Integration Boundaries — v1.0.0

- Digiti Platform remains authoritative for organisation identity, memberships, commercial billing, invoices/payments, domain/TLS lifecycle and effective SaaS entitlements.
- Publisher staff creation/membership administration is currently performed in Digiti Platform; ePaper SaaS consumes the resolved membership and enforces publisher roles.
- External machine-translation provider integration is not included in this baseline. Smart Article OCR and browser Text-to-Speech are included.
- The initial offline implementation records the edition and caches page media in the browser. Advanced background download/resume/quota management can be expanded later.
- The current Deep Zoom generator requires PHP Imagick. PDF page rendering prefers `pdftoppm`; Tesseract is required for OCR/hOCR.
- Storage is local through a clean service boundary in this baseline; S3-compatible storage migration remains an operational expansion.
- Automated Cloudflare custom-hostname/TLS provisioning belongs to Digiti Platform/edge infrastructure, not the ePaper business application.
- The build environment used to create the release did not contain a live Cloudways MariaDB/Redis instance, Platform service credentials, Tesseract corpus, or production customer domains. Those runtime tests are deliberately left to the provided post-install diagnostics rather than falsely marked as passed.
