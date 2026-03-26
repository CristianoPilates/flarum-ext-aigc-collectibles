#!/usr/bin/env bash
set -euo pipefail

site_dir="${FLARUM_SITE_DIR:?FLARUM_SITE_DIR is required}"

if [ -f "$site_dir/flarum" ]; then
  echo "[forum-install] site already exists: $site_dir"
  exit 0
fi

echo "[forum-install] creating site in $site_dir"
composer create-project "flarum/flarum:${FLARUM_VER:-^2.0.0}" --stability=beta "$site_dir"
cd "$site_dir"
composer require flarum/extension-manager:"*"

echo "[forum-install] site created"
