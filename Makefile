# Runtime/project paths (SITE_DIR follows .env FLARUM_SITE_DIR by default)
SITE_DIR    ?= $(FLARUM_SITE_DIR)
EXT_DIR     := $(shell pwd)
STATE_DIR   := $(EXT_DIR)/.devenv/state
EXT_NAME    := donk/flarum-ext-aigc-collectibles
FLARUM_VER  := ^2.0.0
PLAYWRIGHT_MCP_PORT := $(shell printf '%s\n' "$(PLAYWRIGHT_MCP_URL)" | sed -n 's#.*:\([0-9][0-9]*\)/.*#\1#p')
PLAYWRIGHT_MCP_OUTPUT_DIR ?= $(STATE_DIR)/playwright-mcp-output
# Persistent seed profile for manual wallet setup; MCP runs use a fresh runtime copy.
PLAYWRIGHT_SHARED_USER_DATA_DIR := $(STATE_DIR)/playwright-profile
PLAYWRIGHT_MCP_USER_DATA_DIR := $(STATE_DIR)/playwright-mcp-profile
PLAYWRIGHT_MCP_CONFIG := $(EXT_DIR)/scripts/playwright/mcp.config.json
PLAYWRIGHT_MCP_CLI_DIR := $(EXT_DIR)/scripts/playwright/mcp-cli
DEVENV_EXEC := $(EXT_DIR)/scripts/runtime/with-devenv.sh
PLAYWRIGHT_MANUAL_USER_DATA_DIR := $(PLAYWRIGHT_SHARED_USER_DATA_DIR)
PLAYWRIGHT_MANUAL_CHANNEL ?= chromium
PLAYWRIGHT_MANUAL_EXTENSION_DIRS ?= $(EXT_DIR)/e2e/support/nkbihfbeogaeaoehlefnkodbefgpgknn
PW_MCP_HEADLESS ?= 1
PW_SMOKE_EXTERNAL_PID_FILE ?= $(STATE_DIR)/pw-smoke-external.pid
PW_SMOKE_SITE_PID_FILE ?= $(STATE_DIR)/pw-smoke-site.pid
PW_SMOKE_EXTERNAL_LOG_FILE ?= $(STATE_DIR)/pw-smoke-external.log
PW_SMOKE_SITE_LOG_FILE ?= $(STATE_DIR)/pw-smoke-site.log

ifneq (,$(wildcard ./.env))
include .env
export
endif

ifeq ($(strip $(SITE_DIR)),)
$(error SITE_DIR is empty; set FLARUM_SITE_DIR in .env or export SITE_DIR)
endif

.PHONY: up down status dev pw-smoke pw-manual mcp mcp-headed prepare-playwright-profile prepare-playwright-mcp-profile \
        mcp-state mcp-minimal-nft mcp-debug-mint mcp-focus-metamask mcp-storage \
        mcp-showcase mcp-proof mcp-messages mcp-barter-inspect mcp-barter locale-status locale-set-en locale-set-zh-hans locale-set-zh-Hans \
        up-site up-external up-mysql up-ipfs up-anvil up-akashgen \
        init init-site init-chain init-test-data assert-mysql assert-no-pw-smoke-scene assert-runtime-clean verify reset-state \
        enable-messages publish-site-runtime \
        site enable disable migrate migrate-reset test help

# === 开发环境编排 ===
up: assert-runtime-clean
	devenv up mysql ipfs anvil akashgen forum frontend

status:
	./scripts/health/probe.sh

down:
	./scripts/runtime/stack.sh down

dev: assert-runtime-clean
	devenv up mysql ipfs anvil akashgen forum frontend

up-site: assert-runtime-clean
	devenv up mysql forum frontend

up-external: assert-runtime-clean
	devenv up ipfs anvil akashgen

init: init-site init-chain init-test-data

init-site: assert-mysql site enable enable-messages publish-site-runtime

init-chain:
	./scripts/chain/bootstrap.sh

init-test-data:
	php ./scripts/playwright/prepare-data.php

assert-mysql:
	@"$(DEVENV_EXEC)" mysql \
		-u "$(DB_USERNAME)" \
		-p"$(DB_PASSWORD)" \
		-h "$(DB_HOST)" \
		-P "$(DB_PORT)" \
		-e "SELECT 1" >/dev/null 2>&1 \
	|| (echo "mysql is not running; start it with make up-site or make up-mysql" >&2; exit 1)

assert-runtime-clean:
	@./scripts/runtime/stack.sh check

assert-no-pw-smoke-scene:
	@set -eu; \
	for file in "$(PW_SMOKE_EXTERNAL_PID_FILE)" "$(PW_SMOKE_SITE_PID_FILE)"; do \
		if [ -f "$$file" ]; then \
			pid="$$(cat "$$file" 2>/dev/null || true)"; \
			if [ -n "$$pid" ] && kill -0 "$$pid" >/dev/null 2>&1; then \
				echo "retained pw-smoke scene is still running; inspect it or run make down" >&2; \
				exit 1; \
			fi; \
			rm -f "$$file"; \
		fi; \
	done

pw-smoke: assert-runtime-clean assert-no-pw-smoke-scene
	@set -eu; \
	mkdir -p "$(STATE_DIR)"; \
	trap 'status="$$?"; \
		echo "pw-smoke scene retained; use make down to clean up"; \
		exit "$$status"' INT TERM EXIT; \
	setsid devenv up ipfs anvil akashgen mysql >"$(PW_SMOKE_EXTERNAL_LOG_FILE)" 2>&1 < /dev/null & \
	ext_pid="$$!"; \
	printf '%s\n' "$$ext_pid" > "$(PW_SMOKE_EXTERNAL_PID_FILE)"; \
	for _ in $$(seq 1 60); do \
		if ./scripts/health/probe.sh | rg -q "^\[up\]\s+mysql$$" \
		&& ./scripts/health/probe.sh | rg -q "^\[up\]\s+ipfs$$" \
		&& ./scripts/health/probe.sh | rg -q "^\[up\]\s+anvil$$" \
		&& ./scripts/health/probe.sh | rg -q "^\[up\]\s+akashgen$$"; then \
			break; \
		fi; \
		sleep 1; \
	done; \
	make init-site; \
	setsid devenv up forum frontend >"$(PW_SMOKE_SITE_LOG_FILE)" 2>&1 < /dev/null & \
	site_pid="$$!"; \
	printf '%s\n' "$$site_pid" > "$(PW_SMOKE_SITE_PID_FILE)"; \
	for _ in $$(seq 1 60); do \
		if ./scripts/health/probe.sh | rg -q "^\[up\]\s+forum$$" \
		&& ./scripts/health/probe.sh | rg -q "^\[up\]\s+frontend$$"; then \
			break; \
		fi; \
		sleep 1; \
	done; \
	make init-chain; \
	make init-test-data; \
	"$(DEVENV_EXEC)" playwright test --grep @smoke

prepare-playwright-profile:
	@mkdir -p "$(STATE_DIR)"
	@if [ ! -e "$(PLAYWRIGHT_SHARED_USER_DATA_DIR)" ]; then mkdir -p "$(PLAYWRIGHT_SHARED_USER_DATA_DIR)"; fi

prepare-playwright-mcp-profile: prepare-playwright-profile
	@rm -rf "$(PLAYWRIGHT_MCP_USER_DATA_DIR)"
	@mkdir -p "$(PLAYWRIGHT_MCP_USER_DATA_DIR)"
	@if [ -d "$(PLAYWRIGHT_SHARED_USER_DATA_DIR)" ]; then \
		tar -C "$(PLAYWRIGHT_SHARED_USER_DATA_DIR)" -cf - . | tar -C "$(PLAYWRIGHT_MCP_USER_DATA_DIR)" -xf -; \
	fi
	@find "$(PLAYWRIGHT_MCP_USER_DATA_DIR)" -maxdepth 1 \( -name 'Singleton*' -o -name 'lockfile' \) -delete
	@rm -f \
		"$(PLAYWRIGHT_MCP_USER_DATA_DIR)/Default/Current Session" \
		"$(PLAYWRIGHT_MCP_USER_DATA_DIR)/Default/Current Tabs" \
		"$(PLAYWRIGHT_MCP_USER_DATA_DIR)/Default/Last Session" \
		"$(PLAYWRIGHT_MCP_USER_DATA_DIR)/Default/Last Tabs"
	@rm -rf "$(PLAYWRIGHT_MCP_USER_DATA_DIR)/Default/Sessions"

pw-manual: prepare-playwright-profile
	@set -eu; \
	curl -fsS "$(FORUM_URL)" >/dev/null; \
	PLAYWRIGHT_MANUAL_USER_DATA_DIR="$(PLAYWRIGHT_MANUAL_USER_DATA_DIR)" \
	PLAYWRIGHT_MANUAL_CHANNEL="$(PLAYWRIGHT_MANUAL_CHANNEL)" \
	PLAYWRIGHT_MANUAL_EXTENSION_DIRS="$(PLAYWRIGHT_MANUAL_EXTENSION_DIRS)" \
	"$(DEVENV_EXEC)" node ./scripts/playwright/launch-manual.cjs

mcp-headed:
	@$(MAKE) mcp PW_MCP_HEADLESS=0

mcp: prepare-playwright-profile
	@mkdir -p "$(PLAYWRIGHT_MCP_OUTPUT_DIR)" "$(PLAYWRIGHT_MCP_USER_DATA_DIR)"
	@set -eu; \
	curl -fsS "$(FORUM_URL)" >/dev/null; \
	pid="$$(ss -ltnp '( sport = :$(PLAYWRIGHT_MCP_PORT) )' | sed -n 's/.*pid=\([0-9]\+\).*/\1/p' | head -n1)"; \
	if [ -n "$$pid" ]; then kill "$$pid" >/dev/null 2>&1 || true; fi; \
	$(MAKE) prepare-playwright-mcp-profile; \
	trap 'status="$$?"; if [ -n "$${mcp_pid:-}" ]; then kill "$$mcp_pid" >/dev/null 2>&1 || true; wait "$$mcp_pid" 2>/dev/null || true; fi; exit "$$status"' INT TERM EXIT; \
	echo "playwright-mcp profile: $(PLAYWRIGHT_MCP_USER_DATA_DIR)"; \
	"$(DEVENV_EXEC)" mcp-server-playwright \
	  --config "$(PLAYWRIGHT_MCP_CONFIG)" \
	  --user-data-dir "$(PLAYWRIGHT_MCP_USER_DATA_DIR)" \
	  $$( [ "$(PW_MCP_HEADLESS)" = "1" ] && printf '%s' '--headless' ) \
	  --no-sandbox \
	  & \
	mcp_pid="$$!"; \
	ready=""; \
	for _ in $$(seq 1 30); do \
		code="$$(curl -sS -o /dev/null -w '%{http_code}' "$(PLAYWRIGHT_MCP_URL)" || true)"; \
		if [ "$$code" = "200" ] || [ "$$code" = "400" ]; then \
			ready=1; \
			break; \
		fi; \
		sleep 1; \
	done; \
	if [ "$$ready" != "1" ]; then \
		echo "playwright-mcp failed to listen on $(PLAYWRIGHT_MCP_URL)" >&2; \
		exit 1; \
	fi; \
	echo "playwright-mcp ready: $(PLAYWRIGHT_MCP_URL)"; \
	wait "$$mcp_pid"

# Optional CLI wrappers over the running Playwright MCP server.
mcp-state:
	"$(DEVENV_EXEC)" node "$(PLAYWRIGHT_MCP_CLI_DIR)/mcp-inspect-state.cjs"

mcp-minimal-nft:
	"$(DEVENV_EXEC)" node "$(PLAYWRIGHT_MCP_CLI_DIR)/mcp-minimal-nft.cjs"

mcp-debug-mint:
	"$(DEVENV_EXEC)" node "$(PLAYWRIGHT_MCP_CLI_DIR)/mcp-debug-mint-state.cjs"

mcp-focus-metamask:
	"$(DEVENV_EXEC)" node "$(PLAYWRIGHT_MCP_CLI_DIR)/mcp-focus-metamask.cjs"

mcp-storage:
	"$(DEVENV_EXEC)" node "$(PLAYWRIGHT_MCP_CLI_DIR)/mcp-inspect-metamask-storage.cjs"

mcp-showcase:
	"$(DEVENV_EXEC)" node "$(PLAYWRIGHT_MCP_CLI_DIR)/mcp-validate-showcase.cjs"

mcp-proof:
	"$(DEVENV_EXEC)" node "$(PLAYWRIGHT_MCP_CLI_DIR)/mcp-validate-proof.cjs"

mcp-messages:
	"$(DEVENV_EXEC)" node "$(PLAYWRIGHT_MCP_CLI_DIR)/mcp-validate-messages.cjs"

mcp-barter-inspect:
	"$(DEVENV_EXEC)" node "$(PLAYWRIGHT_MCP_CLI_DIR)/mcp-inspect-barter-composer.cjs"

mcp-barter:
	"$(DEVENV_EXEC)" node "$(PLAYWRIGHT_MCP_CLI_DIR)/mcp-validate-barter-composer.cjs"

up-mysql: assert-runtime-clean
	devenv up mysql

up-ipfs: assert-runtime-clean
	devenv up ipfs

up-anvil: assert-runtime-clean
	devenv up anvil

up-akashgen: assert-runtime-clean
	devenv up akashgen

verify:
	./scripts/verify/run.sh

# === 创建并安装 Flarum 站点（幂等，官方 non-interactive install） ===
site:
	FLARUM_VER="$(FLARUM_VER)" ./scripts/forum/install.sh

# === 启用扩展（Flarum 必须已安装，且 mysql 已运行） ===
enable: assert-mysql site
	@echo ">>> Linking and enabling extension..."
	cd $(SITE_DIR) && composer config repositories.donk-aigc-collectibles path $(EXT_DIR)
	@cd $(SITE_DIR) && if composer show $(EXT_NAME) >/dev/null 2>&1; then \
		echo ">>> Extension package already linked"; \
	else \
		composer require $(EXT_NAME):@dev; \
	fi
	@cd $(SITE_DIR) && output="$$(php flarum extension:enable donk-aigc-collectibles 2>&1)"; \
	status="$$?"; \
	printf '%s\n' "$$output"; \
	if [ "$$status" -eq 0 ]; then \
		exit 0; \
	fi; \
	echo "$$output" | grep -Fq "already enabled" && exit 0; \
	exit "$$status"

enable-messages: assert-mysql site
	@cd $(SITE_DIR) && output="$$(php flarum extension:enable flarum-messages 2>&1)"; \
	status="$$?"; \
	printf '%s\n' "$$output"; \
	if [ "$$status" -eq 0 ]; then \
		exit 0; \
	fi; \
	echo "$$output" | grep -Fq "already enabled" && exit 0; \
	exit "$$status"

publish-site-runtime:
	cd $(SITE_DIR) && php flarum assets:publish
	cd $(SITE_DIR) && php flarum cache:clear

locale-status:
	FLARUM_SITE_DIR="$(SITE_DIR)" php ./scripts/forum/locale-status.php

locale-set-en:
	FLARUM_SITE_DIR="$(SITE_DIR)" php ./scripts/forum/set-default-locale.php en
	cd $(SITE_DIR) && php flarum cache:clear

locale-set-zh-hans:
	FLARUM_SITE_DIR="$(SITE_DIR)" php ./scripts/forum/set-default-locale.php zh-hans
	cd $(SITE_DIR) && php flarum cache:clear

locale-set-zh-Hans:
	FLARUM_SITE_DIR="$(SITE_DIR)" php ./scripts/forum/set-default-locale.php zh-Hans
	cd $(SITE_DIR) && php flarum cache:clear

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

# === 状态重建 ===
reset-state: assert-runtime-clean
	rm -rf .devenv/state/anvil
	rm -rf .devenv/state/ipfs
	rm -rf .devenv/state/playwright
	rm -rf .devenv/state/playwright-profile
	rm -rf .devenv/state/playwright-mcp-profile
	rm -rf .devenv/state/playwright-mcp-output
	rm -f .devenv/state/pw-test-external.pid
	rm -f .devenv/state/pw-test-site.pid
	rm -f .devenv/state/pw-test-external.log
	rm -f .devenv/state/pw-test-site.log
	rm -f .devenv/state/pw-smoke-external.pid
	rm -f .devenv/state/pw-smoke-site.pid
	rm -f .devenv/state/pw-smoke-external.log
	rm -f .devenv/state/pw-smoke-site.log
	rm -f .devenv/state/contract.env

help:
	@echo ""
	@echo "  === 三个闭环 ==="
	@echo "  make dev            - 完整开发编排：启动站点本体 + 外部服务 + frontend watch"
	@echo "  make pw-smoke       - Headless 冒烟测试闭环：拉起测试依赖、初始化并跑 @smoke E2E 子集"
	@echo "  make pw-manual      - Headed 手工测试：打开可加载 unpacked extensions 的持久化 Chromium"
	@echo "  make mcp            - MCP 自动化闭环（默认 headless，使用从 playwright-profile 派生的临时运行 profile）"
	@echo "  make mcp-headed     - MCP 自动化闭环（headed，使用从 playwright-profile 派生的临时运行 profile）"
	@echo "  make mcp-state      - 通过 MCP 检查当前 MCP 运行 profile / MetaMask / 应用状态"
	@echo "  make mcp-minimal-nft - 通过 MCP 跑最小 NFT 路径（需要先提供 METAMASK_PASSWORD）"
	@echo ""
	@echo "  === 支撑命令 ==="
	@echo "  make up             - 前台启动整套开发环境"
	@echo "  make down           - 停掉当前项目的运行现场"
	@echo "  make status         - 探测各服务状态"
	@echo "  make up-site        - 启动论坛本体 + mysql + frontend watch"
	@echo "  make up-external    - 启动外部服务：ipfs + anvil + akashgen"
	@echo "  make up-mysql       - 单独启动 mysql"
	@echo "  make up-ipfs        - 单独启动 ipfs"
	@echo "  make up-anvil       - 单独启动 anvil"
	@echo "  make up-akashgen    - 单独启动 akashgen"
	@echo "  make init           - 低频初始化总入口"
	@echo "  make init-site      - 低频初始化：建站、同步配置、启用 collectibles + messages"
	@echo "  make init-chain     - 低频初始化：部署/复用合约并写回论坛设置"
	@echo "  make init-test-data - 低频初始化：准备 Playwright 测试数据"
	@echo "  make verify         - 跑完整验收流程"
	@echo "  make reset-state    - 清理 .devenv/state 持久化状态（需先 make down）"
	@echo "  make pw-smoke       - 运行 @smoke 自动冒烟测试"
	@echo "  make mcp-debug-mint - 通过 MCP 排查 collectible 的 mint 状态"
	@echo "  make mcp-focus-metamask - 通过 MCP 聚焦 MetaMask 页面"
	@echo "  make mcp-storage    - 通过 MCP 检查 MetaMask 扩展存储"
	@echo "  make mcp-messages   - 通过 MCP 验证 buyer -> seller 私信链路"
	@echo "  make mcp-barter-inspect - 通过 MCP 检查私信线程内 barter composer 是否成功挂载"
	@echo "  make mcp-barter     - 通过 MCP 验证 proposal-in-PM 主链路（buyer 发起，seller 接受）"
	@echo "  PLAYWRIGHT_MANUAL_EXTENSION_DIRS=/abs/ext make pw-manual - 加载 unpacked 扩展"
	@echo "  playwright test     - 直接运行 Playwright"
	@echo "  playwright test --headed - 直接运行 headed Playwright"
	@echo "  playwright codegen  - 直接运行 Playwright codegen"
	@echo ""
	@echo "  === 低层入口 ==="
	@echo "  make site           - 创建 Flarum 站点"
	@echo "  make enable         - 链接并启用扩展"
	@echo "  make disable        - 禁用并移除扩展"
	@echo "  make migrate        - 运行新增迁移"
	@echo "  make locale-status  - 查看站点 default_locale 与扩展 locale 文件"
	@echo "  make locale-set-en  - 将站点默认语言切回 English"
	@echo "  make locale-set-zh-hans - 将站点默认语言切到 zh-hans（扩展中文可用）"
	@echo "  make locale-set-zh-Hans - 将站点默认语言切到 zh-Hans（兼容完整语言包）"
	@echo "  make test           - 运行 PHPUnit"
	@echo ""
