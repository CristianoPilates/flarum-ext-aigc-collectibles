# Agent Handoff

这份文档给后续 Code agent / 新会话 Codex 用。

它只记录当前现场，不负责解释完整架构。完整规则看 `CLAUDE.md`，具体操作看 `Manul.md`，执行计划看 `ROADMAP.md`。

## 当前现场快照

截至 2026-04-15：

- 当前工作分支：`release/post-refactor-consolidation`
- HEAD commit：`17b13ea` `fix: blind box tabs now show status filter and sort by budget`
- PR：`https://github.com/CristianoPilates/flarum-ext-aigc-collectibles/pull/1`
- PR 状态：OPEN，待合并

## 本次发布内容（37 commits）

这是 v1.0.0 + v1.1.0 的发布提交。v1.0.0 (35 commits) 主要内容：

1. **Barter proposals in private message threads** — barter 已完全迁移到私信线程，old `Trade` 模型已退役
2. **Multi-asset barter** — 盲盒 + 藏品混合交换，双向多件打包
3. **Barter settlement** — 接受后自动结算资产转移
4. **Locale 管理** — `zh-Hans.yml` 为规范源，`zh-Hans` 为别名
5. **Blind box balance dictionary** — 按 type 分组的余额视图
6. **Blind box SVG 视觉** — `blindbox-unappraised.svg` / `blindbox-appraised.svg`
7. **Showcase panel** — reply 右侧展示，with fallback DB query

v1.1.0 (2 additional commits) 主要内容：

1. **Barter composer redesign** — composer 从内联双列资产 grid 改为摘要卡片 + config overlay 路线
2. **BarterConfigOverlay** — 全屏 overlay，4 tabs (mine collectibles/blind boxes, theirs collectibles/blind boxes)，搜索、稀有度筛选、分页
3. **Collectible inline rename** — detail modal 中可通过铅笔图标直接修改藏品名，PATCH 到 API
4. **Barter draft persistence** — 选中的资产每 500ms 自动保存到 localStorage，刷新页面或重开 composer 可恢复
5. **Unnamed collectibles banner** — overlay 的 mine-collectibles tab 在有未命名藏品时显示引导 banner

### 本次修复的 bug（最新 hotfix）

1. **Barter assets API 422 错误** — 私信 composer 中 barter 资产加载返回 "Thread ID is required"
   - 根因：前端发送 snake_case 参数名 (`thread_id`)，后端只读取 camelCase (`threadId`)
   - 修复：`ListBarterAssetsController.php` 现在同时支持两种命名格式

2. **翻译 key 缺失警告** — `core.forum.blind_box.inventory_title` 等 key 显示为原始 key 名
   - 根因：API 失败导致翻译资源未加载，Flarum 缓存需要刷新
   - 修复：执行 `make publish-site-runtime` 清理缓存并重新发布资源

3. **盲盒 tabs 显示为空** — "我的盲盒"与"对方盲盒"tabs 没有显示任何盲盒
   - 根因 A：`BarterConfigOverlay` 对所有 tabs 使用藏品 rarity filter，盲盒没有 `rarity` 字段，导致切换 tab 后盲盒被错误过滤
   - 修复：添加 `activeStatus` 字段，盲盒 tabs 显示 status filter (已鉴定/未鉴定)，排序按 budget 降序
   - 根因 B（发现于 2026-04-15）：`getActiveBucket()` 和 `renderGrid()` 方法中 `kind` 类型声明为 `'blindboxes'`（小写b），但 API 响应和 `BarterAssetBucket` 接口定义使用的属性名是 `blindBoxes`（大写B，camelCase）。JavaScript 访问 `bucket['blindboxes']` 返回 `undefined`。
   - 修复（2026-04-15）：将两处 `kind` 类型统一改为 `'blindBoxes'`，与 API 响应一致

4. **盲盒 tabs 展示为 rarity filter** — 切换到盲盒 tab 后，filter bar 仍显示 rarity 按钮而非 status filter
   - 根因：`filterAssets()` 依赖 `isBlindBoxTab` 判断，但 `activeStatus` 字段在切换 tab 时已重置，逻辑正确
   - 状态：已被根因 B 的修复解决 — 正确加载盲盒数据后，filter 逻辑自然正确

### 之前修复的 bug

1. **Blind box SVG 404** — SVG 从 `resources/images/` 迁到 `assets/images/`，Flarum `assets:publish` 链路修复
2. **Dead `STATUS_OPENED` UI** — 移除不可达展示逻辑
3. **Barter composer 输入区被挤压** — `BarterComposerPanel` 从 `headerItems` 注入改为 `view()` 块级注入
4. **BarterService transaction safety** — `rejectProposal()` 和 `cancelProposal()` 现在使用 `lockForUpdate()` + 事务包装，与 `acceptProposal()` 模式一致

## 遗留问题（PR body 已记录）

这些是发布后需处理的 hotfix，不阻止当前合并：

1. **`CollectibleEventResource` 无 scope/policy** — `src/Api/Resource/CollectibleEventResource.php` 需要加 scope 限制访问
2. **Settlement transaction split** — `BarterService::acceptProposal()` 用两个独立事务，settle 失败后资产已转移但状态 stuck
3. **PoW difficulty 无最低阈值** — `BlindBoxService::appraise()` 接受任意 client-supplied hash

## 测试状态

- Unit tests: 38/38 PASS
- Integration tests: 45/45 PASS (3 deprecations)
- TypeScript: PASS (`tsc noEmit`)
- Frontend build: PASS (webpack 5.105.4, 92.5 KiB forum.js)

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
