#!/bin/sh
set -e
echo "--- kill vite ---
"
pkill -f 'vite' || true
sleep 1
echo "--- ls .vite deps ---"
ls -la /var/www/html/node_modules/.vite/deps/ 2>/dev/null | head -5 || echo "no deps dir"
echo "--- clear .vite + laravel-vite manifest cache ---"
rm -rf /var/www/html/node_modules/.vite
rm -f  /var/www/html/public/build/manifest.json
rm -rf /var/www/html/public/build/hot
rm -rf /var/www/html/public/build/assets
echo "--- restart dev ---"
cd /var/www/html
nohup npm run dev > /tmp/vite.log 2>&1 &
echo "started pid=$!"
