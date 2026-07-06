#!/usr/bin/env sh
# Build the installable Perfex CRM module zip into dist/perfex_api.zip
set -e
cd "$(dirname "$0")"
mkdir -p dist
rm -f dist/perfex_api.zip
zip -r dist/perfex_api.zip perfex_api -x '*.DS_Store'
echo "Built dist/perfex_api.zip"
