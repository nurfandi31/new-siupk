#!/bin/sh
set -e
F=/var/www/html/resources/js/Pages/Admin/Integration.vue
echo "--- ls -la ---"
ls -la "$F"
echo "--- head -3 ---"
head -3 "$F"
echo "--- sha256 ---"
sha256sum "$F"
echo "--- env grep ---"
env | grep -E '^(VITE|VITE_|HOST|PWD|USER)' || true
echo "--- cwd ---"
pwd
echo "--- node version ---"
node -v
