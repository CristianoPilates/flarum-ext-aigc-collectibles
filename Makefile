SITE_DIR    := /home/donk/development/flarum-site
EXT_DIR     := $(shell pwd)
EXT_NAME    := donk/flarum-ext-aigc-collectibles
FLARUM_VER  := ^2.0.0

.PHONY: up site link unlink enable disable migrate test \
        js-install js-dev js-build reset clean help migrate-reset

# === 启动开发环境 ===
up: site link
	devenv up

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

# === 运行测试 ===
test:
	cd $(EXT_DIR) && vendor/bin/phpunit

# === 前端 ===
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
	@echo "    2. Visit http://127.0.0.1:8080 to install Flarum"
	@echo "    3. make enable"
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
	@echo "  === 初始搭建 ==="
	@echo "  make site       - 创建 Flarum 站点"
	@echo "  make link       - 链接扩展到站点"
	@echo "  make enable     - 启用扩展（需先完成 Flarum 安装）"
	@echo ""
	@echo "  === 日常开发 ==="
	@echo "  make up         - 启动开发环境"
	@echo "  make migrate    - 运行新增的迁移"
	@echo "  make test       - 运行测试"
	@echo "  make js-dev     - 前端开发模式"
	@echo "  make js-build   - 前端生产构建"
	@echo ""
	@echo "  === 核武器 ==="
	@echo "  make reset      - 删除站点并重建"
	@echo "  make clean      - 删除站点"
	@echo ""
