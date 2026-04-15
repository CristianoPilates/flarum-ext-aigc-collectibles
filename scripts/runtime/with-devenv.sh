#!/usr/bin/env bash
set -euo pipefail

script_dir="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
project_root="$(cd -- "$script_dir/../.." && pwd)"

if [ "$#" -eq 0 ]; then
  echo "usage: $0 <command> [args...]" >&2
  exit 1
fi

if [ -n "${IN_NIX_SHELL:-}" ]; then
  exec "$@"
fi

if ! command -v direnv >/dev/null 2>&1; then
  echo "direnv is required to enter the project devenv shell" >&2
  exit 1
fi

exec env DIRENV_LOG_FORMAT= direnv exec "$project_root" "$@"
