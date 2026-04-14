# Agent Handoff

这份文档给后续 Code agent / 新会话 Codex 用。

它只记录当前现场，不负责解释完整架构。完整规则看 `CLAUDE.md`，具体操作看 `Manul.md`，执行计划看 `ROADMAP.md`。

## 当前现场快照

截至 2026-04-14：

- 当前工作分支：`release/post-refactor-consolidation`
- HEAD commit：`5cfd32d` `docs: add CHANGELOG.md for v1.0.0`
- PR：`https://github.com/CristianoPilates/flarum-ext-aigc-collectibles/pull/1`
- PR 状态：OPEN，待合并

## 本次发布内容（36 commits）

这是 v1.0.0 的发布提交。主要内容：

1. **Barter proposals in private message threads** — barter 已完全迁移到私信线程，old `Trade` 模型已退役
2. **Multi-asset barter** — 盲盒 + 藏品混合交换，双向多件打包
3. **Barter settlement** — 接受后自动结算资产转移
4. **Locale 管理** — `zh-Hans.yml` 为规范源，`zh-Hans` 为别名
5. **Blind box balance dictionary** — 按 type 分组的余额视图
6. **Blind box SVG 视觉** — `blindbox-unappraised.svg` / `blindbox-appraised.svg`
7. **Showcase panel** — reply 右侧展示，with fallback DB query

### 本次修复的 bug

1. **Blind box SVG 404** — SVG 从 `resources/images/` 迁到 `assets/images/`，Flarum `assets:publish` 链路修复
2. **Dead `STATUS_OPENED` UI** — 移除不可达展示逻辑
3. **Barter composer 输入区被挤压** — `BarterComposerPanel` 从 `headerItems` 注入改为 `view()` 块级注入

## 遗留问题（PR body 已记录）

这些是发布后需处理的 hotfix，不阻止当前合并：

1. **`CollectibleEventResource` 无 scope/policy** — `src/Api/Resource/CollectibleEventResource.php` 需要加 scope 限制访问
2. **Settlement transaction split** — `BarterService::acceptProposal()` 用两个独立事务，settle 失败后资产已转移但状态 stuck
3. **PoW difficulty 无最低阈值** — `BlindBoxService::appraise()` 接受任意 client-supplied hash

## 测试状态

- Unit tests: 34/34 PASS
- Integration tests: 41/41 PASS (3 deprecations)
- TypeScript: PASS (`tsc noEmit`)
- Frontend build: PASS (webpack 5.105.4)

## 当前硬规则（不变）

- 只用 headed Chromium + 真实 MetaMask unpacked extension
- 保留一份手工 seed profile：`.devenv/state/playwright-profile`
- `mcp-headed` 每次从 seed profile 派生新 runtime profile
- mint 必须是用户主动动作
- 证明 NFT 成立时不要只依赖 MetaMask NFT 列表

## 不要做的事（不变）

- 不要自动导入钱包
- 不要把开盒和 mint 再次耦合
- 不要把旧 `Trade` 当运行时模型继续补功能
- 不要引入 mock `window.ethereum`
