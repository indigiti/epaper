# Security Baseline — v1.0.0

- Platform Admin session is separate from organisation/product membership.
- Platform admin mutation routes require authenticated platform admin + CSRF.
- Product internal APIs require scoped service tokens stored as SHA-256 hashes.
- OAuth foundation uses one-time authorization code, two-minute expiry, exact callback binding and PKCE S256 verification.
- Customer domain resolution is based on normalized HTTP Host; product apps are prohibited from trusting arbitrary tenant IDs from request input.
- Custom hostnames are globally unique.
- User passwords and platform admin passwords use PHP `password_hash`.
- Session cookies are HttpOnly, SameSite=Lax and Secure on HTTPS.
- Audit events and errors use stable GUIDs and request GUIDs.
- No database/Redis/payment secrets are exposed by System Health responses.
- Platform service token is shown only at creation time; only its hash is stored.
- Provider webhook receipts are stored before processing and duplicate provider references are rejected.
- Database is authoritative; Redis failure does not change tenant ownership or subscriptions.
