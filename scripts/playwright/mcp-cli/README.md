# MCP CLI Wrappers

这个目录只放一类东西：

- 针对“已经运行中的 Playwright MCP server”的可选 CLI 包装器

它们不是第二套 Playwright 测试框架，也不是用来替代 AI 直接驱动 MCP。

它们的用途只有这些：

- 复现已经验证过的固定动作
- 排障
- 把常见 MCP 调用沉淀成可执行命令

分层：

- `scripts/playwright/mcp.config.json`
  MCP 服务端配置
- `scripts/playwright/launch-manual.cjs`
  手工 Chromium 启动器
- `scripts/playwright/prepare-data.php`
  初始化测试数据
- `scripts/playwright/mcp-cli/*.cjs`
  MCP 客户端包装器

推荐优先使用 `make` 入口，而不是直接记文件路径：

```bash
make mcp-headed
make mcp-state
METAMASK_PASSWORD='<wallet-password>' make mcp-minimal-nft
make mcp-debug-mint
make mcp-focus-metamask
make mcp-storage
make mcp-showcase
```
