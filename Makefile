# Runtime/project paths (SITE_DIR follows .env FLARUM_SITE_DIR by default)
SITE_DIR    ?= $(FLARUM_SITE_DIR)
EXT_DIR     := $(shell pwd)
STATE_DIR   := $(EXT_DIR)/.devenv/state
EXT_NAME    := donk/flarum-ext-aigc-collectibles
FLARUM_VER  := ^2.0.0
PLAYWRIGHT_MCP_PORT := $(shell printf '%s\n' "$(PLAYWRIGHT_MCP_URL)" | sed -n 's#.*:\([0-9][0-9]*\)/.*#\1#p')
PLAYWRIGHT_MCP_OUTPUT_DIR ?= $(STATE_DIR)/playwright-mcp-output
PLAYWRIGHT_MCP_USER_DATA_DIR ?= $(STATE_DIR)/playwright-mcp-profile
PW_TEST_EXTERNAL_PID_FILE ?= $(STATE_DIR)/pw-test-external.pid
PW_TEST_SITE_PID_FILE ?= $(STATE_DIR)/pw-test-site.pid
PW_TEST_EXTERNAL_LOG_FILE ?= $(STATE_DIR)/pw-test-external.log
PW_TEST_SITE_LOG_FILE ?= $(STATE_DIR)/pw-test-site.log

ifneq (,$(wildcard ./.env))
include .env
export
endif

ifeq ($(strip $(SITE_DIR)),)
$(error SITE_DIR is empty; set FLARUM_SITE_DIR in .env or export SITE_DIR)
endif

.PHONY: up down status dev pw-test pw-mcp \
        up-site up-external up-mysql up-ipfs up-anvil up-akashgen \
        init init-site init-chain init-test-data assert-mysql assert-no-pw-test-scene assert-runtime-clean verify reset-state \
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

init-site: assert-mysql site enable

init-chain:
	./scripts/chain/bootstrap.sh

init-test-data:
	php ./scripts/playwright/prepare-data.php

assert-mysql:
	@mysql \
		-u "$(DB_USERNAME)" \
		-p"$(DB_PASSWORD)" \
		-h "$(DB_HOST)" \
		-P "$(DB_PORT)" \
		-e "SELECT 1" >/dev/null 2>&1 \
	|| (echo "mysql is not running; start it with make up-site or make up-mysql" >&2; exit 1)

assert-runtime-clean:
	@./scripts/runtime/stack.sh check

assert-no-pw-test-scene:
	@set -eu; \
	for file in "$(PW_TEST_EXTERNAL_PID_FILE)" "$(PW_TEST_SITE_PID_FILE)"; do \
		if [ -f "$$file" ]; then \
			pid="$$(cat "$$file" 2>/dev/null || true)"; \
			if [ -n "$$pid" ] && kill -0 "$$pid" >/dev/null 2>&1; then \
				echo "retained pw-test scene is still running; inspect it or run make down" >&2; \
				exit 1; \
			fi; \
			rm -f "$$file"; \
		fi; \
	done

pw-test: assert-runtime-clean assert-no-pw-test-scene
	@set -eu; \
	mkdir -p "$(STATE_DIR)"; \
	trap 'status="$$?"; \
		echo "pw-test scene retained; use make down to clean up"; \
		exit "$$status"' INT TERM EXIT; \
	setsid devenv up ipfs anvil akashgen mysql >"$(PW_TEST_EXTERNAL_LOG_FILE)" 2>&1 < /dev/null & \
	ext_pid="$$!"; \
	printf '%s\n' "$$ext_pid" > "$(PW_TEST_EXTERNAL_PID_FILE)"; \
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
	setsid devenv up forum frontend >"$(PW_TEST_SITE_LOG_FILE)" 2>&1 < /dev/null & \
	site_pid="$$!"; \
	printf '%s\n' "$$site_pid" > "$(PW_TEST_SITE_PID_FILE)"; \
	for _ in $$(seq 1 60); do \
		if ./scripts/health/probe.sh | rg -q "^\[up\]\s+forum$$" \
		&& ./scripts/health/probe.sh | rg -q "^\[up\]\s+frontend$$"; then \
			break; \
		fi; \
		sleep 1; \
	done; \
	make init-chain; \
	make init-test-data; \
	playwright test --grep @smoke

pw-mcp:
	@mkdir -p "$(PLAYWRIGHT_MCP_OUTPUT_DIR)" "$(PLAYWRIGHT_MCP_USER_DATA_DIR)"
	@set -eu; \
	curl -fsS "$(FORUM_URL)" >/dev/null; \
	pid="$$(ss -ltnp '( sport = :$(PLAYWRIGHT_MCP_PORT) )' | sed -n 's/.*pid=\([0-9]\+\).*/\1/p' | head -n1)"; \
	if [ -n "$$pid" ]; then kill "$$pid" >/dev/null 2>&1 || true; fi; \
	trap 'status="$$?"; if [ -n "$${mcp_pid:-}" ]; then kill "$$mcp_pid" >/dev/null 2>&1 || true; wait "$$mcp_pid" 2>/dev/null || true; fi; exit "$$status"' INT TERM EXIT; \
	mcp-server-playwright \
	  --headless \
	  --no-sandbox \
	  --browser chromium \
	  --port "$(PLAYWRIGHT_MCP_PORT)" \
	  --user-data-dir "$(PLAYWRIGHT_MCP_USER_DATA_DIR)" \
	  --init-page "$(EXT_DIR)/e2e/support/playwright-mcp-init-page.ts" \
	  --init-script "$(EXT_DIR)/e2e/support/playwright-mcp-init-script.js" \
	  --output-dir "$(PLAYWRIGHT_MCP_OUTPUT_DIR)" \
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
	rm -rf .devenv/state/playwright-mcp-output
	rm -rf .devenv/state/playwright-mcp-profile
	rm -f .devenv/state/pw-test-external.pid
	rm -f .devenv/state/pw-test-site.pid
	rm -f .devenv/state/pw-test-external.log
	rm -f .devenv/state/pw-test-site.log
	rm -f .devenv/state/contract.env

help:
	@echo ""
	@echo "  === 三个闭环 ==="
	@echo "  make dev            - 完整开发编排：启动站点本体 + 外部服务 + frontend watch"
	@echo "  make pw-test        - Playwright 测试闭环：拉起测试依赖、初始化并跑 @smoke E2E 子集"
	@echo "  make pw-mcp         - Playwright MCP 闭环：前台启动 MCP，探针通过后保持运行"
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
	@echo "  make init-site      - 低频初始化：建站、同步配置、启用扩展"
	@echo "  make init-chain     - 低频初始化：部署/复用合约并写回论坛设置"
	@echo "  make init-test-data - 低频初始化：准备 Playwright 测试数据"
	@echo "  make verify         - 跑完整验收流程"
	@echo "  make reset-state    - 清理 .devenv/state 持久化状态（需先 make down）"
	@echo "  playwright test     - 直接运行 Playwright"
	@echo "  playwright test --headed - 直接运行 headed Playwright"
	@echo "  playwright codegen  - 直接运行 Playwright codegen"
	@echo ""
	@echo "  === 低层入口 ==="
	@echo "  make site           - 创建 Flarum 站点"
	@echo "  make enable         - 链接并启用扩展"
	@echo "  make disable        - 禁用并移除扩展"
	@echo "  make migrate        - 运行新增迁移"
	@echo "  make test           - 运行 PHPUnit"
	@echo ""
