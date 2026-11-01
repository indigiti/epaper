# Publisher sign-in after ePaper Platform binding

`/health/platform` and `/health/ready` can pass while publisher sign-in is unavailable: domain resolution uses `domains.resolve`, but `POST /internal/v1/auth/direct` requires **`auth.direct`**.

## Fix existing EP-DI service account (no token rotation required)

1. Sign in as an authorized Platform administrator and navigate to **Service Accounts**.
2. Select the ACTIVE product-scoped account currently used by ePaper. **Edit scopes** and add `auth.direct` to its existing scopes, leaving all previously required scopes intact.
3. Do not paste credentials in chat or source files. Do not re-create products, applications, users, or tenants to repair a missing service scope.
4. Retry publisher sign-in with an ACTIVE Platform global user who has an ACTIVE membership in the **organisation attached to the resolved hostname's product tenant**. ePaper hostnames may resolve to different organisations and tenants on the same application.
5. If the user is not assigned, use **Publisher Onboarding → Assign owner** for an authorized owner, or configure the appropriate team membership. Do not add a legacy/archived organisation as a workaround.
6. If sign-in still fails: inspect sanitized Platform audit actions `identity.direct_login_failed`, `identity.direct_login_denied`, and `identity.direct_login_rate_limited` plus application logs. Wrong password/TOTP, inactive global user, stale OAuth Client ID, and missing current-organisation membership are distinct from a service scope failure.

Domain binding healthy ≠ publisher authentication healthy. A global Platform administrator account is **not** the same as a publisher user account.

The source default scope list, connection setup assistant and regression tests include `auth.direct` in this release. Existing production service accounts are **never automatically granted additional permissions** by a Git deployment.
