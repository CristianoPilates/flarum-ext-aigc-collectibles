{
  pkgs,
  ...
}:

{
  languages = {
    javascript = {
      enable = true;
      npm.install.enable = true;
    };
    php.enable = true;
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

  dotenv.enable = true;
}
