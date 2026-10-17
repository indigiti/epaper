# CRUD Matrix — ePaper SaaS v1.1.5

| Area | Create | Edit | Enable/Disable | Archive/Restore | Permanent remove |
|---|---|---|---|---|---|
| Publications | Yes | Yes | Yes | — | Yes, only when unused and not default |
| Highlights | Yes | Yes | Yes | — | Yes |
| Cities | Yes | Yes | Yes | — | Yes, only when unused |
| Languages | Yes | Yes | Yes | — | Yes, only when unused |
| Sections | Yes | Yes | Yes | — | Best-effort only when schema/dependencies permit |
| Editions | Yes | Metadata | — | Yes | No; processing/audit evidence retained |
| Subscribers | System/Reader | — | Local status | — | No; identity/membership stays in Digiti Platform |
| Brand assets | Upload | Replace | — | Reset/remove | Yes for managed asset file |
| Saved Articles | Reader | Title | — | — | Yes / Clear all |
| Bookmarks | Reader | Note | — | — | Yes / Clear all |
| Clips | Reader | Title + Private/Public | — | — | Yes / Clear all |
| History | Automatic | — | — | — | Remove / Clear all |
| Offline | Reader | — | — | — | Remove / Clear all |
| Notifications | System | Read state | — | — | User-specific delete / Clear all |
| Audit log | System | No | No | No | **Immutable** |
| Error Center | System | No | No | No | **Immutable** |
| Processing jobs/history | System | Retry/recover only | No | No | **Immutable** |
| Billing/invoices | Digiti Platform | Controlled in Platform | — | — | **Never ordinary delete** |

All mutations are POST + CSRF protected and tenant scoped. Destructive actions require explicit confirmation in the UI.
