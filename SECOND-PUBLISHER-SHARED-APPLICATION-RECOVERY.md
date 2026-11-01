# Second publisher domain incorrectly registered as a second application

Symptoms: `epaper.dcnnews.co.in` shows `PLATFORM_BINDING_APPLICATION_BASE_URL_MISMATCH` while ePaper works at `epaper.digiti.in`.

The Platform record for `epaper.dcnnews.co.in` may have been incorrectly created under a separate `EP-DCN` application whose Base URL is the publisher hostname. One Cloudways installation should be represented by **one active application GUID** and **many registered publisher hostnames / separate product tenants**.

## Safe recovery

1. Deploy updated `cw-platform` branch and back up Platform database.
2. Open **Publishers & domains → DCN domain → Check application binding and repair** or **Domain Registry → Edit → Check shared-app recovery**.
3. SUPER_ADMIN selects the already deployed shared EP-DI application (base `https://epaper.digiti.in`), confirms both source and target hostnames and explicit tenant preservation.
4. The workflow only offers a target application whose **product GUID matches** the source publisher tenant and whose **canonical hostname is registered and routable**. If there is no eligible candidate, stop: this is a product split, and a separate reviewed tenant/product migration is needed. Do **not** mutate product or tenant GUIDs to force it.
5. After confirmation Platform updates **the existing domain GUID**, retaining organisation, product, tenant and content ownership. It converts an accidental `SAAS_DEFAULT` registration into a `CUSTOMER_CUSTOM` alias, re-registers the OAuth callback on the shared application, and resets domain verification/approval to `AWAITING_DNS`.
6. Use **Check DNS again**, ensure Cloudways maps the custom hostname to the shared origin and has the correct SSL certificate, then check `https://epaper.dcnnews.co.in/health/platform` and `/health/ready`. Allow for ePaper binding cache TTL, at most configured `platform.domain_cache_ttl` (default 600 seconds), unless the private application cache is safely invalidated.
7. Test the DCN publisher account, its own publications, reader data, and inability to read another publisher's private editions; only then approve go-live.
8. The redundant EP-DCN application is **not** removed automatically. After confirming it has no remaining dependencies, archive/release it separately if no longer needed. Audit and billing records are preserved.

Do not change the deployed app's `app.url` or `platform.application_guid` to DCN values. Changing ePaper server config per publisher would break the shared application design.
