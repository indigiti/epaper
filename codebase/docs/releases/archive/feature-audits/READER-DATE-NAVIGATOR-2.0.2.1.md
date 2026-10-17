# EP-DI v2.0.2.1 — Reader Back-Edition Navigator

The Reader toolbar now supports a compact back-edition selector positioned between Download and Share.

Responsive trigger:
- wide toolbar: `09 Sep 2026` + calendar
- medium toolbar: `09 Sep` + calendar
- compact/mobile toolbar: calendar icon only

The menu is generated only from PUBLISHED editions that match the current edition's `publication_guid`, `city_guid`, and `language_guid`. Selecting a date opens page 1 of that exact edition.

Admin → Settings → Reader:
- Enable edition / back-date navigation: On / Off
- Edition navigation style: Dropdown / Strip / Off
- Back editions available in dropdown: default 365, bounded in runtime to 30–1000

No schema migration is required. The Android wrapper remains v2.0.2.0 because this is a server/WebView Reader UX update and does not change native Android code.
