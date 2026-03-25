SITE_DIR    := /home/donk/development/flarum-site
EXT_DIR     := $(shell pwd)
EXT_NAME    := donk/flarum-ext-aigc-collectibles
FLARUM_VER  := ^2.0.0

.PHONY: up down restart status doctor urls \
        up-mysql up-ipfs up-anvil up-akashgen up-forum up-playwright \
        ready bootstrap ready-contract ready-settings seed-demo reset-demo \
        verify e2e e2e-smoke e2e-full e2e-clean \
        pw-test pw-headed pw-codegen pw-doctor playwright-mcp \
        site link unlink enable disable migrate migrate-reset test \
        js-install js-dev js-build reset clean all help

# === 开发环境编排 ===
up: site link
	devenv up -d

down:
	devenv processes down

restart: down up

status:
	devenv shell -- status

doctor:
	devenv shell -- doctor

urls:
	devenv shell -- urls

up-mysql:
	devenv up -d mysql

up-ipfs:
	devenv up -d ipfs

up-anvil:
	devenv up -d anvil

up-akashgen:
	devenv up -d akashgen

up-forum: site link
	devenv up -d forum frontend

up-playwright:
	devenv up -d playwright-mcp

playwright-mcp: up-playwright

# === 环境准备 ===
run: ready

ready:
	devenv shell -- ready

bootstrap: ready

ready-contract:
	devenv shell -- ready-contract

ready-settings:
	devenv shell -- ready-settings

seed-demo:
	devenv shell -- seed-demo

reset-demo:
	devenv shell -- reset-demo

verify:
	devenv shell -- verify

# === Playwright / E2E ===
pw-test:
	devenv shell -- pw-test

pw-headed:
	devenv shell -- pw-headed

pw-codegen:
	devenv shell -- pw-codegen

pw-doctor:
	devenv shell -- pw-doctor

e2e: pw-test

e2e-smoke: pw-test

e2e-full:
	devenv shell -- e2e-full

e2e-clean:
	devenv shell -- e2e-clean

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

# === 将扩展软链接到站点 ===
link: site
	@echo ">>> Linking extension..."
	cd $(SITE_DIR) && composer config repositories.donk-aigc-collectibles \
		path $(EXT_DIR)
	cd $(SITE_DIR) && composer require $(EXT_NAME):@dev

# === 断开扩展链接 ===
unlink:
	cd $(SITE_DIR) && composer remove $(EXT_NAME) || true
	cd $(SITE_DIR) && composer config --unset \
		repositories.donk-aigc-collectibles || true

# === 启用扩展（Flarum 必须已安装） ===
enable:
	cd $(SITE_DIR) && php flarum extension:enable donk-aigc-collectibles

# === 禁用扩展 ===
disable:
	cd $(SITE_DIR) && php flarum extension:disable donk-aigc-collectibles

# === 运行迁移（扩展已启用后，新增迁移文件时用） ===
migrate:
	cd $(SITE_DIR) && php flarum migrate

migrate-reset:
	cd $(SITE_DIR) && php flarum migrate:reset

# === 测试 / 前端 ===
test:
	cd $(EXT_DIR) && vendor/bin/phpunit

js-install:
	cd $(EXT_DIR)/js && npm install

js-dev:
	cd $(EXT_DIR)/js && npm run dev

js-build:
	cd $(EXT_DIR)/js && npm run build

# === 完整重建 ===
reset: clean site link
	@echo ""
	@echo ">>> Site created and extension linked."
	@echo ">>> Next steps:"
	@echo "    1. make up"
	@echo "    2. make ready"
	@echo "    3. Visit http://127.0.0.1:8080 if Flarum is not installed yet"
	@echo "    4. make enable"
	@echo ""

# === 清除站点 ===
clean:
	@echo ">>> Removing Flarum site..."
	rm -rf $(SITE_DIR)

# === 一键（Flarum 已安装后） ===
all: link js-install js-build enable
	@echo ">>> Everything ready."

help:
	@echo ""
	@echo "  === 进程控制 ==="
	@echo "  make up             - 后台启动整套开发环境"
	@echo "  make down           - 停止 devenv 管理的进程"
	@echo "  make restart        - 重启整套开发环境"
	@echo "  make status         - 探测各服务状态"
	@echo "  make doctor         - 状态 + Playwright 运行时检查"
	@echo "  make urls           - 打印常用访问地址"
	@echo "  make up-mysql       - 仅启动 MySQL"
	@echo "  make up-ipfs        - 仅启动 IPFS"
	@echo "  make up-anvil       - 仅启动 Anvil"
	@echo "  make up-akashgen    - 仅启动 AkashGen API"
	@echo "  make up-forum       - 启动 Flarum + frontend watch"
	@echo "  make up-playwright  - 启动 Playwright MCP"
	@echo ""
	@echo "  === 环境准备 ==="
	@echo "  make ready          - 等待服务就绪并同步链上配置"
	@echo "  make ready-contract - 部署/校验测试合约"
	@echo "  make ready-settings - 同步合约配置到 Flarum"
	@echo "  make seed-demo      - 写入演示数据"
	@echo "  make reset-demo     - 重置演示数据"
	@echo "  make verify         - 完整验证（bootstrap + seed + API + E2E）"
	@echo ""
	@echo "  === Playwright ==="
	@echo "  make pw-test        - 运行 Playwright Test"
	@echo "  make pw-headed      - 以 headed 模式运行 Playwright Test"
	@echo "  make pw-codegen     - 打开 Playwright codegen"
	@echo "  make pw-doctor      - 检查 Playwright runtime/config"
	@echo "  make e2e            - 运行 E2E smoke specs"
	@echo "  make e2e-full       - 执行完整 E2E 验证"
	@echo "  make e2e-clean      - 清理 Playwright 产物"
	@echo ""
	@echo "  === Flarum / 扩展 ==="
	@echo "  make site           - 创建 Flarum 站点"
	@echo "  make link           - 链接扩展到站点"
	@echo "  make enable         - 启用扩展"
	@echo "  make migrate        - 运行新增迁移"
	@echo "  make test           - 运行 PHPUnit"
	@echo "  make js-dev         - 前端开发模式"
	@echo "  make js-build       - 前端生产构建"
	@echo "  make reset          - 删除站点并重建"
	@echo "  make clean          - 删除站点"
	@echo ""
