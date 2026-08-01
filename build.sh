#!/usr/bin/env sh
# Build the installable Perfex CRM module zips into dist/
set -e
cd "$(dirname "$0")"
mkdir -p dist
for module in perfex_api wasms perfexpilot; do
  rm -f "dist/$module.zip"
  zip -r "dist/$module.zip" "$module" -x '*.DS_Store'
  echo "Built dist/$module.zip"
done
