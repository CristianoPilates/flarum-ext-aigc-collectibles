SITE_DIR    := /home/donk/development/flarum-site
EXT_DIR     := $(shell pwd)
EXT_NAME    := donk/flarum-ext-aigc-collectibles
FLARUM_VER  := ^2.0.0

.PHONY: up status \
        up-mysql up-ipfs up-anvil up-akashgen up-forum up-playwright \
        chain-ready seed-demo verify reset \
        site enable disable migrate migrate-reset test help

# === 开发环境编排 ===
up:
	devenv up -d

status:
	./scripts/status.sh

up-mysql:
	devenv up -d mysql

up-ipfs:
	devenv up -d ipfs

up-anvil:
	devenv up -d anvil

up-akashgen:
	devenv up -d akashgen

up-forum: site enable
	./scripts/sync-forum-config.sh
	devenv up -d forum frontend

up-playwright:
	devenv up -d playwright-mcp

chain-ready:
	./scripts/chain-ready.sh

seed-demo:
	php ./scripts/seed-demo.php

verify:
	./scripts/verify.sh

# === 创建 Flarum 站点（幂等） ===
site:
	@if [ ! -f "$(SITE_DIR)/flarum" ]; then \
		echo ">>> Creating Flarum site..."; \
		composer create-project flarum/flarum:$(FLARUM_VER) \
			--stability=beta $(SITE_DIR); \
		cd $(SITE_DIR) && composer require flarum/extension-manager:"*"; \
	else \
		echo ">>> Flarum site already exists, skipping."; \
	fi
	./scripts/sync-forum-config.sh

# === 启用扩展（Flarum 必须已安装） ===
enable: site
	@echo ">>> Linking and enabling extension..."
	./scripts/sync-forum-config.sh
	cd $(SITE_DIR) && composer config repositories.donk-aigc-collectibles path $(EXT_DIR)
	cd $(SITE_DIR) && composer require $(EXT_NAME):@dev
	cd $(SITE_DIR) && php flarum extension:enable donk-aigc-collectibles

# === 禁用扩展 ===
disable:
	cd $(SITE_DIR) && php flarum extension:disable donk-aigc-collectibles
	cd $(SITE_DIR) && composer remove $(EXT_NAME) || true
	cd $(SITE_DIR) && composer config --unset repositories.donk-aigc-collectibles || true

# === 运行迁移（扩展已启用后，新增迁移文件时用） ===
migrate:
	cd $(SITE_DIR) && php flarum migrate

migrate-reset:
	cd $(SITE_DIR) && php flarum migrate:reset

test:
	cd $(EXT_DIR) && vendor/bin/phpunit

# === 完整重建 ===
reset:
	rm -rf .devenv/state/anvil
	rm -rf .devenv/state/ipfs
	rm -rf .devenv/state/playwright
	rm -rf .devenv/state/playwright-mcp-output
	rm -rf .devenv/state/playwright-mcp-profile
	rm -f .devenv/state/contract.env

help:
	@echo ""
	@echo "  === 进程控制 ==="
	@echo "  make up             - 后台启动整套开发环境"
	@echo "  make status         - 探测各服务状态"
	@echo "  make up-mysql       - 仅启动 MySQL"
	@echo "  make up-ipfs        - 仅启动 IPFS"
	@echo "  make up-anvil       - 仅启动 Anvil"
	@echo "  make up-akashgen    - 仅启动 AkashGen API"
	@echo "  make up-forum       - 启动 Flarum + frontend watch"
	@echo "  make up-playwright  - 启动 Playwright MCP"
	@echo ""
	@echo "  === 业务编排 ==="
	@echo "  make chain-ready    - 部署/校验合约并同步链配置"
	@echo "  make seed-demo      - 写入演示数据"
	@echo "  make verify         - 跑完整验收流程"
	@echo "  playwright test     - 直接运行 Playwright"
	@echo "  playwright test --headed - 直接运行 headed Playwright"
	@echo "  playwright codegen  - 直接运行 Playwright codegen"
	@echo ""
	@echo "  === Flarum / 扩展 ==="
	@echo "  make site           - 创建 Flarum 站点"
	@echo "  make enable         - 链接并启用扩展"
	@echo "  make disable        - 禁用并移除扩展"
	@echo "  make migrate        - 运行新增迁移"
	@echo "  make test           - 运行 PHPUnit"
	@echo "  make reset          - 打印回到下一轮测试起点的重置步骤"
	@echo ""
