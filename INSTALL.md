# ePaper SaaS — Final Login Height CSS Hotfix

## Purpose
Removes the excessive blank vertical space from the branded login card. This is a CSS-only hotfix; it does not modify authentication, QR/clipping, Redis, RabbitMQ, Supervisor, database schema, or Digiti Platform integration.

## Install

1. Take a Cloudways backup.
2. Upload this ZIP to the application's **outer `public_html`** — the directory that contains both `app/` and the inner `public_html/` directory.
3. Extract the ZIP there.

After extraction this file must exist:

`public_html/assets/auth-login-height-final.css`

In server filesystem terms, with the normal ePaper layout, that is:

`<application-root>/public_html/assets/auth-login-height-final.css`

4. Open `app/Core/View.php`.
5. Find the existing authentication stylesheet link, for example:

```html
<link rel="stylesheet" href="/assets/auth-v1.1.9.10.css?v=1.1.9.10">
```

6. Immediately AFTER it add:

```html
<link rel="stylesheet" href="/assets/auth-login-height-final.css?v=1.0.1">
```

The final order must be:

```html
<link rel="stylesheet" href="/assets/auth-v1.1.9.10.css?v=1.1.9.10">
<link rel="stylesheet" href="/assets/auth-login-height-final.css?v=1.0.1">
```

7. Purge Cloudways/Varnish/application cache.
8. Hard-refresh `/login` (`Ctrl+Shift+R`).
9. Confirm this URL opens and displays CSS text:

`https://epaper.digiti.in/assets/auth-login-height-final.css?v=1.0.1`

## Rollback
Remove the second `<link>` line from `app/Core/View.php`. The CSS file itself can remain unused or be deleted.
