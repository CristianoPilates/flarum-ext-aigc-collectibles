# Manual

这份文档只回答三件事：

- 环境怎么启动
- 真实 MetaMask / NFT / IPFS 怎么验收
- 出问题时先查哪里

它不是架构说明，也不是项目规划。那两件事分别看 `CLAUDE.md` 和 `ROADMAP.md`。

## 1. 文档地图

- `Manul.md`
  运行手册与验收手册
- `CLAUDE.md`
  架构约束、代码约束、测试约束
- `AGENT_HANDOFF.md`
  当前现场、已验证事实、接手注意事项
- `ROADMAP.md`
  当前唯一有效的执行计划
- `scripts/playwright/mcp-cli/README.md`
  Playwright MCP CLI 包装器的职责边界

## 2. 当前硬规则

围绕 Playwright / MetaMask / NFT，只遵守这些规则：

1. 只保留一份共享 Chromium profile：
   `.devenv/state/playwright-profile`
2. `pw-manual` 和 `mcp-headed` 必须复用这同一份 profile
3. 只用 headed Chromium，不接管用户日常 Chrome
4. 只用真实 MetaMask unpacked extension：
   `e2e/support/nkbihfbeogaeaoehlefnkodbefgpgknn`
5. 钱包导入只手工做一次，之后长期复用 shared profile
6. 不再注入 mock `window.ethereum`
7. mint 必须保持用户主动触发，不能在开盒后顺手自动 mint

不要把钱包密码写进仓库文件。

## 3. 关键状态与端口

### 3.1 状态目录

- shared profile:
  `.devenv/state/playwright-profile`
- Playwright MCP 输出:
  `.devenv/state/playwright-mcp-output`
- 普通 Playwright 工件:
  `.devenv/state/playwright`

### 3.2 服务端口

- Forum:
  `http://127.0.0.1:8080`
- Playwright MCP:
  `http://localhost:8931/mcp`
- Anvil:
  `http://127.0.0.1:8545`
- IPFS API:
  `http://127.0.0.1:5001`
- IPFS WebUI:
  `http://127.0.0.1:5001/webui`
- IPFS Gateway:
  `http://127.0.0.1:8888`
- AIGC service:
  `http://127.0.0.1:6571/health`

## 4. 最常用命令

```bash
make dev
make status
make down

make pw-smoke
make pw-manual
make mcp-headed
make mcp-state
make mcp-showcase
make mcp-proof

METAMASK_PASSWORD='<wallet-password>' make mcp-minimal-nft
```

含义：

- `make dev`
  启动 mysql / ipfs / anvil / akashgen / forum / frontend
- `make status`
  查看关键服务健康状态
- `make down`
  关闭当前编排
- `make pw-smoke`
  跑 headless 回归，不负责真实钱包 GUI 验收
- `make pw-manual`
  手工打开共享 profile，对 MetaMask 做人工操作
- `make mcp-headed`
  启动 headed Playwright MCP，驱动同一份 profile
- `make mcp-state`
  检查当前共享 profile 里的 MetaMask / provider / 地址状态
- `make mcp-showcase`
  验收 reply 右侧展柜
- `make mcp-proof`
  验收四层 proof modal
- `make mcp-minimal-nft`
  跑登录 -> 生成 -> 绑定钱包 -> mint 的最小闭环

## 5. 标准启动顺序

如果只是想在本机复用已有环境，推荐顺序固定如下：

1. 进入项目目录

```bash
cd /home/donk/development/flarum-ext-aigc-collectibles
```

2. 进入 devenv shell

```bash
devenv shell
```

3. 启动环境

```bash
make dev
```

4. 看一次健康状态

```bash
make status
```

5. 需要人工处理钱包时，用共享 profile 打开浏览器

```bash
make pw-manual
```

6. 需要 AI / CLI 驱动真实 GUI 时，启动 headed MCP

```bash
make mcp-headed
```

7. 验证 profile 里确实已经是“真 MetaMask + 真账户”

```bash
make mcp-state
```

8. 按需做功能验收

```bash
make mcp-showcase
make mcp-proof
METAMASK_PASSWORD='<wallet-password>' make mcp-minimal-nft
```

9. 收尾

```bash
make down
```

不要同时让两套 Chromium 进程争抢同一个 `userDataDir`。

## 6. 三条使用 lane

### 6.1 `pw-smoke`

回答的问题：

> 基础回归还通不通？

特点：

- headless
- 偏 deterministic
- 不负责证明 MetaMask GUI 真的工作

### 6.2 `pw-manual`

回答的问题：

> 我能不能亲手打开同一份持久化 profile，看到真实 MetaMask 并保留状态？

适合：

- 第一次导入钱包
- 手工解锁钱包
- 手工确认扩展是否真的加载
- 手工查看页面和存储状态

### 6.3 `mcp-headed`

回答的问题：

> agent / CLI 现在能不能驱动可见 Chromium，并让真实 MetaMask 路径起作用？

这是 GUI 自动化的主 lane。

## 7. 第一次准备 MetaMask

如果共享 profile 还是空的，按这套流程做一次即可：

1. `make dev`
2. `make pw-manual`
3. 在 Chromium 里完成 MetaMask onboarding
4. 手工导入钱包
5. 进入钱包主页，确认不是 onboarding 页
6. 关闭浏览器也没关系，profile 会保留
7. 之后改用 `make mcp-headed` + `make mcp-state`

理想的 `make mcp-state` 结果至少应体现：

- `hasEthereum: true`
- `isMetaMask: true`
- `selectedAddress` 非空
- `walletAccounts` 非空

如果 GUI 里又出现 onboarding，先怀疑 profile 没复用，而不是先怀疑钱包丢了。

## 8. 最小 NFT 路径

当前最小闭环只关注：

1. 登录
2. 开盒生成藏品
3. 绑定钱包
4. 用户主动 mint

运行命令：

```bash
METAMASK_PASSWORD='<wallet-password>' make mcp-minimal-nft
```

这条路径的目标不是做完整 NFT 产品化，而是证明：

- 应用层已经接入真实 MetaMask
- 已经能拿到真实钱包地址
- 已经能把 collectible 链接到链上 NFT
- 至少达到“MetaMask 会有反应，链上确实有 token”的最低门槛

## 9. Showcase 与 Proof 验收

### 9.1 Reply 右侧展柜

运行：

```bash
make mcp-showcase
```

期望：

- reply 页面右侧存在固定 showcase panel
- 点击 showcase 可打开 collectible detail modal
- 桌面端不遮挡正文

### 9.2 四层 NFT Proof

运行：

```bash
make mcp-proof
```

proof modal 当前验证四层证据：

1. `App Record`
2. `Chain Proof`
3. `Metadata JSON`
4. `Image Asset`

这比只盯着 MetaMask NFT 标签页更可靠，因为 MetaMask 对本地链、自定义网络和 IPFS NFT 展示一直不稳定。

## 10. IPFS 怎么看

这里最容易混淆的是三种入口：

- `5001`
  Kubo API
- `5001/webui`
  Kubo WebUI
- `8888/ipfs/<cid>`
  人类可直接查看内容的网关

验收时优先看：

1. 应用里的 `metadataCid` / `ipfsCid`
2. `http://127.0.0.1:8888/ipfs/<metadataCid>`
3. `http://127.0.0.1:8888/ipfs/<ipfsCid>`
4. `http://127.0.0.1:5001/webui`

注意：

- 当前上传是通过本地 Kubo API 完成
- WebUI 的 `Files` 为空，不代表内容没进 IPFS
- 如果内容没写入 MFS，WebUI “My Files” 就不会自动出现条目

## 11. 常见问题

### 11.1 手工浏览器正常，MCP 浏览器却在 onboarding

先查：

- 是否真的只剩 `.devenv/state/playwright-profile`
- `pw-manual` 和 `mcp-headed` 是否都指向同一目录
- 是否误开了另一套 profile

### 11.2 浏览器突然关闭

通常是：

- `mcp-headed` 进程退出了
- 共享 browser context 被关闭了
- MCP 服务被重启了

### 11.3 `Session not found` / `Target page, context or browser has been closed`

这是 Playwright MCP HTTP 会话在浏览器上下文失效后的典型症状。先重启：

```bash
make mcp-headed
```

再复测，不要先改业务逻辑。

### 11.4 `EPERM 127.0.0.1:8931` 或 `fetch failed`

这通常是 sandbox / 本地 socket 问题，不一定是业务问题。

### 11.5 integration test 数据库报错

先重建测试库：

```bash
php tests/integration/setup.php
```

## 12. 一句话总结

当前操作原则很简单：

- 只用一份 shared profile
- 只用真实 MetaMask
- GUI 验收优先走 `mcp-headed`
- MetaMask 不是唯一证据，proof modal + 链上 + metadata + IPFS 才是完整验收链
