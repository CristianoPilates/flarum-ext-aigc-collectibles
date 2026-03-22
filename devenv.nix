{ pkgs, ... }:

{
  dotenv.enable = true;
  env = {
    GO111MODULE = "on";
    CGO_ENABLED = "0";
  };

  packages = with pkgs; [
    foundry # anvil / forge / cast
    kubo # ipfs
    jq
    curl
    gnumake
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
    export IPFS_PATH="$DEVENV_STATE/ipfs"
    export ANVIL_STATE_DIR="$DEVENV_STATE/anvil"
    export CONTRACT_ENV_FILE="$DEVENV_STATE/contract.env"

    mkdir -p "$IPFS_PATH" "$ANVIL_STATE_DIR"

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
    echo "- Start once: run"
    echo "- Keep running with process manager: devenv up"
    echo "- Health check: curl http://127.0.0.1:6571/health"
  '';

  processes.ipfs.exec = "ipfs daemon --migrate=true --enable-gc";

  processes.anvil.exec = ''
    anvil \
      --host 127.0.0.1 \
      --port 8545 \
      --chain-id 31337 \
      --mnemonic "test test test test test test test test test test test junk" \
      --state "$DEVENV_STATE/anvil/state.json"
  '';

  # one-shot: 幂等确保合约已部署
  processes.contract-ensure.exec = "bash scripts/ensure-contract.sh";

  processes.forum = {
    exec = "php -S 127.0.0.1:8080 -t public";
    cwd = "/home/donk/development/flarum-site/";
  };

  processes.akashgen-api-go = {
    exec = "go run ./main.go";
    cwd = "/home/donk/development/akashgen-api-go/";
  };
}
