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
echo 'Ephemeral PHP HTTP checks passed (epaper); Cloudways acceptance remains separate.'
