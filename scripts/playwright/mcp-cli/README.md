# Playwright MCP CLI Wrappers

这个目录只放一类东西：

- 针对“已经运行中的 Playwright MCP server”的薄客户端包装器

它们不是第二套 Playwright 测试框架，也不是为了替代 AI 直接驱动 MCP。

## 1. 职责边界

主链路仍然是：

`AI / Codex -> Playwright MCP server -> headed Chromium`

这里的 `*.cjs` 只是把一段已经验证过的 MCP 调用，固化成可重复执行的命令。

它们适合做三件事：

1. 复现固定动作
2. 排障
3. 回归验收

不适合做的事：

- 取代 AI 实时驱动
- 再造一套独立浏览器自动化体系
- 绕开 `make mcp-headed` 直接自己拉浏览器

## 2. 依赖关系

先启动服务端：

```bash
make mcp-headed
```

再运行这些包装器：

```bash
make mcp-state
make mcp-showcase
make mcp-proof
make mcp-messages
METAMASK_PASSWORD='<wallet-password>' make mcp-minimal-nft
```

也可以直接用 `node` 执行：

```bash
node scripts/playwright/mcp-cli/mcp-inspect-state.cjs
node scripts/playwright/mcp-cli/mcp-validate-showcase.cjs
node scripts/playwright/mcp-cli/mcp-validate-proof.cjs
```

前提不变：

- `http://localhost:8931/mcp` 已经在监听
- 手工 seed profile 已准备好

当前 profile 约定：

- `playwright-profile`
  只给 `make pw-manual` 用
  用来长期保留 MetaMask 导入/解锁后的持久化状态
- `playwright-mcp-profile`
  只给 `make mcp` / `make mcp-headed` 用
  每次启动前都会从 `playwright-profile` 复制一份新的运行副本

这样做的原因：

- 保留钱包状态
- 避免旧 tab / beforeunload dialog / session restore 污染下一次 MCP 验收

## 3. 当前脚本清单

- `mcp-inspect-state.cjs`
  检查 MetaMask/provider/profile 状态
- `mcp-minimal-nft.cjs`
  跑最小 NFT 闭环
- `mcp-debug-mint-state.cjs`
  排查 mint 前后状态
- `mcp-focus-metamask.cjs`
  聚焦 MetaMask 页面
- `mcp-inspect-metamask-storage.cjs`
  检查 MetaMask 存储
- `mcp-validate-showcase.cjs`
  验收 reply 右侧展柜
- `mcp-validate-proof.cjs`
  验收四层 proof modal
- `mcp-validate-messages.cjs`
  验收 buyer -> seller 私信链路
- `mcp-client.cjs`
  公共 MCP HTTP client

## 4. 为什么保留这些脚本

它们的价值不是“多写了一层代码”，而是把已经跑通过的调查与验收动作沉淀下来：

- 新会话里可以直接复现
- 排障时不用每次从零手敲 MCP 请求
- 可以挂在 `make` 入口下形成稳定习惯

如果只是临时探索页面，直接让 AI 驱动 MCP 更合适。
