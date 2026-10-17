# EP-DI app-key rotation dependency inventory
**Prepared:** 2026-10-17 · Scope: source-level consumer inventory (not live data verification)

Do not rotate \`app.key\` until backup/restore and migration have been verified. This file contains **no secrets**.

| Consumer | Relevant source | What breaks when \`app.key\` changes |
|---|---|---|
| Offline Vault sealed content-encryption keys | \`app/Services/OfflineVault.php\` (\`sealCek\`, \`openSealedCek\`) | AES-256-GCM CEK seal uses SHA-256('epdi-offline-seal-v1|' + app.key); previously sealed licenses cannot be opened by the server with a different key. |
| FCM push tokens | \`app/Services/FcmPush.php\` (\`encryptToken\`, \`decryptToken\`) | Encrypted device push tokens use key hash SHA-256(app.key), so existing tokens fail decryption after replacement. |
| Signed media links | \`app/Services/Media.php\` (\`token\`, \`decode\`) | HMAC-SHA256 signatures invalidate immediately when old signing key is removed; existing media URLs may need to be regenerated. |
| Desktop login session fingerprints | \`app/Services/DesktopAuth.php\` (\`browserSessionHash\`, IP hash) | Old pending challenge and browser-session fingerprints change. |
| Device registration/IP hashes | \`app/Services/DeviceAuth.php\` | Fallback device pepper and IP fingerprints can change unless a dedicated persistent \`auth.mobile.device_pepper\` exists. |
| Additional cryptographic/auth consumers | All PHP classes referencing \`Config::get('app.key')\` or downstream key functions | Perform a fresh exhaustive local grep and data-model review immediately before cutover; this inventory is not exhaustive. |

## Rotation strategy — PREPARATION only

1. On an isolated restored **staging** copy, inventory rows containing Offline Vault sealed keys, encrypted FCM tokens, pending desktop/mobile challenges and existing signed-media cache usage.
2. Confirm data-retention requirements, active device counts and whether members can re-license/re-enroll; document customer communication and support.
3. Choose and test a compatible rotation implementation. For existing encrypted data, safe choices include versioned dual-decrypt and data re-encryption (with offline rollback), or a documented and approved device re-enrollment/re-license reset. **Do not simply change \`app.key\` in a live config.**
4. Generate replacement keys via an approved server-side cryptographic generator; keep both old and new material only in a controlled private secret system during migration. Do not write replacement key material to GitHub or chat.
5. Run comparison tests on a staging restoration: old sealed CEKs must open until migrated, newly encrypted content must open with the replacement key, device approvals work, and media signature rollover is intentional.
6. After backup/restore acceptance, execute the controlled production rotation and revoke old key usage as soon as verified complete; perform historical Git cleanup only after affected secrets are invalidated.

## Blocking evidence

Before progressing, the private change ticket must identify the specific CEK migration or device reset strategy, staging successful proof, rollback point, operator approver, and cutoff for old key removal.
