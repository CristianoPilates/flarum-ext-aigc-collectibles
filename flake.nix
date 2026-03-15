{
  description = "Flarum dev environment";

  inputs = {
    devenv-root = {
      url = "file+file:///dev/null";
      flake = false;
    };
    nixpkgs.url = "github:cachix/devenv-nixpkgs/rolling";
    flake-parts = {
      url = "github:hercules-ci/flake-parts";
      inputs.nixpkgs-lib.follows = "nixpkgs";
    };
    devenv.url = "github:cachix/devenv";
  };

  outputs =
    inputs@{ flake-parts, ... }:
    flake-parts.lib.mkFlake { inherit inputs; } {
      imports = [ inputs.devenv.flakeModule ];

      systems = [ "x86_64-linux" ];

      perSystem =
        { pkgs, ... }:
        {
          devenv.shells.default = {

            # ── use flake: activated by direnv ──────────────────
            languages = {
              javascript = {
                enable = true;
                npm.install.enable = true;
              };
              php.enable = true;
            };

            # ── devenv up: activated by `devenv up` ─────────────

            services.mysql = {
              enable = true;
              package = pkgs.mysql80;
              settings.mysqld = {
                default-storage-engine = "InnoDB";
                bind-address = "127.0.0.1";
                port = 3306;
              };
              initialDatabases = [
                { name = "flarum"; }
              ];
              ensureUsers = [
                {
                  name = "flarum";
                  password = "flarum";
                  ensurePermissions = {
                    "flarum.*" = "ALL PRIVILEGES";
                  };
                }
              ];
            };

            processes.flarum = {
              cwd = "./";
              exec = "php -S 127.0.0.1:8080 -t public";
              after = [ "devenv:processes:mysql@started" ];
            };
          };
        };
    };
}
