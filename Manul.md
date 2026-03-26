# Manual

这份手册只做一件事：

把这个项目压成少量稳定入口，并且把“运行层”和“初始化层”彻底分开。

它不是命令百科。
它不是架构总览。
它是你回到这个项目时，可以直接照着执行的工作手册。

当前只有 3 条主闭环：

1. 开发闭环
2. Playwright 测试闭环
3. Playwright MCP 闭环

---

## 1. 先记住这几个入口

你平时主要只需要记住这 4 个命令：

```bash
make dev
make pw-test
make pw-mcp
make down
```

含义：

- `make dev`
  启动完整开发编排

- `make pw-test`
  启动完整 Playwright smoke 测试编排，并保留现场

- `make pw-mcp`
  前台启动 Playwright MCP

- `make down`
  清理当前项目的运行现场

除此之外，还有一层低频初始化命令：

```bash
make init
make init-site
make init-chain
make init-test-data
make reset-state
```

这层不属于“日常启动”。

它只在这些情况使用：

- 第一次建环境
- 完全重建之后
- MySQL / 链 / 站点状态被清空之后

也就是说：

- `dev / pw-test / pw-mcp` 是运行层
- `init-* / reset-state` 是持久化初始化层

---

## 2. 当前脚本分层

当前 `scripts/` 目录按职责拆成 5 层：

```text
scripts/
├── chain/
│   └── bootstrap.sh
├── forum/
│   ├── install.sh
│   └── configure.sh
├── health/
│   └── probe.sh
├── playwright/
│   └── prepare-data.php
├── runtime/
│   └── stack.sh
└── verify/
    └── run.sh
```

含义：

- `scripts/health/probe.sh`
  纯探针，负责测活，不修改状态

- `scripts/forum/install.sh`
  从无到有创建 Flarum 站点

- `scripts/forum/configure.sh`
  把已安装站点收口到开发配置

- `scripts/playwright/prepare-data.php`
  准备 Playwright smoke 所需 demo 用户和数据

- `scripts/runtime/stack.sh`
  管理当前项目的运行现场：列出、阻止重复启动、显式清理

- `scripts/chain/bootstrap.sh`
  部署或复用链上合约，并把配置回写到论坛 settings

- `scripts/verify/run.sh`
  做更高层的完整验收

这是当前最重要的结构边界：

- `status` 不做初始化
- `verify` 不承担底层探针职责
- `runtime` 只处理当前项目进程，不处理持久化状态
- `forum` 只负责站点
- `playwright` 只负责测试数据

---

## 3. 闭环一：开发闭环

### 3.1 目标

开发闭环的目标是：

- 论坛可访问
- 扩展已经启用
- 前端 watch 正在运行
- 你改代码后可以立即看到结果

它回答的问题是：

> 我现在能不能开始开发？

### 3.2 使用方式

进入项目目录：

```bash
cd /home/donk/development/flarum-ext-aigc-collectibles
```

进入 devenv shell：

```bash
devenv shell
```

启动完整开发编排：

```bash
make dev
```

这个命令会前台拉起：

- `mysql`
- `ipfs`
- `anvil`
- `akashgen`
- `forum`
- `frontend`

它不会自动做初始化。

所以第一次建环境或完全重建之后，你应该先执行：

```bash
make init
```

### 3.3 常见变体

如果你只想拉起站点本体：

```bash
make up-site
```

它会启动：

- `mysql`
- `forum`
- `frontend`

如果你只想拉起外部服务：

```bash
make up-external
```

它会启动：

- `ipfs`
- `anvil`
- `akashgen`

### 3.4 如何确认环境正常

执行：

```bash
make status
```

它会探测：

- `mysql`
- `ipfs`
- `anvil`
- `forum`
- `akashgen`
- `frontend`
- `playwright-mcp`

这是纯测活命令，不会修改状态。

补充两点：

- 如果你没有单独执行 `make pw-mcp`，那么 `playwright-mcp` 显示 `[down]` 是正常现象
- `make status` 只负责探测，不会因为某项是 `[down]` 自动失败

还有一条关键规则：

- 如果当前项目已经有旧的运行现场，新的启动命令会直接拒绝继续
- 这不是报错设计过度，而是为了避免同一份 `.devenv/state` 被重复占用

这里的“新的启动命令”包括：

- `make dev`
- `make up`
- `make up-site`
- `make up-external`
- `make up-mysql`
- `make up-ipfs`
- `make up-anvil`
- `make up-akashgen`
- `make pw-test`

这些命令都会先执行当前项目运行现场检查。

---

## 4. 闭环二：Playwright 测试闭环

### 4.1 目标

测试闭环的目标是：

- 拉起 smoke 所需依赖
- 完成站点、链和测试数据初始化
- 跑 `@smoke` 子集
- 保留失败现场

它回答的问题是：

> 核心路径现在还通不通？

### 4.2 使用方式

进入 shell：

```bash
devenv shell
```

运行：

```bash
make pw-test
```

这个命令会做这些事：

1. 拉起 `mysql/ipfs/anvil/akashgen`
2. 执行 `make init-site`
3. 拉起 `forum/frontend`
4. 执行 `make init-chain`
5. 执行 `make init-test-data`
6. 执行 `playwright test --grep @smoke`
7. 保留现场，供你继续排查或继续联调

### 4.3 为什么不自动清理

`pw-test` 不自动清理，是刻意设计的。

而且这里的“保留现场”不只发生在失败时。

即使 smoke 全部通过，它也会保留现场。

因为通过之后你通常仍然会继续看：

- forum 页面状态
- frontend watch 是否还活着
- 链和 API 是否仍然可用
- `.devenv/state/pw-test-*.log` 的进程输出

排查完成后，再执行：

```bash
make down
```

`make down` 会尝试：

- 停掉 `pw-test` 保留的后台进程
- 停掉当前项目目录对应的 `devenv` 进程

它不会清理别的项目目录下的运行现场。

如果你只是想看当前项目到底还残留了哪些运行现场，可以直接执行：

```bash
./scripts/runtime/stack.sh list
```

### 4.4 如果你只想重复跑 smoke

```bash
playwright test --grep @smoke
```

这条命令只负责执行测试，不负责准备依赖。

所以：

- 要完整闭环，用 `make pw-test`
- 环境已经准备好了，只想重复执行时，再直接跑 `playwright test --grep @smoke`

---

## 5. 闭环三：Playwright MCP 闭环

### 5.1 目标

这个闭环的目标是：

- 启动 Playwright MCP
- 确认 MCP 已经开始监听
- 保持进程存活

它回答的问题是：

> 我现在能不能把浏览器作为 MCP 服务来驱动？

### 5.2 使用方式

先确保 forum 已经起来，再执行：

```bash
make pw-mcp
```

它会：

1. 创建输出目录和 profile 目录
2. 清掉占用 MCP 端口的旧进程
3. 前台启动 `mcp-server-playwright`
4. 等待 `PLAYWRIGHT_MCP_URL` 可访问
5. 保持 MCP 进程继续运行

这是前台长驻任务，不会自己退出。

---

## 6. 初始化层怎么用

### 6.1 `make init`

完整低频初始化入口：

```bash
make init
```

它等价于：

```bash
make init-site
make init-chain
make init-test-data
```

如果你要清理持久化状态，而不是清理运行现场，用：

```bash
make reset-state
```

这条命令只清理 `.devenv/state` 里的持久化数据。

它不会自动帮你停进程。

而且如果当前项目运行现场还活着，它会直接拒绝执行。

所以正确顺序是：

```bash
make down
make reset-state
```

### 6.2 `make init-site`

作用：

- 安装论坛站点
- 同步 `config.php`
- 启用扩展

### 6.3 `make init-chain`

作用：

- 等待 anvil 可用
- 编译并部署或复用合约
- 把链配置写回论坛 settings

### 6.4 `make init-test-data`

作用：

- 确保 `admin`、`buyer`、`seller` 存在
- 确保密码统一为 `password`
- 确保测试所需盲盒数量和群组关系存在

这一步不属于“论坛安装”，它属于“测试数据准备”。

---

## 7. 完整验收

如果你要做更高层的完整验收：

```bash
make verify
```

它会执行：

- 严格进程探针
- `make init-chain`
- `make init-test-data`
- API 级验收
- `playwright test`

这是比 `pw-test` 更重的验收入口。

这里的“严格”意思是：

- `mysql / ipfs / anvil / forum / akashgen / frontend` 任一项没起来，`verify` 会直接失败
- `playwright-mcp` 仍然只是可选探针，不会阻塞 `verify`

---

## 8. 最短记忆版本

如果以后忘了，只记这些就够了：

```bash
make dev
make pw-test
make pw-mcp
make down
make init
make reset-state
```

对应含义：

- `dev`: 整套开发环境起来没有
- `pw-test`: smoke 路径还通不通
- `pw-mcp`: MCP 服务能不能接
- `down`: 当前项目现场看完后怎么清理
- `init`: 持久化初始化要不要重做
- `reset-state`: 持久化状态要不要整份清空
