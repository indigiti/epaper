# EP-DI — production security cutover attestation
**Not yet completed.** This is a blank operator sign-off record, not a claim that any step has happened.

| Mandatory gate | Outcome | Evidence location (private reference only) | Owner/time |
|---|---|---|---|
| Access/incident containment | NOT VERIFIED | | |
| Cloudways application + MariaDB backup | NOT VERIFIED | | |
| Original PDF, media, clips recovery backup | NOT VERIFIED | | |
| Isolated restore rehearsal | NOT VERIFIED | | |
| Private configuration FPM/CLI/worker propagation | NOT VERIFIED | | |
| MariaDB credential rotated/revoked | NOT VERIFIED | | |
| Redis credential rotated/revoked | NOT VERIFIED | | |
| Digiti Platform token replaced and revoked | NOT VERIFIED | | |
| RabbitMQ dedicated account reviewed/rotated | NOT VERIFIED | | |
| App key Offline Vault/FCM/signed media migration | NOT VERIFIED | | |
| Git-history all-ref remediation | NOT VERIFIED | | |
| Authenticated admin role matrix | NOT VERIFIED | | |
| Staging PDF render/OCR/publish/recovery | NOT VERIFIED | | |
| Reader/clipping/offline and browser visual QA | NOT VERIFIED | | |
| Cloudways production acceptance | NOT VERIFIED | | |

**Release rule:** Any NOT VERIFIED or FAILED mandatory gate means **NOT DEPLOYMENT CLEARED**. This record must be completed by an operator with authoritative access to the hosting, service identities, DB, media backup and staging deployment. GitHub CI can verify only source-level tests.

Source-only preparation does not satisfy backup, rotation, history rewriting or live staging acceptance. For the requested ordering, backup/restore may be last **preparation** gate but must precede every destructive step.
