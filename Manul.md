# Manual

这份手册描述当前项目收缩后的真实开发边界。

原则：

- `Makefile` 只负责高层编排
- 前端开发与构建使用 `npm run ...`
- Playwright 直接使用 `playwright ...`
- `status` 只做检查，不修改系统状态
- 区块链准备动作集中到 `make chain-ready`
- 只使用两个数据库：`flarum` 和 `flarum_test`
- 前端开发模式固定为：`npm run dev` + Flarum `debug=true`
- `.env` 只保留项目配置；`devenv.nix` 只保留 Nix/runtime 派生变量

## 1. 先理解几个边界

### `forum` 与 `frontend` 的区别

项目里有两个不同进程：

- `forum`
- `frontend`

`forum` 是 PHP 内置服务器，负责提供 Flarum 站点。

`frontend` 是前端 watch 进程，负责在你修改 `js/` 后重新编译前端资源。

所以：

- PHP 代码不需要 HMR
- 前端资源需要 `webpack --watch`
- 这里不是 webpack dev server / browser HMR
- 这里是“修改源码 -> 重新生成 `js/dist/*.js` -> Flarum 下次请求读取新文件”

Flarum 官方文档给出的标准扩展前端脚本就是：

```json
"dev": "webpack --mode development --watch",
"build": "webpack --mode production"
```

并且官方文档明确写了：

- `npm run dev` 会“compile ... into the `js/dist/forum.js` file, and keep watching for changes to the source files”
- 扩展通过 `extend.php` 的 `->js(__DIR__.'/js/dist/forum.js')` / `->js(__DIR__.'/js/dist/admin.js')` 注册产物
- 开发扩展时应当开启 debug mode，这样 “Flarum recompiles assets automatically, so you don't have to manually clear the cache every time you make a change to your extension JavaScript.”

这意味着开发时你不需要担心“旧缓存卡住前端改动”这个问题，前提是：

- `npm run dev` 正在运行
- Flarum 站点处于 debug mode

如果这两个条件成立，前端开发的社区标准做法就是：

```bash
make up-forum
```

这个命令现在还会确保站点 `config.php` 处于开发模式：

- `debug = true`
- `url = $FORUM_URL`

或者你自己分别管理：

```bash
devenv up -d forum
cd js && npm run dev
```

这里的关键不是 HMR，而是：

- watch 持续写入 `js/dist/admin.js` 和 `js/dist/forum.js`
- Flarum 扩展入口直接引用这两个文件
- debug mode 下 Flarum 会强制重新提交前端资产，并给聚合资产 URL 带 revision 参数，避免你反复手动清缓存

### Playwright MCP 的边界

项目里已经有 `processes.playwright-mcp`，所以 Playwright MCP 应该只由本项目管理。

如果你在 `~/.codex/config.toml` 里还有全局：

```toml
[mcp_servers.playwright]
command = "mcp-server-playwright"
args = ["--headless"]
```

那通常是多余的。  
项目内已经定义了：

- `--port`
- `--user-data-dir`
- `--init-page`
- `--init-script`
- `--output-dir`

全局那条配置既重复，又缺少项目需要的初始化参数。

## 2. 当前保留的命令

### 进程编排

```bash
make up
make status
make up-mysql
make up-ipfs
make up-anvil
make up-akashgen
make up-forum
make up-playwright
```

### 业务编排

```bash
make chain-ready
make seed-demo
make verify
make reset
```

### Flarum / 扩展

```bash
make site
make enable
make disable
make migrate
make migrate-reset
make test
```

### 直接使用，不再包一层 Makefile

```bash
playwright test
playwright test --headed
playwright codegen
npm run dev
npm run build
```

## 3. 首次进入项目怎么做

```bash
cd /home/donk/development/flarum-ext-aigc-collectibles
devenv shell
make site
make enable
make up
make status
make chain-ready
make seed-demo
```

完成后你应该具备：

- Flarum 站点已存在
- 扩展已链接并启用
- mysql/ipfs/anvil/forum/frontend/akashgen/playwright-mcp 已启动
- 合约已部署或确认可用
- 合约配置已同步到 Flarum
- demo 用户已恢复

## 4. 日常开发流程

### 改 PHP / 后端逻辑

```bash
devenv shell
make up
make status
make test
```

如果后端改动影响链配置或业务流程：

```bash
make chain-ready
make seed-demo
playwright test
```

### 改前端逻辑

```bash
devenv shell
make up-forum
npm run dev
```

说明：

- `make up-forum` 会把论坛和前端 watch 拉起来
- 如果你已经用 Overseer 或其他工具单独管理 `frontend`，那 `npm run dev` 也可以自己控制

### 跑 Playwright

```bash
devenv shell
playwright test
```

如果要肉眼看浏览器：

```bash
playwright test --headed
```

如果要录制交互：

```bash
playwright codegen
```

## 5. 每个高层命令的真实含义

### `make up`

```bash
make up
```

作用：

- 启动整套 `devenv` 进程

它不负责：

- 创建站点
- 启用扩展
- 部署合约
- 写入 demo 数据

所以它只是“把系统拉起来”，不是“把系统准备完毕”。

### `make status`

```bash
make status
```

作用：

- 只做检查
- 不修改状态

它检查：

- mysql
- ipfs
- anvil
- forum
- akashgen
- playwright-mcp

### `make chain-ready`

```bash
make chain-ready
```

作用：

- 运行 `scripts/ensure-contract.sh`
- 运行 `scripts/sync-contract-settings.sh`

这是现在唯一的链上准备入口。

### `make seed-demo`

```bash
make seed-demo
```

作用：

- 恢复 demo 用户和业务初始数据

### `make verify`

```bash
make verify
```

作用：

- 跑一次完整验收流程

它当前会做：

1. `status`
2. `chain-ready`
3. `seed-demo`
4. API smoke
5. `playwright test`

所以它不是“轻量检查”，而是“完整跑一轮”。

### `make reset`

```bash
make reset
```

作用：

- 删除 `.devenv/state` 里容易污染下一轮测试的状态

它当前会清掉：

- anvil state
- ipfs state
- playwright output
- playwright-mcp output
- playwright-mcp profile
- contract.env

这是“回归下一轮干净测试”的高层命令。

## 6. 测试后，怎么回到下一轮干净状态

### 轻量回归

适用：

- 你只想继续下一轮测试
- 不想重置太多状态

```bash
make seed-demo
make chain-ready
```

### 标准回归

适用：

- 你怀疑链状态或 profile 被污染

```bash
make reset
make up
make chain-ready
make seed-demo
```

### 站点级回归

适用：

- 你怀疑 Flarum 站点本身坏了

```bash
make disable
make site
make enable
make up
make chain-ready
make seed-demo
```

## 7. 关于数据库策略

当前策略是只保留两个数据库：

- `flarum`
- `flarum_test`

这意味着：

- 开发环境使用 `flarum`
- integration test 使用 `flarum_test`
- 不再保留第三个项目专用测试库

这样做的理由是：

- 开发数据和 integration test 数据仍然隔离
- 数据库数量控制在两个，复杂度比三库低
- 这比“单库同时承担开发与 integration test”更安全

这是一种更平衡的简化方案。

## 8. 你下一步怎么试

建议你按这个顺序亲自走一遍：

```bash
devenv shell
make site
make enable
make up
make status
make chain-ready
make seed-demo
playwright test
make verify
make reset
```

试完后，你再决定：

- `up-mysql/up-ipfs/up-anvil/...` 是否还要保留
- `verify` 这个高层命令是否还值得保留
- `migrate-reset` 是否应该继续暴露
