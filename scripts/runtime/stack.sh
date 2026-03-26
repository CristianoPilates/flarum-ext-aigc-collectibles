#!/usr/bin/env bash
set -euo pipefail

script_dir="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
project_root="$(cd -- "$script_dir/../.." && pwd)"
state_dir="$project_root/.devenv/state"
dotfile_marker="DEVENV_DOTFILE=$project_root/.devenv"
state_marker="$state_dir/"
self_pid="$$"
self_pgid="$(ps -o pgid= -p "$self_pid" | tr -d ' ')"

collect_entries() {
  ps -eo pid=,ppid=,pgid=,args= | awk \
    -v dotfile_marker="$dotfile_marker" \
    -v state_marker="$state_marker" \
    -v self_pid="$self_pid" \
    -v self_pgid="$self_pgid" '
    {
      pid = $1
      ppid = $2
      pgid = $3
      $1 = ""
      $2 = ""
      $3 = ""
      sub(/^[[:space:]]+/, "", $0)
      args = $0

      if (pid == self_pid || pgid == self_pgid) {
        next
      }

      ppid_by_pid[pid] = ppid
      pgid_by_pid[pid] = pgid
      args_by_pid[pid] = args

      if (index(args, dotfile_marker) || index(args, state_marker)) {
        candidate[pid] = 1
        if (!(pgid in leader_by_pgid)) {
          leader_by_pgid[pgid] = pid
        }
      }
    }

    END {
      for (pid in candidate) {
        pgid = pgid_by_pid[pid]
        pgids[pgid] = 1

        delete seen
        cur = pid
        while ((cur in ppid_by_pid) && !(cur in seen)) {
          seen[cur] = 1
          parent = ppid_by_pid[cur]
          if (!parent || parent == 0) {
            break
          }
          if (args_by_pid[parent] ~ /(^|[[:space:]])devenv up([[:space:]]|$)/) {
            roots[parent] = 1
          }
          cur = parent
        }
      }

      for (pid in roots) {
        print "PID\t" pid "\t" args_by_pid[pid]
      }

      for (pgid in pgids) {
        leader = pgid
        if (!(leader in args_by_pid)) {
          leader = leader_by_pgid[pgid]
        }
        print "PGID\t" pgid "\t" args_by_pid[leader]
      }
    }
  ' | sort -u
}

format_entries() {
  local entries="$1"

  while IFS=$'\t' read -r kind id args; do
    [ -n "${kind:-}" ] || continue
    if [ "$kind" = "PID" ]; then
      printf '[runtime] root pid %s: %s\n' "$id" "$args"
    else
      printf '[runtime] group %s: %s\n' "$id" "$args"
    fi
  done <<<"$entries"
}

remove_pid_files() {
  rm -f \
    "$state_dir/pw-test-external.pid" \
    "$state_dir/pw-test-site.pid"
}

command="${1:-check}"
entries="$(collect_entries)"

case "$command" in
  check)
    if [ -z "$entries" ]; then
      exit 0
    fi

    echo "[runtime] current project runtime is still active" >&2
    format_entries "$entries" >&2
    echo "[runtime] run: make down" >&2
    exit 1
    ;;

  list)
    if [ -z "$entries" ]; then
      echo "[runtime] no current project runtime found"
      exit 0
    fi

    format_entries "$entries"
    ;;

  down)
    if [ -n "$entries" ]; then
      root_pids=()
      group_ids=()

      while IFS=$'\t' read -r kind id _args; do
        [ -n "${kind:-}" ] || continue
        if [ "$kind" = "PID" ]; then
          root_pids+=("$id")
        else
          group_ids+=("$id")
        fi
      done <<<"$entries"

      for pid in "${root_pids[@]}"; do
        if kill -0 "$pid" >/dev/null 2>&1; then
          echo "[runtime] stopping root pid $pid"
          kill "$pid" >/dev/null 2>&1 || true
        fi
      done

      for pgid in "${group_ids[@]}"; do
        if kill -0 -- "-$pgid" >/dev/null 2>&1; then
          echo "[runtime] stopping group $pgid"
          kill -- "-$pgid" >/dev/null 2>&1 || true
        fi
      done

      sleep 1

      for pid in "${root_pids[@]}"; do
        if kill -0 "$pid" >/dev/null 2>&1; then
          echo "[runtime] force stopping root pid $pid"
          kill -KILL "$pid" >/dev/null 2>&1 || true
        fi
      done

      for pgid in "${group_ids[@]}"; do
        if kill -0 -- "-$pgid" >/dev/null 2>&1; then
          echo "[runtime] force stopping group $pgid"
          kill -KILL -- "-$pgid" >/dev/null 2>&1 || true
        fi
      done
    else
      echo "[runtime] no current project runtime found"
    fi

    remove_pid_files
    ;;

  *)
    echo "usage: $0 {check|list|down}" >&2
    exit 1
    ;;
esac
