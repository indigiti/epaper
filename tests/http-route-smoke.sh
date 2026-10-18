#!/usr/bin/env bash
set -euo pipefail
# Run only on ephemeral CI checkout; never use or contact production/staging URLs.
cp config/config.example.php config/config.php
php -S 127.0.0.1:18987 -t public_html > /tmp/epdi-http-smoke.log 2>&1 &
server=$!
trap 'kill "$server" 2>/dev/null || true; rm -f config/config.php' EXIT
for i in {1..30}; do
  if curl --silent --max-time 2 http://127.0.0.1:18987/health/live > /tmp/epdi-live.json; then break; fi
  sleep 0.1
done
php -r '$json=json_decode(file_get_contents("/tmp/epdi-live.json"),true); if(!is_array($json)||($json["ok"]??false)!==true||($json["status"]??"")!=="LIVE"){fwrite(STDERR,"live health did not return JSON LIVE\n");exit(1);}'

# Anonymous admin must not bypass authentication, even with a valid PHP session cookie.
status=$(curl --silent --output /tmp/epdi-admin-response --dump-header /tmp/epdi-admin-headers --write-out '%{http_code}' http://127.0.0.1:18987/admin)
[[ "$status" == "302" ]] || { echo "Admin did not redirect: $status" >&2; exit 1; }
grep -qi '^Location: /login' /tmp/epdi-admin-headers || { echo 'Wrong anonymous redirect' >&2; exit 1; }
status=$(curl --silent --output /tmp/epdi-login --write-out '%{http_code}' http://127.0.0.1:18987/login)
[[ "$status" == "200" ]] || { echo "Login form unavailable: $status" >&2; exit 1; }
grep -q 'name="_csrf"' /tmp/epdi-login || { echo 'Login page missing CSRF form field' >&2; exit 1; }
status=$(curl --silent --output /tmp/epdi-login-invalid --write-out '%{http_code}' --request POST --data 'email=nobody%40invalid.test&password=invalid' http://127.0.0.1:18987/login)
[[ "$status" == "419" ]] || { echo "Missing CSRF was not rejected: $status" >&2; exit 1; }
echo 'Ephemeral PHP HTTP checks passed (platform); Cloudways acceptance remains separate.'
