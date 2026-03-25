{ pkgs, inputs, ... }:

let
  pkgsPlaywright = import inputs.nixpkgsPlaywright { system = pkgs.stdenv.system; };
  playwrightBrowsers = pkgsPlaywright.playwright-driver.browsers;
  projectRoot = "/home/donk/development/flarum-ext-aigc-collectibles";
  forumDir = "/home/donk/development/flarum-site";
  aigcApiDir = "/home/donk/development/akashgen-api-go";
  playwrightConfig = "${projectRoot}/playwright.config.cjs";
  playwrightBaseURL = "http://127.0.0.1:8080";
  playwrightMcpPort = "8931";
  anvilMnemonic = "test test test test test test test test test test test junk";
  defaultAnvilPrivateKey = "0xac0974bec39a17e36ba4a6b4d238ff944bacb478cbed5efcae784d7bf4f2ff80";
in

{
  dotenv.enable = true;
  env = {
    GO111MODULE = "on";
    CGO_ENABLED = "0";
    PLAYWRIGHT_BROWSERS_PATH = "${playwrightBrowsers}";
    PLAYWRIGHT_SKIP_VALIDATE_HOST_REQUIREMENTS = true;
    PLAYWRIGHT_SKIP_BROWSER_DOWNLOAD = "1";
    PLAYWRIGHT_HOST_PLATFORM_OVERRIDE = "ubuntu-24.04";
    PLAYWRIGHT_TEST_CONFIG = playwrightConfig;
    PLAYWRIGHT_BASE_URL = playwrightBaseURL;
    PLAYWRIGHT_MCP_PORT = playwrightMcpPort;
    PLAYWRIGHT_MCP_URL = "http://127.0.0.1:${playwrightMcpPort}/mcp";
    AIGC_EXTENSION_ROOT = projectRoot;
    FLARUM_SITE_DIR = forumDir;
    AIGC_API_DIR = aigcApiDir;
    ANVIL_RPC_URL = "http://127.0.0.1:8545";
  };

  packages =
    (with pkgs; [
      foundry # anvil / forge / cast
      kubo # ipfs
      jq
      curl
      findutils
      gnumake
    ])
    ++ [
      pkgsPlaywright.playwright-test
      pkgsPlaywright.playwright-mcp
    ];

  languages = {
    javascript = {
      enable = true;
      npm.install.enable = true;
    };

    php = {
      enable = true;
      package = pkgs.php.buildEnv {
        extensions = { all, enabled }: with all; enabled ++ [ xdebug ];
      };
      ini = ''
        memory_limit = 256M
        xdebug.mode = debug
        xdebug.start_with_request = yes
        xdebug.client_host = 127.0.0.1
        xdebug.client_port = 9003
      '';
    };
    go = {
      enable = true;
      package = pkgs.go_1_25;
    };
  };

  services.mysql = {
    enable = true;
    package = pkgs.mysql84;
    settings.mysqld = {
      default-storage-engine = "InnoDB";
      bind-address = "127.0.0.1";
      port = 3306;
    };
    initialDatabases = [
      { name = "flarum"; }
      { name = "flarum_test"; }
      { name = "flarum_aigc_collectibles_test"; }
    ];
    ensureUsers = [
      {
        name = "flarum";
        password = "flarum";
        ensurePermissions = {
          "flarum.*" = "ALL PRIVILEGES";
          "flarum_test.*" = "ALL PRIVILEGES";
          "flarum_aigc_collectibles_test.*" = "ALL PRIVILEGES";
        };
      }
    ];
  };

  enterShell = ''
    export IPFS_PATH="$DEVENV_STATE/ipfs"
    export ANVIL_STATE_DIR="$DEVENV_STATE/anvil"
    export CONTRACT_ENV_FILE="$DEVENV_STATE/contract.env"
    export PLAYWRIGHT_TEST_OUTPUT_DIR="$DEVENV_STATE/playwright/test-results"
    export PLAYWRIGHT_HTML_REPORT_DIR="$DEVENV_STATE/playwright/html-report"
    export PLAYWRIGHT_MCP_OUTPUT_DIR="$DEVENV_STATE/playwright-mcp-output"
    export PLAYWRIGHT_MCP_USER_DATA_DIR="$DEVENV_STATE/playwright-mcp-profile"

    mkdir -p \
      "$IPFS_PATH" \
      "$ANVIL_STATE_DIR" \
      "$PLAYWRIGHT_TEST_OUTPUT_DIR" \
      "$PLAYWRIGHT_HTML_REPORT_DIR" \
      "$PLAYWRIGHT_MCP_OUTPUT_DIR" \
      "$PLAYWRIGHT_MCP_USER_DATA_DIR"

    if [ ! -f "$IPFS_PATH/config" ]; then
      ipfs init --profile=server
      ipfs config Addresses.API "/ip4/127.0.0.1/tcp/5001"
      ipfs config Addresses.Gateway "/ip4/127.0.0.1/tcp/8888"
      ipfs config Datastore.StorageMax "10GB"
    fi

    if [ -f "$CONTRACT_ENV_FILE" ]; then
      set -a
      . "$CONTRACT_ENV_FILE" || true
      set +a
    fi

    echo "AkashGen API dev shell ready"
    echo "- Start services: devenv up"
    echo "- Bootstrap contract + forum config: run"
    echo "- Seed demo data: seed-demo"
    echo "- Browser E2E: e2e"
    echo "- Full verification: verify"
    echo "- Playwright test: pw-test"
    echo "- Playwright MCP bridge: devenv up -d playwright-mcp"
    echo "- Health check: curl http://127.0.0.1:6571/health"
    echo "- Playwright browsers: $PLAYWRIGHT_BROWSERS_PATH"
    echo "- Playwright config: $PLAYWRIGHT_TEST_CONFIG"
  '';

  scripts."ensure-contract".exec = ''
    set -euo pipefail

    rpc_url="''${ANVIL_RPC_URL:-http://127.0.0.1:8545}"
    private_key="''${ANVIL_PRIVATE_KEY:-${defaultAnvilPrivateKey}}"
    env_file="''${CONTRACT_ENV_FILE:-$DEVENV_STATE/contract.env}"
    chain_dir="$AIGC_EXTENSION_ROOT/chain"
    contract_path="src/CollectibleNFT.sol:CollectibleNFT"

    case "$env_file" in
      /*) ;;
      *) env_file="$PWD/$env_file" ;;
    esac

    if [ ! -d "$chain_dir" ]; then
      echo "[ensure-contract] chain directory not found: $chain_dir" >&2
      exit 1
    fi

    mkdir -p "$(dirname "$env_file")"

    for _ in $(seq 1 60); do
      if cast chain-id --rpc-url "$rpc_url" >/dev/null 2>&1; then
        break
      fi
      sleep 1
    done
    cast chain-id --rpc-url "$rpc_url" >/dev/null

    cd "$chain_dir"
    forge build >/dev/null

    contract_addr=""
    if [ -f "$env_file" ]; then
      contract_addr="$(grep '^NFT_CONTRACT_ADDRESS=' "$env_file" | cut -d= -f2- || true)"
    fi

    if [ -n "$contract_addr" ]; then
      code="$(cast code "$contract_addr" --rpc-url "$rpc_url" 2>/dev/null || echo 0x)"
      if [ "$code" != "0x" ]; then
        echo "[ensure-contract] already deployed: $contract_addr"
        exit 0
      fi
    fi

    out="$(forge create "$contract_path" \
      --rpc-url "$rpc_url" \
      --private-key "$private_key" \
      --broadcast)"

    contract_addr="$(echo "$out" | sed -n 's/^Deployed to: //p' | tail -n1)"
    if [ -z "$contract_addr" ]; then
      echo "$out"
      echo "[ensure-contract] could not parse deployed address" >&2
      exit 1
    fi

    printf '%s\n' \
      "BLOCKCHAIN_RPC_URL=$rpc_url" \
      "NFT_CONTRACT_ADDRESS=$contract_addr" \
      "MINTER_PRIVATE_KEY=$private_key" \
      > "$env_file"

    echo "[ensure-contract] deployed: $contract_addr"
    echo "[ensure-contract] wrote $env_file"
  '';

  scripts."sync-contract-settings".exec = ''
        set -euo pipefail

        env_file="''${CONTRACT_ENV_FILE:-$DEVENV_STATE/contract.env}"

        case "$env_file" in
          /*) ;;
          *) env_file="$PWD/$env_file" ;;
        esac

        if [ ! -f "$env_file" ]; then
          echo "[sync-contract-settings] missing contract env: $env_file" >&2
          echo "[sync-contract-settings] run ensure-contract first" >&2
          exit 1
        fi

        set -a
        . "$env_file"
        set +a

        mysql -u flarum -pflarum -h 127.0.0.1 flarum <<SQL
    INSERT INTO settings (\`key\`, \`value\`) VALUES
      ('donk-aigc-collectibles.blockchain-rpc-url', '$BLOCKCHAIN_RPC_URL'),
      ('donk-aigc-collectibles.nft-contract-address', '$NFT_CONTRACT_ADDRESS'),
      ('donk-aigc-collectibles.minter-private-key', '$MINTER_PRIVATE_KEY')
    ON DUPLICATE KEY UPDATE \`value\` = VALUES(\`value\`);
    SQL

        echo "[sync-contract-settings] synced contract settings to Flarum DB"
  '';

  scripts.run.exec = ''
    set -euo pipefail

    wait_for() {
      local label="$1"
      shift

      for _ in $(seq 1 60); do
        if "$@" >/dev/null 2>&1; then
          echo "[run] ready: $label"
          return 0
        fi
        sleep 1
      done

      echo "[run] timeout waiting for $label" >&2
      return 1
    }

    wait_for "MySQL" mysql -u flarum -pflarum -h 127.0.0.1 -e "SELECT 1"
    wait_for "IPFS" curl -fsS -X POST http://127.0.0.1:5001/api/v0/id
    wait_for "Anvil" cast chain-id --rpc-url "''${ANVIL_RPC_URL:-http://127.0.0.1:8545}"
    wait_for "Forum" curl -fsS http://127.0.0.1:8080
    wait_for "AIGC API" curl -fsS http://127.0.0.1:6571/health

    ensure-contract
    sync-contract-settings

    echo "[run] development environment is ready"
  '';

  scripts.ready.exec = ''
    set -euo pipefail
    run "$@"
  '';

  scripts.bootstrap.exec = ''
    set -euo pipefail
    run "$@"
  '';

  scripts."ready-contract".exec = ''
    set -euo pipefail
    ensure-contract "$@"
  '';

  scripts."ready-settings".exec = ''
    set -euo pipefail
    sync-contract-settings "$@"
  '';

  scripts."seed-demo".exec = ''
    set -euo pipefail
    php "$AIGC_EXTENSION_ROOT/scripts/seed-demo.php"
  '';

  scripts."reset-demo".exec = ''
    set -euo pipefail
    seed-demo "$@"
  '';

  scripts."pw-test".exec = ''
    set -euo pipefail
    playwright test --config "$PLAYWRIGHT_TEST_CONFIG" "$@"
  '';

  scripts."pw-headed".exec = ''
    set -euo pipefail
    playwright test --config "$PLAYWRIGHT_TEST_CONFIG" --headed "$@"
  '';

  scripts."pw-codegen".exec = ''
    set -euo pipefail
    playwright codegen "$PLAYWRIGHT_BASE_URL" "$@"
  '';

  scripts."pw-doctor".exec = ''
    set -euo pipefail

    echo "[pw-doctor] playwright: $(command -v playwright)"
    playwright --version
    echo "[pw-doctor] mcp-server-playwright: $(command -v mcp-server-playwright)"
    echo "[pw-doctor] PLAYWRIGHT_BROWSERS_PATH=$PLAYWRIGHT_BROWSERS_PATH"
    echo "[pw-doctor] PLAYWRIGHT_TEST_CONFIG=$PLAYWRIGHT_TEST_CONFIG"
    playwright test --config "$PLAYWRIGHT_TEST_CONFIG" --list >/dev/null
    echo "[pw-doctor] config loads successfully"
  '';

  scripts.e2e.exec = ''
    set -euo pipefail
    pw-test "$@"
  '';

  scripts."e2e-smoke".exec = ''
    set -euo pipefail
    pw-test "$@"
  '';

  scripts."e2e-full".exec = ''
    set -euo pipefail
    verify "$@"
  '';

  scripts."e2e-clean".exec = ''
    set -euo pipefail

    output_dir="''${PLAYWRIGHT_TEST_OUTPUT_DIR:-$DEVENV_STATE/playwright/test-results}"
    report_dir="''${PLAYWRIGHT_HTML_REPORT_DIR:-$DEVENV_STATE/playwright/html-report}"
    mcp_output_dir="''${PLAYWRIGHT_MCP_OUTPUT_DIR:-$DEVENV_STATE/playwright-mcp-output}"

    rm -rf \
      "$output_dir" \
      "$report_dir" \
      "$mcp_output_dir"

    mkdir -p \
      "$output_dir" \
      "$report_dir" \
      "$mcp_output_dir"

    echo "[e2e-clean] cleared Playwright artifacts"
  '';

  scripts.verify.exec = ''
    set -euo pipefail

    run
    seed-demo

    mysql -u flarum -pflarum -h 127.0.0.1 flarum \
      -e "UPDATE users SET last_checkin_at = '2026-03-24 00:00:00' WHERE username = 'admin';" >/dev/null

    token="$(curl -fsS http://127.0.0.1:8080/api/token -X POST \
      -H 'Content-Type: application/json' \
      -d '{"identification":"admin","password":"password"}' | jq -r '.token')"

    if [ "$token" = "null" ] || [ -z "$token" ]; then
      echo "[verify] login failed" >&2
      exit 1
    fi
    echo "[verify] login ok"

    checkin="$(curl -fsS http://127.0.0.1:8080/api/checkin-records/checkin -X POST \
      -H "Authorization: Token $token" \
      -H 'Content-Type: application/json')"
    if echo "$checkin" | jq -e '.data.id' >/dev/null 2>&1; then
      echo "[verify] checkin ok"
    else
      echo "[verify] checkin failed: $(echo "$checkin" | jq -r '.errors[0].detail // "unknown error"')" >&2
      exit 1
    fi

    collectibles="$(curl -fsS "http://127.0.0.1:8080/api/collectibles?filter[user]=1" \
      -H "Authorization: Token $token" | jq -r '.data | length')"
    echo "[verify] collectibles: $collectibles"

    web3_accounts="$(curl -fsS http://127.0.0.1:8080/api/web3-accounts \
      -H "Authorization: Token $token" | jq -r '.data | length')"
    echo "[verify] web3 accounts: $web3_accounts"

    ipfs_id="$(curl -fsS -X POST http://127.0.0.1:5001/api/v0/id | jq -r '.ID // "unavailable"')"
    echo "[verify] ipfs: $ipfs_id"

    chain_id="$(curl -fsS -X POST http://127.0.0.1:8545 \
      -H 'Content-Type: application/json' \
      -d '{"jsonrpc":"2.0","method":"eth_chainId","params":[],"id":1}' | jq -r '.result // "unavailable"')"
    echo "[verify] anvil chain: $chain_id"

    aigc_status="$(curl -fsS http://127.0.0.1:6571/health | jq -r '.status // "unavailable"')"
    echo "[verify] aigc api: $aigc_status"

    pw-test
  '';

  scripts.urls.exec = ''
    set -euo pipefail

    echo "Forum: $PLAYWRIGHT_BASE_URL"
    echo "AkashGen API: http://127.0.0.1:6571/health"
    echo "Anvil RPC: $ANVIL_RPC_URL"
    echo "IPFS API: http://127.0.0.1:5001/api/v0/id"
    echo "IPFS Gateway: http://127.0.0.1:8888"
    echo "Playwright MCP: $PLAYWRIGHT_MCP_URL"
  '';

  scripts.status.exec = ''
    set -euo pipefail

    probe() {
      local label="$1"
      shift

      if "$@" >/dev/null 2>&1; then
        echo "[up]   $label"
      else
        echo "[down] $label"
      fi
    }

    probe "mysql" mysql -u flarum -pflarum -h 127.0.0.1 -e "SELECT 1"
    probe "ipfs" curl -fsS -X POST http://127.0.0.1:5001/api/v0/id
    probe "anvil" cast chain-id --rpc-url "$ANVIL_RPC_URL"
    probe "forum" curl -fsS "$PLAYWRIGHT_BASE_URL"
    probe "akashgen" curl -fsS http://127.0.0.1:6571/health

    mcp_status_code="$(curl -sS -o /dev/null -w '%{http_code}' "$PLAYWRIGHT_MCP_URL" || true)"
    if [ "$mcp_status_code" = "000" ]; then
      echo "[down] playwright-mcp"
    else
      echo "[up]   playwright-mcp (HTTP $mcp_status_code)"
    fi
  '';

  scripts.doctor.exec = ''
    set -euo pipefail

    status
    echo
    pw-doctor
  '';

  processes.ipfs.exec = ''
    export IPFS_PATH="$DEVENV_STATE/ipfs"
    exec ipfs daemon --migrate=true --enable-gc
  '';

  processes.anvil.exec = ''
    mkdir -p "$DEVENV_STATE/anvil"
    exec anvil \
      --host 127.0.0.1 \
      --port 8545 \
      --chain-id 31337 \
      --mnemonic "${anvilMnemonic}" \
      --state "$DEVENV_STATE/anvil/state.json"
  '';

  processes.forum = {
    exec = "php -S 127.0.0.1:8080 -t public";
    cwd = forumDir;
  };

  processes.frontend = {
    exec = "npm run dev";
    cwd = "${projectRoot}/js";
  };

  processes.akashgen = {
    exec = "go run ./main.go";
    cwd = aigcApiDir;
  };

  processes."playwright-mcp".exec = ''
    output_dir="''${PLAYWRIGHT_MCP_OUTPUT_DIR:-$DEVENV_STATE/playwright-mcp-output}"
    user_data_dir="''${PLAYWRIGHT_MCP_USER_DATA_DIR:-$DEVENV_STATE/playwright-mcp-profile}"

    mkdir -p "$output_dir" "$user_data_dir"
    export PLAYWRIGHT_MCP_OUTPUT_DIR="$output_dir"
    export PLAYWRIGHT_MCP_USER_DATA_DIR="$user_data_dir"

    exec mcp-server-playwright \
      --headless \
      --no-sandbox \
      --browser chromium \
      --port "$PLAYWRIGHT_MCP_PORT" \
      --user-data-dir "$PLAYWRIGHT_MCP_USER_DATA_DIR" \
      --init-page "$AIGC_EXTENSION_ROOT/tests/e2e/support/playwright-mcp-init-page.ts" \
      --init-script "$AIGC_EXTENSION_ROOT/tests/e2e/support/playwright-mcp-init-script.js" \
      --output-dir "$PLAYWRIGHT_MCP_OUTPUT_DIR"
  '';
}
