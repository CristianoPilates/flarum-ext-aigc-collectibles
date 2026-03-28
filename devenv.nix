{ pkgs, config, ... }:

let
  playwrightBrowsers = pkgs.playwright-driver.browsers;
  projectRoot = "/home/donk/development/flarum-ext-aigc-collectibles";
  forumDir = "/home/donk/development/flarum-site";
  aigcApiDir = "/home/donk/development/akashgen-api-go";
  stateDir = config.devenv.state;
  anvilMnemonic = "test test test test test test test test test test test junk";
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
    IPFS_PATH = "${stateDir}/ipfs";
    ANVIL_STATE_DIR = "${stateDir}/anvil";
    CONTRACT_ENV_FILE = "${stateDir}/contract.env";
    PLAYWRIGHT_TEST_OUTPUT_DIR = "${stateDir}/playwright/test-results";
    PLAYWRIGHT_HTML_REPORT_DIR = "${stateDir}/playwright/html-report";
    PLAYWRIGHT_MCP_OUTPUT_DIR = "${stateDir}/playwright-mcp-output";
    PLAYWRIGHT_SHARED_USER_DATA_DIR = "${stateDir}/playwright-profile";
    PLAYWRIGHT_MCP_USER_DATA_DIR = "${stateDir}/playwright-profile";
  };

  packages =
    (with pkgs; [
      foundry # anvil / forge / cast
      kubo # ipfs
      jq
      curl
      findutils
      gnumake
      playwright-test
      playwright-mcp
    ]);

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
    ];
    ensureUsers = [
      {
        name = "flarum";
        password = "flarum";
        ensurePermissions = {
          "flarum.*" = "ALL PRIVILEGES";
          "flarum_test.*" = "ALL PRIVILEGES";
        };
      }
    ];
  };

  enterShell = ''
    parse_http_url() {
      local url="$1"
      local rest="''${url#http://}"
      URL_HOST="''${rest%%[:/]*}"
      local after_host="''${rest#"$URL_HOST"}"
      after_host="''${after_host#:}"
      URL_PORT="''${after_host%%/*}"
    }

    mkdir -p \
      "$IPFS_PATH" \
      "$ANVIL_STATE_DIR" \
      "$PLAYWRIGHT_TEST_OUTPUT_DIR" \
      "$PLAYWRIGHT_HTML_REPORT_DIR" \
      "$PLAYWRIGHT_MCP_OUTPUT_DIR"

    if [ ! -e "$PLAYWRIGHT_SHARED_USER_DATA_DIR" ]; then
      mkdir -p "$PLAYWRIGHT_SHARED_USER_DATA_DIR"
    fi

    if [ ! -f "$IPFS_PATH/config" ]; then
      parse_http_url "$IPFS_API_URL"
      ipfs_api_host="$URL_HOST"
      ipfs_api_port="$URL_PORT"
      parse_http_url "$IPFS_GATEWAY_URL"
      ipfs_gateway_host="$URL_HOST"
      ipfs_gateway_port="$URL_PORT"

      ipfs init --profile=server
      ipfs config Addresses.API "/ip4/$ipfs_api_host/tcp/$ipfs_api_port"
      ipfs config Addresses.Gateway "/ip4/$ipfs_gateway_host/tcp/$ipfs_gateway_port"
      ipfs config Datastore.StorageMax "10GB"
    fi

    echo "AkashGen API dev shell ready"
    echo "- Start services: make dev"
    echo "- Init stack: make init"
    echo "- Playwright: playwright test"
    echo "- Playwright browsers: $PLAYWRIGHT_BROWSERS_PATH"
  '';

  processes.ipfs.exec = ''
    exec ipfs daemon --migrate=true --enable-gc
  '';

  processes.anvil.exec = ''
    rpc_url="''${ANVIL_RPC_URL:?ANVIL_RPC_URL is required}"
    rest="''${rpc_url#http://}"
    host="''${rest%%[:/]*}"
    after_host="''${rest#"$host"}"
    after_host="''${after_host#:}"
    port="''${after_host%%/*}"

    exec anvil \
      --host "$host" \
      --port "$port" \
      --chain-id 31337 \
      --mnemonic "${anvilMnemonic}" \
      --state "$ANVIL_STATE_DIR/state.json"
  '';

  processes.forum = {
    exec = ''
      forum_url="''${FORUM_URL:?FORUM_URL is required}"
      rest="''${forum_url#http://}"
      host="''${rest%%[:/]*}"
      after_host="''${rest#"$host"}"
      after_host="''${after_host#:}"
      port="''${after_host%%/*}"

      exec php -S "$host:$port" -t public
    '';
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
    mcp_url="''${PLAYWRIGHT_MCP_URL:?PLAYWRIGHT_MCP_URL is required}"
    rest="''${mcp_url#http://}"
    host="''${rest%%[:/]*}"
    after_host="''${rest#"$host"}"
    after_host="''${after_host#:}"
    port="''${after_host%%/*}"

    exec mcp-server-playwright \
      --config "${projectRoot}/scripts/playwright/mcp.config.json" \
      --user-data-dir "$PLAYWRIGHT_MCP_USER_DATA_DIR" \
      --headless \
      --no-sandbox \
      --port "$port"
  '';
}
