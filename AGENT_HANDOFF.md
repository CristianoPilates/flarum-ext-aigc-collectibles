# Agent Handoff

这份文档给后续 Code agent / 新会话 Codex 用。

它只记录当前现场，不负责解释完整架构。完整规则看 `CLAUDE.md`，具体操作看 `Manul.md`。

## 1. 用户偏好与硬约束

- 全程优先中文，少噪音，先给证据再下结论
- 不接管用户日常 Chrome
- 只用 headed Chromium + 真实 MetaMask unpacked extension
- 保留一份手工 seed profile：
  `.devenv/state/playwright-profile`
- `mcp-headed` 每次启动前都要从 seed profile 派生新的 runtime profile：
  `.devenv/state/playwright-mcp-profile`
- 钱包导入由用户手工完成，只写入 seed profile，之后长期复用
- 不要自动化导入钱包
- mint 必须是用户主动动作
- 证明 NFT 已经成立时，不要只依赖 MetaMask NFT 列表
- 每条 commit 之前，都要先用 `mcp-headed` 做实际验收

不要把用户密码写进仓库。

## 2. 当前现场快照

截至 2026-04-02：

- 当前工作分支：
  `main`
- 已落在 `HEAD` 的最近提交：
  `17754c9`
- 当前工作树是 dirty 的
- dirty 内容主要包括：
  - 一条尚未提交的“静态质量/LSP 清理 + MCP 验收稳定化”候选：
    前端 TS typings 收敛
    一批 PHP 低风险 docblock / 泛型 / 返回类型修复
    `mcp-headed` 改为每次从 seed profile 复制 fresh runtime profile
  - 少量与本轮任务无关的既有脏文件，例如：
    `contracts/CollectibleNFT.sol`
    `.codex`

## 3. 已确认的事实

### 3.1 浏览器 / MetaMask

- `pw-manual` 继续复用手工 seed profile：
  `.devenv/state/playwright-profile`
- `mcp-headed` / `make mcp` 不再直接复用 seed profile
- 当前稳定方案是：
  每次启动前从
  `.devenv/state/playwright-profile`
  复制出新的运行时 profile：
  `.devenv/state/playwright-mcp-profile`
- 这样保留 MetaMask 已导入状态，同时避免旧 tab / beforeunload / session restore 残留污染下一次验收
- 真实 MetaMask unpacked extension 目录：
  `e2e/support/nkbihfbeogaeaoehlefnkodbefgpgknn`
- mock `window.ethereum` 已移除
- GUI 路径已经证明能看到真实 MetaMask provider
- 钱包导入策略已经定为人工一次导入，长期复用 seed profile

### 3.2 最小 NFT 路径

真实 GUI + 真实 MetaMask + 真实应用最小路径已经跑通过：

- 登录
- 开盒生成藏品
- 钱包绑定
- 手动 mint

已经确认过的一组样本：

- `Collectible #43`
- `tokenId = 8`
- `metadataCid = QmcLVCHYo8eBbwHGbCYuUkavmZz9XG9gphNjocoEN9nq6L`
- `ipfsCid = QmcDxqofG2Wpou9HG7WbsmaatZgCcGrxk9aKc5uvAiwXTx`
- `chainId = 31337`
- `contract = 0x5FbDB2315678afecb367f032d93F642f64180aa3`

注意：

- 当前 mint 交易不是每次都由 MetaMask 弹窗确认
- 当前实现是后端 minter wallet 发链上 mint，归属到用户绑定地址
- 所以“MetaMask 已接入”和“链上 NFT 已存在”都是真的
- 但“用户每次在 MetaMask 里亲手确认 mint”不是当前产品实现

### 3.3 Showcase / Proof

这两条旧结论仍成立：

- reply showcase 第一版已落地
- proof modal 第一版已落地

proof 当前已有：

- `GET /api/collectibles/{id}/proof`
- `CollectibleDetailModal` 里的 `View Proof` 按钮
- `CollectibleProofModal`
- `make mcp-proof`
- `scripts/playwright/mcp-cli/mcp-validate-proof.cjs`

### 3.4 Private Messages / Phase 1 CTA

本轮已经把 Phase 1 主链打通，并已提交。

当前代码状态：

- 新增：
  [privateMessages.ts](/home/donk/development/flarum-ext-aigc-collectibles/js/src/forum/utils/privateMessages.ts)
- 展柜 CTA 已改成：
  - 展柜卡片点击：打开藏品详情
  - 独立按钮：`Message Owner`
- 藏品详情主 CTA 已从旧 `TradeRequestModal` 改成私信入口
- 展柜布局已回到 post/reply 右侧：
  `CollectibleShowcasePost`
- CTA 视觉已改成：
  默认隐藏，hover 时从卡片底边浮出

最关键的修法：

- 不再自己手搓 `MessageComposer` chunk loader
- 改为复用 `flarum-messages` 官方注入到
  `UserControls.userControls(user)` 里的
  `sendMessage` control 的 `onclick`
- 这样 CTA 私信路径与官方用户卡片“Send Message”路径完全一致

headed MCP 真实验收结论：

- discussion/reply 页右侧展柜可见
- hover 后 CTA 出现
- 点击 `Message Owner` 后，私信 composer 正常出现
- buyer 发送后会跳到：
  `/messages/dialog/2`
- 调试时确认过 composer 状态：
  - `position = normal`
  - `mounted = true`
  - `visible = true`
  - body component 是 `MessageComposer`

注意：

- `.Composer` 容器本身偶尔有可见性过渡抖动
- 但 `.TextEditor-editor` 已经真实可见
- smoke / 验收应以 editor 可见为准，不要只盯 `.Composer`

与 Phase 1 同轮收口的站点问题：

- `/messages/dialog/:id` 原先会报：
  `Resource [tags] not found.`
- 原因是站点已安装 `flarum/tags` 包，但没启用，而且数据库也没跑 tags migration
- 已直接把 `flarum-tags` 加入 `settings.extensions_enabled`
- 已执行：
  `php flarum migrate`
  跑完 tags 相关 migration
- 之后 headed 诊断确认：
  - `GET /api/dialog-messages...` 不再 404
  - dialog 页面已能正常显示消息流正文

当前判断：

- Phase 1 主链已提交：
  `0d079a0`
  `Implement showcase CTA private messaging flow`
- 第二条小修已提交：
  `c9474da`
  `Fix trade modal close and owner profile links`
- 第三条 locale 已提交：
  `83c8ab4`
  `Add locale management scripts and zh-Hans alias`
- 第四条 blind box 已提交：
  `4ae5cf3`
  `Add blind box inventory and staged opening flow`
- Phase 2 已提交：
  `17754c9`
  `Add collectible context to private messages`

### 3.5 Trade / barter

必须明确：

- 用户已同意不要继续把旧 `Trade` 模型硬补成 barter
- 当前只是在 Phase 1 中绕开旧 `Trade`，把 CTA 先接到私信
- `Trade/barter` 领域模型重做仍未开始

现状：

- 旧 `TradeRequestModal` 还在代码里
- 旧 `Trade` 仍然是“盲盒数量换单个 collectible”的窄模型
- 用户后续报告的旧 bug
  `set offer` 后 modal 无法正常关闭
  已在 `c9474da` 修复并提交

这说明：

- 旧 Trade 路径还要做最小 bugfix
- 但真正的 barter 重做必须单列为后续阶段工程，不能与 Phase 1 混做

### 3.6 Blind box / rarity / phrase pool

- 后端已经存在真正的 `BlindBox` 模型与 `/blindboxes` API
- 当前前端主交互仍以“余额 + 开盒 modal”为主
- 现有流程是：
  先 appraise，再自动继续 open
- 用户当前明确要求改成：
  先 appraise 出 budget，再由用户决定是否 open

用户新增产品要求还包括：

- blind box 不应只作为货币余额
- 需要独立的 BlindBox 页面
- 要显示：
  `type`、`seed`、`status`、`budget`
- 同 type 统一精美外观
- 不同 status 不同外观
- 未鉴定前 budget 显示 `???`
- 还要展示该 type 可抽取的 phrase pool categories

截至当前会话结束前，第四条提交候选已基本做完，但尚未 commit：

- 新增前端 BlindBox model：
  [BlindBox.ts](/home/donk/development/flarum-ext-aigc-collectibles/js/src/forum/models/BlindBox.ts)
- 新增用户盲盒页：
  [UserBlindBoxesPage.tsx](/home/donk/development/flarum-ext-aigc-collectibles/js/src/forum/components/UserBlindBoxesPage.tsx)
- 新增盲盒库存组件：
  [BlindBoxInventory.tsx](/home/donk/development/flarum-ext-aigc-collectibles/js/src/forum/components/BlindBoxInventory.tsx)
- `forum.tsx` 已注册：
  - `blindboxes` store model
  - `/u/:username/blindboxes`
  - header 盲盒入口
  - profile nav `盲盒`
- `BlindBoxOpener.tsx` 已改成两段式：
  - `先鉴定盲盒`
  - 停在 `已鉴定`
  - 再 `开盒生成藏品`
- `BlindBoxResource.php` 已新增：
  - `drawRules`
  - `trade_reward -> checkin_reward` fallback
- `resources/less/forum.less` 已新增独立 blind box 页面与卡片样式
- `resources/locale/en.yml`
  `resources/locale/zh-hans.yml`
  `resources/locale/zh-Hans.yml`
  已补 blind box 页面文案

本轮最关键的真实 bug 已定位并修掉：

- `BlindBoxOpener` 之前把 Flarum model 的 `id()` 错当成属性 `id`
- 导致 appraise 请求 URL 被拼成函数源码字符串
- headed 验收里实际表现为：
  `MethodNotAllowedException: POST`
- 现已改为显式取 `id()` / `id`

本轮 headed MCP 实机验收结论：

- buyer 访问：
  `/u/buyer/blindboxes`
- 可见 3 个独立盲盒卡片
- 初始状态：
  - `未鉴定`
  - budget 为 `???`
- 点击 `先鉴定盲盒` 后：
  - 约 10 秒 PoW 鉴定完成
  - modal 停在 `已鉴定`
  - budget 显示真实值，例如 `20`
  - 不会自动开盒
- 再点击 `开盒生成藏品` 后：
  - 成功 reveal
  - 当前一次实测产物：
    `Collectible #48`
  - 库存从 `3` 变成 `2`

因此，第 4 条 blind box 提交已经完成。当前下一步不再是提交 blind box，而是先收口一条“静态质量提升”提交。

2026-04-02 更新：

- Phase 2 已提交：
  `17754c9`
  `Add collectible context to private messages`
- 当前真正的下一步是：
  先提交“静态质量 / LSP 清理 + MCP 运行 profile 稳定化”
  然后再进入 `Phase 3` 的 barter 模型重做

### 3.7 i18n / 语言

扩展自己的本地化已经注册：

- [extend.php](/home/donk/development/flarum-ext-aigc-collectibles/extend.php)
  中已有：
  `new Extend\Locales(__DIR__.'/resources/locale')`

这意味着：

- `resources/locale/en.yml`
- `resources/locale/zh-hans.yml`

已经是“实装状态”。

如果站点 UI 目前仍只有 English，问题不在本扩展 locale 注册，而在站点层语言包/默认语言：

- 需要安装并启用站点简中语言扩展，例如：
  `flarum-lang/chinese-simplified`
- 然后在 Flarum 后台切换默认显示语言

但 2026-03-30 当前已确认一个关键兼容性问题：

- `flarum-lang/chinese-simplified` 公开仓库 `composer.json`
  仍声明 `require flarum/core ^1.0.0`
- 当前站点是 `Flarum 2.0.0-beta.8`
- 所以不能把“安装这个包”当作当前站点的稳方案

当前已完成的运行态工作：

- 新增：
  `resources/locale/zh-Hans.yml`
  作为 `zh-hans.yml` 的兼容别名版本
- 新增：
  `scripts/forum/locale-status.php`
  `scripts/forum/set-default-locale.php`
- 新增 Makefile 入口：
  - `make locale-status`
  - `make locale-set-en`
  - `make locale-set-zh-hans`
  - `make locale-set-zh-Hans`

当前 headed 实验结论：

- 把站点 `default_locale` 切到 `zh-Hans` 后
  扩展自己的中文文案已经生效
- 证据包括：
  - `签到`
  - `藏品`
  - `交易`
  - `Web3 钱包`
  - `断开钱包`
- 但 Flarum core 仍大量显示英文：
  - `Settings`
  - `Security`
  - `Collectibles`
  - `All / Common / Rare / Epic / Legendary`

结论：

- 本扩展 `resources/locale/zh-hans.yml`
  现在已经是“实装并可被站点消费”的
- 真正缺的是“兼容 Flarum 2 的完整站点简中语言包”

### 3.8 前端技术栈 / 诊断 / 静态质量

用户提到“前端似乎没用到 Mithril.js”，这里要明确：

- Flarum forum 前端本来就是 Mithril 体系
- 当前代码大量使用：
  - `flarum/common/Component`
  - `flarum/forum/app`
  - `m.redraw()`
  - Flarum 的 TSX 组件模式

所以答案是：

- 用了 Mithril
- 只是大部分代码写成 TSX，而不是手写 `m(...)`

诊断现状：

- 已有：
  `js/tsconfig.json`
- 可先跑：
  `npm run check-typings`
- 当前仓库没有现成：
  `phpstan.neon`
- `vendor/bin` 下也没有现成 `phpstan`
- 实际可用的 PHP 静态分析入口是：
  `phpactor worse:analyse src --format=json`

2026-03-31 当前已完成一轮较大但低风险的静态质量清理，尚未提交：

- 前端：
  `npm run check-typings` 已通过
- 前端：
  `npm run build` 已通过
- 新增：
  [js/src/global.d.ts](/home/donk/development/flarum-ext-aigc-collectibles/js/src/global.d.ts)
- 已修正一批 TS 问题，包括：
  - Flarum / Mithril 全局 typings
  - `Modal` attrs 泛型
  - `m.route` / `m.redraw` / TSX 推断问题
  - `user.attribute(...)` 若干不安全调用
  - `TradePanel` payload 归一化
  - `WalletConnector` attrs typing
  - `replaceAll` 兼容性问题
- PHP 侧已补一批低风险类型信息，包括：
  - policy 显式返回类型
  - Eloquent relation 泛型 docblock
  - repository / service / interface / command 参数与返回注释

当前 `phpactor worse:analyse src --format=json` 剩余项，全部属于框架静态分析误报或高风险改法，不应为了“清零”而改运行时逻辑：

- `Endpoint::defaultSort`
- `Endpoint::paginate`
- `Context::getActor`
- `Builder::whereVisibleTo`

结论：

- 这一轮静态质量已经收敛到合理边界
- 不应再为追求“分析器全绿”而改 Flarum 运行时用法

2026-04-02 新增确认：

- 这批质量修正里，曾有人把若干 Flarum 组件改成运行时
  `import m from 'mithril'`
- 这在 Flarum extension 里会引入与全局 `m` 不同的 Mithril runtime
- 已实际触发过 `/u/:username/collectibles` 页面崩溃：
  `TypeError: Cannot read properties of undefined (reading 'toLowerCase')`
- 根因是 `m.route.param('username')` 来自错误的 Mithril 实例
- 结论：
  - 运行时 `m` 必须继续使用 Flarum 全局实例
  - 允许 `import type Mithril from 'mithril'`
  - 不要再引入运行时 `import m from 'mithril'`

## 4. 当前 dirty 文件

当前可见 dirty 文件大致分两类：

应该进入下一条“质量 / MCP 稳定化”提交的文件：

- `Makefile`
- `devenv.nix`
- `e2e/app.e2e.spec.cjs`
- `js/src/global.d.ts`
- `js/src/forum/components/BlindBoxOpener.tsx`
- `js/src/forum/components/CollectibleDetailModal.tsx`
- `js/src/forum/components/CollectibleProofModal.tsx`
- `js/src/forum/components/PostCollectibleBadge.tsx`
- `js/src/forum/components/TradePanel.tsx`
- `js/src/forum/components/TradeRequestModal.tsx`
- `js/src/forum/components/UserCollectiblesPage.tsx`
- `js/src/forum/components/WalletConnector.tsx`
- `js/src/forum/utils/ipfs.ts`
- `js/src/forum/utils/notifications.ts`
- `js/tsconfig.json`
- `resources/less/forum.less`
- `scripts/playwright/mcp-cli/README.md`
- `scripts/playwright/mcp-cli/mcp-validate-proof.cjs`
- `scripts/playwright/mcp-cli/mcp-validate-showcase.cjs`
- `scripts/playwright/mcp.config.json`
- `src/Access/CheckinPolicy.php`
- `src/Access/CollectiblePolicy.php`
- `src/Access/TradePolicy.php`
- `src/Api/Resource/BlindBoxResource.php`
- `src/Api/UserResourceFields.php`
- `src/Command/BindWallet.php`
- `src/Command/CreateTrade.php`
- `src/Model/BlindBox.php`
- `src/Model/Collectible.php`
- `src/Model/CollectibleEvent.php`
- `src/Model/Trade.php`
- `src/Model/Web3Account.php`
- `src/Repository/CheckinRepository.php`
- `src/Repository/CollectibleRepository.php`
- `src/Repository/TradeRepository.php`
- `src/Search/CollectibleSearcher.php`
- `src/Service/CollectibleProofService.php`
- `src/Service/Contracts/CollectibleProofServiceInterface.php`
- `src/Service/Contracts/IPFSServiceInterface.php`
- `src/Service/Contracts/WalletVerificationServiceInterface.php`
- `src/Service/NftMintingService.php`
- `src/StateMachine/StateMachineConfig.php`
- `tests/integration/api/BlindBoxLifecycleTest.php`

明确不要进这条提交的文件：

- `contracts/CollectibleNFT.sol`
- `.codex`
- `scripts/playwright/mcp-cli/debug-cta-state.cjs`
- `scripts/playwright/mcp-cli/validate-showcase-context.cjs`

说明：

- `contracts/CollectibleNFT.sol` 不是本轮主任务改动，应避免误回滚
- 其余 keep 文件主要属于“前端 TS + PHP 类型注释清理 + MCP 验收稳定化”这一条尚未提交的质量改动

## 5. 已跑过的验证

本轮已确认：

- `npm run build` in `js/`
  通过
- `npm run check-typings` in `js/`
  通过
- `phpactor worse:analyse src --format=json`
  已只剩 Flarum/Tobyz 静态分析误报
- headed MCP：
  展柜 CTA -> 私信 composer
  通过
- headed MCP：
  buyer 发送后跳转 `/messages/dialog/2`
  通过
- headed MCP：
  `/messages/dialog/2` 消息流正文渲染
  通过
- headed MCP：
  `set offer` 成功后旧 `TradeRequestModal` 自动关闭
  通过
- headed MCP：
  detail modal 中 owner 显示为 `admin`，链接跳转 `/u/admin`
  通过
- headed MCP：
  proof modal 中 owner 显示为 `admin`，链接跳转 `/u/admin`
  通过
- headed MCP：
  站点切到 `zh-Hans` 后，扩展 UI 文案显示中文
  通过
- headed MCP：
  当前只有扩展文案切成中文，Flarum core 仍大量英文
  已确认

2026-04-02 新增确认：

- 论坛 API 当前健康：
  `curl -fsS http://127.0.0.1:8080/api`
  可正常返回
- 旧的共享长寿命 MCP 运行态是导致验收不稳定的主要来源：
  - 旧 tab 残留
  - beforeunload dialog 残留
  - session restore 残留
- 当前稳定方案已经落地：
  - `pw-manual` 使用 seed profile
  - `mcp-headed` / `make mcp` 每次先复制 fresh runtime profile
- 实测最稳的真实 GUI 验收路径是：
  使用隔离端口启动单次 MCP 运行，例如 `8932`
- 已通过的隔离 headed 验收包括：
  - showcase 展柜可见且详情可打开
  - proof modal 可打开并显示预期 section
  - `/u/admin/collectibles` 页面基础渲染正常
- 因此：
  不能再把 `8931` 共享态偶发失败直接理解为业务功能回归
  提交前应优先使用 fresh runtime profile 的实际 GUI 验收

注意：

- 真正要进入每条 commit 之前，必须先走 `mcp-headed` 的实际 GUI 验收
- 不是只靠 headless smoke

## 6. 当前执行顺序

这是当前认可的顺序，后续会话不要改丢：

1. 先收口 Phase 1：
   `展柜 CTA -> 私信`
   已提交
2. 然后修一批短平快问题：
   - 旧 `TradeRequestModal` 关闭 bug
   - detail / proof 的 owner 展示与 profile 跳转
   已提交
3. 然后做语言切换与运行态 locale 治理
   已提交
4. 然后把 BlindBox 从余额重构为一等资产并做独立页面
   已提交
5. 然后先做一条静态质量 / LSP 清理提交
   当前进行中，尚未提交
6. 这条提交同时收口 MCP fresh runtime profile 稳定化
7. 最后才进入 `Phase 3`：
   重做 barter / Trade 领域模型并挂入私信线程

关键提醒：

- `展柜 CTA` 已完成并提交，不要回退到旧 trade CTA 路径
- `Trade 模型重做` 也还没完成，只是明确延期到后续阶段，不是取消

## 7. commit 规则

用户最新明确要求：

- 每条 commit 之前，都要用 `mcp-headed` 实际验收

推荐执行模板：

1. 启动：
   `make mcp-headed`
2. 用 MCP / headed Chromium 走实际用户路径
3. 确认通过后再做该 commit
4. commit 之间不要跳过 GUI 验收

2026-04-02 当前额外提醒：

- 不要并行跑多个 MCP CLI 脚本
- `8931` 共享态仍可能偶发不稳
- 提交前优先使用 fresh runtime profile 的真实 GUI 验收
- `/tmp/*.cjs` 只作为临时排障辅助，不要把它们提交进仓库

## 8. 已经明确不要再做的事

- 不要自动导入钱包
- 不要把 MetaMask gallery 当作唯一真相
- 不要把开盒和 mint 再次耦合回去
- 不要接管用户日常 Chrome
- 不要把旧 `Trade` 硬补成目标 barter 模型
- 不要让 `mcp-headed` 直接复用 seed profile

## 9. 接手时先看哪些文件

- [Manul.md](/home/donk/development/flarum-ext-aigc-collectibles/Manul.md)
- [CLAUDE.md](/home/donk/development/flarum-ext-aigc-collectibles/CLAUDE.md)
- [ROADMAP.md](/home/donk/development/flarum-ext-aigc-collectibles/ROADMAP.md)
- [AGENT_HANDOFF.md](/home/donk/development/flarum-ext-aigc-collectibles/AGENT_HANDOFF.md)
- [js/src/forum/utils/privateMessages.ts](/home/donk/development/flarum-ext-aigc-collectibles/js/src/forum/utils/privateMessages.ts)
- [js/src/forum.tsx](/home/donk/development/flarum-ext-aigc-collectibles/js/src/forum.tsx)
- [js/src/forum/components/CollectibleDetailModal.tsx](/home/donk/development/flarum-ext-aigc-collectibles/js/src/forum/components/CollectibleDetailModal.tsx)
- [js/src/forum/components/CollectibleProofModal.tsx](/home/donk/development/flarum-ext-aigc-collectibles/js/src/forum/components/CollectibleProofModal.tsx)
- [js/src/forum/components/TradeRequestModal.tsx](/home/donk/development/flarum-ext-aigc-collectibles/js/src/forum/components/TradeRequestModal.tsx)
- [scripts/forum/locale-status.php](/home/donk/development/flarum-ext-aigc-collectibles/scripts/forum/locale-status.php)
- [scripts/forum/set-default-locale.php](/home/donk/development/flarum-ext-aigc-collectibles/scripts/forum/set-default-locale.php)
- [resources/locale/zh-Hans.yml](/home/donk/development/flarum-ext-aigc-collectibles/resources/locale/zh-Hans.yml)
- [js/src/forum/components/BlindBoxOpener.tsx](/home/donk/development/flarum-ext-aigc-collectibles/js/src/forum/components/BlindBoxOpener.tsx)
- [js/src/global.d.ts](/home/donk/development/flarum-ext-aigc-collectibles/js/src/global.d.ts)
- [src/Api/Resource/BlindBoxResource.php](/home/donk/development/flarum-ext-aigc-collectibles/src/Api/Resource/BlindBoxResource.php)
- [src/Model/BlindBox.php](/home/donk/development/flarum-ext-aigc-collectibles/src/Model/BlindBox.php)
- [src/Model/Trade.php](/home/donk/development/flarum-ext-aigc-collectibles/src/Model/Trade.php)
- [src/Service/BlindBoxService.php](/home/donk/development/flarum-ext-aigc-collectibles/src/Service/BlindBoxService.php)
- [src/Access/CollectiblePolicy.php](/home/donk/development/flarum-ext-aigc-collectibles/src/Access/CollectiblePolicy.php)
- [src/Access/TradePolicy.php](/home/donk/development/flarum-ext-aigc-collectibles/src/Access/TradePolicy.php)
- [src/Service/CollectibleProofService.php](/home/donk/development/flarum-ext-aigc-collectibles/src/Service/CollectibleProofService.php)
- [scripts/playwright/mcp.config.json](/home/donk/development/flarum-ext-aigc-collectibles/scripts/playwright/mcp.config.json)
- [scripts/playwright/mcp-cli/mcp-client.cjs](/home/donk/development/flarum-ext-aigc-collectibles/scripts/playwright/mcp-cli/mcp-client.cjs)

## 10. 一句话总结

项目当前不是“所有东西都没做”，而是已经进入一个明确但未收口的过渡态：

- proof 已落地
- showcase 已落地
- Phase 1 的 CTA -> 私信 已提交
- 第二条小修已提交
- 第三条 locale 已提交
- 第四条 BlindBox 资产化与两段式开盒已提交
- Phase 2 的私信藏品上下文也已提交
- 当前进行中的不是新业务功能，而是一条静态质量 / LSP 清理 + MCP 稳定化提交
- 当前最大的下一阶段任务是 `Phase 3` 的 barter 重构
- Trade/barter 重做仍在后续阶段

下一位 agent 不要偏航。先完成这条静态质量提交，然后进入 Phase 3 的 barter 设计与实现。

## 11. 2026-04-01 本次恢复记录

### 11.1 MCP 验收链路

- `.env` 里的 `PLAYWRIGHT_MCP_URL` 之前写成了 `http://127.0.0.1:8931/mcp`
- 当前 `@playwright/mcp` 运行时实际要求客户端通过 `http://localhost:8931/mcp` 接入
- 现已修正为：
  `PLAYWRIGHT_MCP_URL=http://localhost:8931/mcp`
- 同时保留服务端绑定：
  `scripts/playwright/mcp.config.json` / `devenv.nix`
  仍使用 `127.0.0.1` 监听
- 结论：
  当前可用组合是：
  - 服务监听：`127.0.0.1:8931`
  - 客户端 URL：`http://localhost:8931/mcp`

2026-04-02 补充：

- 仅修正 `localhost` / `127.0.0.1` 还不够
- 真正稳定下来的关键是：
  `Makefile` / `devenv.nix` / `scripts/playwright/mcp.config.json`
  现在统一使用：
  - seed profile：
    `.devenv/state/playwright-profile`
  - runtime profile：
    `.devenv/state/playwright-mcp-profile`
- `mcp-headed` 每次启动前都会重建 runtime profile，
  并删除 `Singleton*`、`Current Session`、`Last Session`、`Sessions/`
  等残留状态文件
- 实际上，当前最可靠的验收方式是单次隔离端口运行，例如：
  `8932`

### 11.2 私信 composer 崩溃根因与修复

- 真实 headed 验收先复现到：
  - 官方 `/messages` 新建私信也失败
  - `.Composer normal visible` 已存在，但 `.TextEditor-editor` 没建出来
  - 报错：
    `Cannot read properties of undefined (reading 'append')`
- 这说明问题不是展柜 CTA 独有，而是 `TextEditor.onbuild()` 执行时
  `.TextEditor-editorContainer` 偶发还没准备好
- 已在
  [js/src/forum.tsx](/home/donk/development/flarum-ext-aigc-collectibles/js/src/forum.tsx)
  加入一个非常窄的前端防御：
  - override `flarum/common/components/TextEditor.onbuild`
  - 容器缺失时最多重试 20 次
  - 容器就绪后再执行原始 `onbuild`
  - 不改 vendor，不改消息业务逻辑

### 11.3 本次真实 headed 验收结果

- 官方消息页：
  `node scripts/playwright/mcp-cli/mcp-validate-messages.cjs`
  已通过
- 验收结论：
  - buyer 可在 `/messages` 新建私信
  - composer 输入框可见
  - 消息可发送
  - seller 可看到新消息
- 展柜 CTA：
  `node scripts/playwright/mcp-cli/validate-showcase-cta-click.cjs`
  已通过
- 验收结论：
  - discussion / reply 里的展柜 CTA 可点击
  - composer 正常显示
  - `.TextEditor-editor` 可见
  - `composerDisplay` 为 `block`

### 11.4 当前残余注意事项

- `debug-cta-state.cjs` 这类脚本比验收脚本更脆，
  在 shared MCP / shared browser context 下偶发 `fetch failed`
- 不要并行跑多个 MCP CLI 脚本
- 必须串行
- 推荐顺序：
  1. `make mcp-headed`
  2. 等待 `playwright-mcp ready`
  3. 串行运行单个 `node scripts/playwright/mcp-cli/*.cjs`
- 目前前台 `make mcp-headed` 比后台 `devenv` 进程更稳定，提交前验收优先用前台模式

### 11.5 Phase 2 已实现：私信附带藏品上下文

用户已明确同意直接进入 Phase 2：

- 目标不是重做旧 `Trade`
- 而是在 Phase 1 的“展柜 -> 私信主人”基础上，
  让私信 composer 自动附带当前藏品上下文

本次已完成实现：

- 在
  [js/src/forum/utils/privateMessages.ts](/home/donk/development/flarum-ext-aigc-collectibles/js/src/forum/utils/privateMessages.ts)
  新增：
  - `PrivateMessageCollectibleContext`
  - `buildCollectibleMessageContent(...)`
  - `currentDiscussionTitle()`
  - 更稳的 `waitForComposerReady()`
  - `applyInitialContent(...)`
- `applyInitialContent(...)` 不只写
  `app.composer.fields.content(...)`
  也会同步把内容写入底层 editor DOM，
  避免 composer 已显示但 textarea 没更新
- 在
  [js/src/forum/components/PostCollectibleShowcase.tsx](/home/donk/development/flarum-ext-aigc-collectibles/js/src/forum/components/PostCollectibleShowcase.tsx)
  接入展柜 CTA 的上下文预填
- 在
  [js/src/forum/components/CollectibleDetailModal.tsx](/home/donk/development/flarum-ext-aigc-collectibles/js/src/forum/components/CollectibleDetailModal.tsx)
  接入详情弹窗 CTA 的上下文预填
- 在 locale 中补齐上下文字段：
  - [resources/locale/en.yml](/home/donk/development/flarum-ext-aigc-collectibles/resources/locale/en.yml)
  - [resources/locale/zh-Hans.yml](/home/donk/development/flarum-ext-aigc-collectibles/resources/locale/zh-Hans.yml)

当前 PM 预填内容包含：

- 藏品名
- 稀有度
- Token ID
- 来源讨论帖标题
- 来源帖子 URL

### 11.6 Phase 2 真实 headed 验收结果

最终稳定使用的验收脚本是：

- [scripts/playwright/mcp-cli/validate-showcase-cta-click.cjs](/home/donk/development/flarum-ext-aigc-collectibles/scripts/playwright/mcp-cli/validate-showcase-cta-click.cjs)

该脚本本次已扩充：

- 不只检查 CTA 点击后 composer 是否出现
- 还额外抓取 `editorValue`
  直接验证 textarea 实际预填内容

真实 headed 验收已通过，关键结果：

- 展柜 CTA 可点击
- 私信 composer 正常出现
- `.TextEditor-editor` 存在
- `composerDisplay` 为 `block`
- `editorValue` 已正确写入上下文

本次实际捕获到的 `editorValue` 为：

- `我想聊聊这件藏品：`
- 空行
- `藏品：Collectible #45`
- `稀有度：普通`
- `Token ID：#9`
- `讨论帖：Showcase CTA Click Validation 1775053602677`
- `来源帖子：http://127.0.0.1:8080/d/48-showcase-cta-click-validation-1775053602677`

这说明 Phase 2 当前至少在 showcase CTA 路径上，
已经实现“带藏品上下文进入私信 composer”。

### 11.7 测试与提交边界提醒

- PHP 集成测试：
  [tests/integration/api/BlindBoxLifecycleTest.php](/home/donk/development/flarum-ext-aigc-collectibles/tests/integration/api/BlindBoxLifecycleTest.php)
  已同步修正为动态校验 phrase pool，
  不再依赖陈旧硬编码词表
- `vendor/bin/phpunit -c tests/phpunit.integration.xml`
  已通过：
  `48 / 48`
- 当前工作树里仍有大量与静态质量 / BlindBox / 旧 Trade 相关的 dirty 改动
- 提交 Phase 2 时必须只挑这批相关文件，
  不要误把
  `contracts/CollectibleNFT.sol`
  等无关改动混进去

### 11.8 临时脚本说明

- [scripts/playwright/mcp-cli/validate-showcase-context.cjs](/home/donk/development/flarum-ext-aigc-collectibles/scripts/playwright/mcp-cli/validate-showcase-context.cjs)
  是本轮排障时加的辅助脚本
- 最终可靠验收并不是依赖它，而是依赖
  [scripts/playwright/mcp-cli/validate-showcase-cta-click.cjs](/home/donk/development/flarum-ext-aigc-collectibles/scripts/playwright/mcp-cli/validate-showcase-cta-click.cjs)
- 是否保留这个辅助脚本，可以在提交前再决定

## 12. 2026-04-02 当前提交前状态

- 当前 `HEAD`：
  `17754c9`
  `Add collectible context to private messages`
- 当前待提交批次不是 Phase 3 业务代码，
  而是“静态质量 / LSP 清理 + MCP runtime profile 稳定化”
- 这批里已经确认的真实回归并已修复：
  - 若干组件一度被改成运行时 `import m from 'mithril'`
  - 这会破坏 Flarum 全局 Mithril 实例一致性
  - 已实际导致 `/u/:username/collectibles` 页面崩溃
  - 当前修法是移除运行时 `m` 导入，只保留 type-only 导入
- 提交前真实验收应优先覆盖：
  - showcase 展柜打开与详情
  - proof modal
  - `/u/:username/collectibles`
- 当前完成这条提交后，下一阶段就是 `Phase 3`：
  重新设计挂在私信线程里的 barter 模型
