# EP-DI v2.0.1.1 — Service Worker & PWA Install Hotfix

## Problem 1: Response.clone() error
`service-worker.js` previously started an asynchronous `caches.open()` operation and called `response.clone()` only later. The original Response could already be consumed by the browser by that point, causing `TypeError: Response body is already used`.

The fix clones the successful network response immediately, then performs the CacheStorage write with that clone.

## Problem 2: beforeinstallprompt console warning
The application intentionally intercepted `beforeinstallprompt` globally even on pages that did not render the custom `Install app` button. Chrome therefore reported that `preventDefault()` was called without a subsequent `prompt()`.

The hotfix intercepts the event only when `[data-pwa-install]` exists on the current page. On ordinary pages the native browser install flow is left untouched. On Reader OS, the custom Install button continues to work.

## Deployment
1. Apply this hotfix over exact v2.0.1.
2. No SQL.
3. No Upgrade Center repair.
4. No Supervisor restart.
5. Hard-refresh and, if necessary, DevTools → Application → Service Workers → Update once so `epdi-v2.0.1.1` activates.
6. Reload with DevTools Console open and confirm the clone error is gone.
