# Manual

这份文档是使用说明，不是架构文档。

目标很简单：

- 回到仓库时，能快速判断该走哪条 lane
- 知道哪些状态是持久化、会被复用的
- 知道真实 MetaMask / NFT 最小路径怎么跑
- 知道遇到问题先查哪里，不要越查越乱

---

## 0. 核心规则

和 Playwright / MetaMask / NFT 相关的现场，现在只遵守这几条硬规则：

1. `pw-manual` 和 `mcp-headed` 复用同一份 profile：
   `.devenv/state/playwright-profile`
2. 真实 NFT 路径已经切到真实 MetaMask，不再依赖历史遗留的 mock `window.ethereum`
3. 不接管日常 Chrome，只用 headed Chromium
4. 钱包导入优先手动做一次，然后长期复用这份 shared profile
5. 真正要验证“像样的 NFT 路径”，优先走 `make mcp-headed`，不要只看 headless smoke

额外记住一个固定目录：

- MetaMask unpacked extension：
  `e2e/support/nkbihfbeogaeaoehlefnkodbefgpgknn`

---

## 1. 先判断该走哪条 lane

如果你的问题是这个，就走对应命令：

- 我要看基础回归还通不通：
  `make pw-smoke`
- 我要亲手打开浏览器、导入钱包、看扩展有没有真进来：
  `make pw-manual`
- 我要让 agent 驱动真实 GUI Chromium + 真实 MetaMask：
  `make mcp-headed`
- 我要检查当前 shared profile 到底是不是“真 MetaMask + 真钱包账户”：
  `make mcp-state`
- 我要跑登录 -> 生成藏品 -> 绑定钱包 -> mint 的最小闭环：
  `METAMASK_PASSWORD='<wallet-password>' make mcp-minimal-nft`

一句话判断：

- `pw-smoke` 偏回归
- `pw-manual` 偏人工
- `mcp-headed` 偏真实 GUI 自动化

---

## 2. 当前唯一可信的状态布局

现在最重要的是别再把 profile 搞分叉。

当前和这件事相关的状态目录：

- shared Chromium profile：
  `.devenv/state/playwright-profile`
- MCP 输出目录：
  `.devenv/state/playwright-mcp-output`
- unpacked MetaMask 扩展目录：
  `e2e/support/nkbihfbeogaeaoehlefnkodbefgpgknn`
- MCP 配置文件：
  `scripts/playwright/mcp.config.json`

约束：

- `pw-manual` 用 shared profile
- `mcp-headed` 也用同一个 shared profile
- 不再保留或使用独立的 `playwright-mcp-profile`
- 不再注入 fake `window.ethereum`

如果你看到“手工浏览器里钱包正常，但 MCP 浏览器里还在 onboarding”，第一怀疑对象就是：

- profile 用错了
- MCP 没复用 shared profile
- 启动参数又被改回去了

---

## 3. 最常用命令

高频命令只需要记住这些：

```bash
make dev
make status
make pw-smoke
make pw-manual
make mcp-headed
make mcp-state
METAMASK_PASSWORD='<wallet-password>' make mcp-minimal-nft
make down
```

含义：

- `make dev`
  启动完整开发编排
- `make status`
  看 mysql / ipfs / anvil / akashgen / forum / frontend / playwright-mcp 是否正常
- `make pw-smoke`
  跑 headless smoke 回归
- `make pw-manual`
  打开可见 Chromium，手工处理 shared profile
- `make mcp-headed`
  启动 headed Playwright MCP，驱动同一份 shared profile
- `mcp-state`
  检查 MetaMask provider、扩展页、地址和应用绑定状态
- `mcp-minimal-nft`
  跑最小 NFT 闭环
- `make down`
  结束当前运行现场

低频初始化命令：

```bash
make init
make init-site
make init-chain
make init-test-data
make reset-state
```

它们只在下面几种情况用：

- 第一次建环境
- 你明确要重建 `.devenv/state`
- 站点、链或测试数据被清空了

---

## 4. 标准启动顺序

如果你只是想在一台正常机器上验证真实最小 NFT 路径，推荐顺序固定如下：

1. 进入项目目录

```bash
cd /home/donk/development/flarum-ext-aigc-collectibles
```

2. 进入 devenv shell

```bash
devenv shell
```

3. 启动开发编排

```bash
make dev
```

4. 看一次健康状态

```bash
make status
```

5. 如果这是第一次用这份 shared profile，先手工打开浏览器导入钱包

```bash
make pw-manual
```

6. 启动 headed MCP

```bash
make mcp-headed
```

7. 检查 shared profile 是否真有钱包状态

```bash
make mcp-state
```

8. 运行最小 NFT 路径

```bash
METAMASK_PASSWORD='<wallet-password>' make mcp-minimal-nft
```

9. 收尾

```bash
make down
```

如果只是第一次手动导入钱包，步骤 6 和 8 可以先不做。

---

## 5. Lane 一：Automated Smoke

### 5.1 目标

这条 lane 回答的问题是：

> 基础回归还通不通？

特点：

- headless
- 偏 deterministic
- 不做真实 MetaMask GUI 验证
- 不应该承担“真实钱包交互”验收职责

### 5.2 使用方式

```bash
make pw-smoke
```

### 5.3 什么时候它不够

如果你关心的是这些问题，那只跑 `pw-smoke` 不够：

- 扩展是否真的进了 Chromium
- MetaMask 页面是否真的出现了
- 真签名 / 真确认有没有发生
- mint 之前用户可见路径是否自然

---

## 6. Lane 二：Manual Exploratory

### 6.1 目标

这条 lane 回答的问题是：

> 我能不能用一份可见、持久化、真实的 Chromium profile 手工操作？

### 6.2 使用方式

```bash
make pw-manual
```

它会：

1. 复用 `.devenv/state/playwright-profile`
2. 打开 headed Chromium
3. 加载 unpacked MetaMask extension
4. 保留浏览器本地状态，供下次复用

### 6.3 适用场景

- 第一次导入 MetaMask 钱包
- 手工确认扩展是否真的加载
- 手点真实业务流
- 手工解锁钱包
- 保留登录态和扩展状态

### 6.4 它和 MCP 的关系

这不是另一条独立链路。

它实际上是 `mcp-headed` 的上游状态来源。

也就是说，在 `pw-manual` 里做的这些事，MCP 会复用：

- 钱包导入
- 钱包解锁
- MetaMask 扩展状态
- 浏览器 local storage / cookies / session

所以不要再做这些事：

- 给 MCP 指向另一套 profile
- 导入钱包到一个 profile，却用另一套 profile 跑自动化
- 把日常 Chrome profile 接进来

---

## 7. Lane 三：MCP Automation

### 7.1 目标

这条 lane 回答的问题是：

> agent 现在能不能驱动真实 GUI Chromium，并且让 MetaMask 真有反应？

### 7.2 使用方式

```bash
make mcp-headed
```

如果只是工具链检查，不需要真实 GUI，可用：

```bash
make mcp
```

这里要明确区分两层：

- 第一层是 `AI / Codex -> Playwright MCP server`
- 第二层是仓库内提供的 CLI 包装器，它们也是 MCP client，但只是为了复现、排障、回归和把常见操作固化成命令

所以这些 `mcp-cli/*.cjs` 不是另一套浏览器自动化体系。

它们本质上只是：

- 连接已经运行的 `http://localhost:8931/mcp`
- 调 MCP tools
- 把一段固定动作做成可重复执行的命令

如果你更习惯直接让 AI 驱动 MCP server，完全可以继续这样做。

这些 CLI 包装器存在的意义只有三个：

1. 便于复现
2. 便于排障
3. 便于把已经验证过的最小路径沉淀成仓库命令

对应关系应该这样理解：

- `make mcp-headed` 是 MCP 服务端入口
- `make mcp-state` / `make mcp-minimal-nft` 等是可选客户端入口

### 7.3 当前关键配置

配置文件：

- `scripts/playwright/mcp.config.json`

必须满足这些条件：

- `userDataDir` 指向 `.devenv/state/playwright-profile`
- `channel` 是 `chromium`
- 加载 unpacked MetaMask extension
- `sharedBrowserContext` 必须为 `true`

### 7.4 为什么 `sharedBrowserContext: true` 是硬要求

这是这次最关键的问题之一。

如果它是 `false`，会出现这种现象：

- MCP 小脚本跑完
- HTTP session 断开
- 对应 browser context 被回收
- headed Chromium 窗口也一起被关掉

用户体感就是：

- 浏览器刚打开就关
- 还没来得及在 MetaMask 输入内容，窗口就没了

现在已经收敛成：

- 一份 shared profile
- 一份 shared browser context
- 小脚本结束时不顺手把整个可见浏览器带死

---

## 8. 第一次接入真实 MetaMask 的正确做法

如果 shared profile 里还没有导入钱包，按这个顺序做：

1. 启动基础环境

```bash
make dev
```

2. 打开手工浏览器

```bash
make pw-manual
```

3. 在这个 Chromium 里手工完成 MetaMask onboarding

4. 导入你要测试的钱包

5. 设定钱包密码

6. 进入钱包主页，确认至少能看到账户页，而不是 onboarding 页

7. 关闭浏览器也没关系，profile 会保留

8. 后续所有 headed MCP 自动化都复用这份 shared profile

判断是否已经准备好：

- 浏览器里能看到 MetaMask 扩展页
- 扩展页不是 “Get started / Import wallet / Secret Recovery Phrase”
- provider `eth_accounts` 返回至少一个地址

如果你不确定，直接跑：

```bash
make mcp-state
```

理想结果至少应包含：

- `hasEthereum: true`
- `isMetaMask: true`
- `selectedAddress` 非空
- `walletAccounts` 非空

---

## 9. 最小 NFT 路径怎么跑

最小 NFT 路径只关注下面这几个业务节点：

1. 登录
2. 生成藏品
3. 绑定钱包
4. mint
5. 验证应用层结果已经“像样”

推荐命令：

```bash
METAMASK_PASSWORD='<wallet-password>' make mcp-minimal-nft
```

它实际会做：

- 登录 admin
- 确保有可开的 blind box
- 打开 blind box 并等待 collectible 进入 `completed`
- 如果应用层钱包已绑定，先解绑，再走一遍真实绑定流程
- 等待 MetaMask connect / sign / confirm
- 执行 mint
- 在应用层验证 `tokenId / metadataCid / ipfsCid / web3Address / canMint`

成功标准不是“脚本没报错”，而是至少满足：

- MetaMask 发生了用户可感知的动作
- collectible 最终拿到 `tokenId`
- 应用层 toast / API / 模型状态都能证明 mint 成功

本次已验证过一条真实成功样例，最终结果包括：

- `collectibleId: 33`
- `tokenId: 6`
- `metadataCid: QmanfEkV95HhK3VvZgG3KcY8ubfkj1TPP4KTk49Ng7tBBr`
- `ipfsCid: QmeJPGBdyC8aJpsjZ84vqHAud6F6J9Xs2tSPCKUT9At1SL`
- `web3Address: 0xabb6bc9ec4c33cf50b4013ff8bb3b4855e35168f`
- MetaMask 动作为 `button:has-text("Confirm")`

---

## 10. 当前和这件事最相关的脚本

```text
scripts/playwright/
├── mcp-cli/
│   ├── mcp-client.cjs
│   ├── mcp-debug-mint-state.cjs
│   ├── mcp-focus-metamask.cjs
│   ├── mcp-inspect-metamask-storage.cjs
│   ├── mcp-inspect-state.cjs
│   └── mcp-minimal-nft.cjs
├── mcp.config.json
└── prepare-data.php
```

职责：

- `mcp-cli/mcp-client.cjs`
  对 Playwright MCP HTTP transport 做连接、重连和顺序化调用封装
- `mcp-inspect-state.cjs`
  MCP CLI 包装器，检查当前 app + MetaMask + provider + 页面状态
- `mcp-minimal-nft.cjs`
  MCP CLI 包装器，跑真实最小 NFT 闭环
- `mcp-focus-metamask.cjs`
  MCP CLI 包装器，把 MetaMask 页带到前台，便于继续操作
- `mcp-inspect-metamask-storage.cjs`
  MCP CLI 包装器，看扩展存储状态，判断 profile 里有没有钱包痕迹
- `mcp-debug-mint-state.cjs`
  MCP CLI 包装器，排查某个 collectible 为什么没有 mint 按钮或状态不对
- `prepare-data.php`
  测试数据准备脚本，属于环境初始化，不属于 MCP 自动化层

建议按这个心智模型理解：

- `scripts/playwright/mcp.config.json`
  MCP 服务端配置
- `scripts/playwright/mcp-cli/*.cjs`
  MCP 客户端包装器
- `scripts/playwright/launch-manual.cjs`
  手工 Chromium 启动器
- `scripts/playwright/prepare-data.php`
  测试数据准备器

---

## 11. 典型问题与处理顺序

### 11.1 浏览器窗口自己关闭

先查：

- `scripts/playwright/mcp.config.json` 里 `sharedBrowserContext` 是否还是 `true`
- `make mcp-headed` 那个服务是否还活着

这类问题通常不是“浏览器抽风”，而是 MCP context 生命周期把窗口带死了。

### 11.2 MetaMask 还在 onboarding

如果看到：

- `Get started`
- `Import wallet`
- `Secret Recovery Phrase`

优先检查：

1. 是不是用了错误 profile
2. 钱包是不是只导入在别的浏览器里
3. shared profile 是否被重置过

### 11.3 provider 存在，但没有账户

这类状态比“完全没扩展”更容易误判。

重点看：

- `window.ethereum` 在不在
- `eth_accounts` 是不是空数组
- MetaMask 页面是不是还停在 `Your wallet is ready! / Open wallet`

这次真实踩到过这个坑，最后的处理是：

- 先点一次 `Open wallet`
- 再进入真正的钱包主页继续流程

### 11.4 collectible 长时间停在 `generating`

先别急着查 mint。

优先查：

```bash
make status
./scripts/health/probe.sh
```

如果 `akashgen` 是 `[down]`，生成链路就不会完成，后面的 mint UI 也自然不会正确出现。

### 11.5 详情弹窗没有 `Mint as NFT`

先分清是 UI 问题还是后端状态问题。

排查顺序：

1. 用 `mcp-debug-mint-state.cjs` 看 API / store / modal 三层状态
2. 看 collectible 是否已经 `completed`
3. 看是否已经有 `tokenId`
4. 看 `canMint` 是否只是前端字段没刷新

这次已经做过一个前端兜底：

- 优先使用 `canMint`
- 如果字段缺失，则回退到 `status === 'completed' && !tokenId`

### 11.6 MCP 小脚本偶发 `fetch failed` / `Session not found`

先别把锅甩给 profile。

这更像是 Playwright MCP HTTP session 本身不够稳，尤其是在短连接、小脚本频繁起停时。

现在的处理是：

- 用 `mcp-cli/mcp-client.cjs` 做自动重连
- 对 `Session not found` / `fetch failed` / `ECONNRESET` / `socket hang up` 做有限重试

如果偶发失败，先重跑一次小脚本；如果主链路一直失败，再查 MCP 服务端日志。

---

## 12. 这次修掉的历史遗留

这次最重要的清理，不只是“加脚本”，还包括把旧的脆弱逻辑删掉：

- 删除了历史 mock `window.ethereum` 注入逻辑
- 删除了依赖假钱包签名的 init script
- 删除了自动导入 MetaMask 钱包脚本
- 删除了不再需要的 `manual-extension-fixture`
- 把 MCP 默认 profile 改成 shared profile
- 修正了测试数据准备脚本，不再只改 `users.blind_box_count`

核心原则是：

- 能复用真实 profile 的，别再造第二份状态
- 能用真实扩展验证的，别再用 mock 掩盖问题
- 能手动完成一次的 onboarding，不要用脆弱脚本反复模拟

---

## 13. 不要做这些事

- 不要重新引入 fake `window.ethereum`
- 不要让 `mcp-headed` 指向另一套 profile
- 不要把日常 Chrome profile 接进来
- 不要只看 `users.blind_box_count` 就以为 blind box 真能开
- 不要在 collectible 还在 `generating` 时就判断 mint 逻辑坏了
- 不要把 `pw-smoke` 当成真实 MetaMask 验收

---

## 14. 初始化层说明

### 14.1 `make init`

完整低频初始化入口：

```bash
make init
```

等价于：

```bash
make init-site
make init-chain
make init-test-data
```

### 14.2 `make init-site`

作用：

- 安装论坛站点
- 启用扩展

### 14.3 `make init-chain`

作用：

- 等待 anvil 可用
- 部署或复用合约
- 把链配置写回论坛 settings

### 14.4 `make init-test-data`

作用：

- 确保 `admin` / `buyer` / `seller` 存在
- 密码统一为 `password`
- 确保盲盒计数和真实可开盲盒行一致

### 14.5 `make reset-state`

它清理的是 `.devenv/state` 的持久化状态，不是单纯停服务。

正确顺序：

```bash
make down
make reset-state
```

---

## 15. 一条可复用的最短流程

如果以后忘了，只记这一条：

```bash
make dev
make status
make pw-manual
make mcp-headed
make mcp-state
METAMASK_PASSWORD='<wallet-password>' make mcp-minimal-nft
make down
```

对应含义：

- `dev`
  基础服务先起来
- `status`
  先确认不是服务挂了
- `pw-manual`
  手工准备 shared profile
- `mcp-headed`
  让 agent 驱动真实 GUI 浏览器
- `mcp-state`
  看当前到底是不是“真 MetaMask + 真账户”
- `mcp-minimal-nft`
  跑最小 NFT 闭环
- `down`
  收尾
