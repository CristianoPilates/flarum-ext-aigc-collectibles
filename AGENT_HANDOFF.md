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
  `.devenv/state/playwright-mcp-runtime/profile.*`
- 钱包导入由用户手工完成，只写入 seed profile，之后长期复用
- 不要自动化导入钱包
- mint 必须是用户主动动作
- 证明 NFT 已经成立时，不要只依赖 MetaMask NFT 列表
- 每条 commit 之前，都要先用 `mcp-headed` 做实际验收

不要把用户密码写进仓库。

## 2. 当前现场快照

截至 2026-04-03：

- 当前工作分支：
  `main`
- 已落在 `HEAD` 的最近提交：
  `fae0a87`
- 当前正在准备的新提交：
  `refactor: retire legacy trade runtime path`
- 当前工作树内容只应包含这一条提交相关改动：
  - 删除旧 `Trade` 的运行时入口
  - 删除旧 `Trade` 对应测试
  - 保留历史数据库表与 `trade_id` 字段，不在本条提交物理删表

补充：

- 本轮尚有未提交前端回归改动，主题不是“加功能”，而是“收口 / 回归 / 收缩”
- 已把私信线程中的协商区改成消息流上方的横向 banner，而不是右侧 rail
- 用户已明确确认：
  - 横向 banner 需要横向滚动
  - 不需要垂直方向滚动条
  - 协商区不应过宽、喧宾夺主
- 已修掉一个真实前端回归：
  - 原先协商区和 composer 中出现 `藏品 ,#47`
  - 现已恢复为 `藏品 #47`
- 原因不是业务数据脏，而是前端把 `translator.trans(...)` 的结果直接 `String(...)`，把格式化结果串坏了
- 当前修法：
  - [collectibles.ts](/home/donk/development/flarum-ext-aigc-collectibles/js/src/forum/utils/collectibles.ts) 改用 `extractText(...)`
  - 不再对翻译结果直接做 `String(...)`
- 本轮 headed MCP 串行验收已再次确认：
  - `BarterThreadPanel` 仍挂在 `.DialogSection-streamWrap` 内
  - 协商 composer 仍能正常打开
  - 文案已是 `藏品 #47`
  - 验收必须串行，不要并行跑多个 MCP wrapper

## 3. 已确认的事实

### 3.1 浏览器 / MetaMask

- `pw-manual` 继续复用手工 seed profile：
  `.devenv/state/playwright-profile`
- `mcp-headed` / `make mcp` 不再直接复用 seed profile
- 当前稳定方案是：
  每次启动前从
  `.devenv/state/playwright-profile`
  复制出新的运行时 profile：
  `.devenv/state/playwright-mcp-runtime/profile.*`
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
- Phase 1 / Phase 2 已经绕开旧 `Trade`，把 CTA 先接到私信并附带藏品上下文
- Phase 3 已提交第一阶段，方向已从旧 modal 改成“proposal 挂在私信线程里”

现状：

- 前端主路径已经不再使用旧 `Trade`
- 当前 barter 主路径是：
  私信线程内 `BarterProposal` / `BarterProposalItem`
- 已提交：
  `410d5c6`
  `feat: move barter proposals into PM threads`
- 已真实验收通过：
  - buyer 在私信线程内发起 proposal
  - seller 接受 proposal
  - 双方资产完成交换
  - seller 若失去正在展示的藏品，其 showcase 会被自动清空

本次新变化：

- 旧 `Trade` 的运行时后端链路正在被退役
- 本条提交只删“仍然活着的运行时代码”，不碰历史表结构

已删除或正在删除的运行时入口：

- `extend.php`
  - `TradeResource`
  - `User` 上的 `tradesInitiated` / `tradesReceived`
  - `TradePolicy` 注册
- `CollectibleServiceProvider`
  - `TradeServiceInterface -> TradeService`
  - `StateMachineConfig::trade()`
- `StateMachineConfig::trade()`
- `CollectibleResource` 的 `canTrade` / `trades`
- `CollectibleEventResource` 的 `trade`
- `Collectible` 的 `trades()`
- `CollectibleEvent` 的 `trade()`
- 整套旧文件：
  - `TradeResource`
  - `TradePolicy`
  - `TradeService`
  - `TradeServiceInterface`
  - `TradeRepository`
  - `TradeValidator`
  - `AcceptTrade*`
  - `RejectTrade*`
  - `CancelTrade*`
  - `CreateTrade*`
  - `TradeCreated`
  - `TradeCompleted`
  - `Model\Trade`
  - 3 个旧 `Trade` 测试文件

刻意保留的历史兼容层：

- `trades` 表迁移仍保留
- `collectible_events.trade_id` 字段仍保留
- `trade_reward` blind box 类型仍保留

结论：

- 当前产品语义已经是：
  `proposal -> 接受 -> 资产结算`
- 旧 `Trade` 只剩历史数据语义，不再是运行时业务主路径
- 用户可见文案应严格区分：
  - 协商中对象、草稿、还价、修订：用“协商”
  - 达成、结算、完成结果：用“交易”
  - 内部模型名当前仍保留 `BarterProposal`

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

Blind box 资产化与两段式开盒已经提交并落地：

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

因此，第 4 条 blind box 提交已经完成，当前不需要再把它当成待提交候选。

2026-04-02 更新：

- Phase 2 已提交：
  `17754c9`
  `Add collectible context to private messages`
- 静态质量 / MCP runtime 稳定化也已提交：
  `c62495e`
  `Stabilize MCP runtime profile and clean static typing`
- 之后的环境入口结构化修复已提交：
  `fae0a87`
  `chore: make MCP commands enter devenv explicitly`
- 当前真正的下一步已经不是这些历史提交，
  而是退役旧 `Trade` 运行时路径

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

2026-03-31 发起的一轮较大但低风险静态质量清理已经提交：

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

- 这一轮静态质量已经收敛到合理边界，并已提交
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

当前这条未提交改动只应包含旧 `Trade` 运行时退役：

- `extend.php`
- `js/src/forum/models/Collectible.ts`
- `src/Access/CollectiblePolicy.php`
- `src/Api/Resource/CollectibleEventResource.php`
- `src/Api/Resource/CollectibleResource.php`
- `src/Model/Collectible.php`
- `src/Model/CollectibleEvent.php`
- `src/Provider/CollectibleServiceProvider.php`
- `src/StateMachine/StateMachineConfig.php`
- 被删除的旧 `Trade` 后端文件与测试文件

如果接手时看到别的脏文件，先确认是否为用户手工改动，不要顺手回滚。

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
- 环境抖动的结构性修复已提交：
  `fae0a87`
  `chore: make MCP commands enter devenv explicitly`
- 修复点：
  - `.env` 只放项目配置
  - `devenv` 才提供 Playwright / MCP / mysql / `NODE_PATH`
  - 所有关键 `make mcp-*` / `verify` 入口显式通过
    `scripts/runtime/with-devenv.sh`
    进入环境
- 已真实通过：
  - `make mcp-headed`
  - `make mcp-barter-inspect`
  - `make mcp-barter`
- 最近一次真实验收结果：
  - `BarterThreadPanel = true`
  - 点击 `发起协商` 后，私信 composer 内联 panel 正常出现
  - buyer 视角 proposal 状态为 `协商中`
  - seller 接受后状态为 `已成交`

本条“旧 Trade 运行时退役”提交在 handoff 写入后已重新完成验证：

- `npm run check-typings`
  通过
- `npm run build`
  通过
- `vendor/bin/phpunit -c tests/phpunit.integration.xml`
  通过

- `make publish-site-runtime`
  通过
- `make mcp-headed`
  通过
- `make mcp-barter-inspect`
  通过
- `make mcp-barter`
  通过

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
5. 然后进入 `Phase 3`：
   把 barter proposal 挂进私信线程
   已提交
6. 然后做环境抖动的结构性修复：
   让 MCP / verify 命令显式进入 `devenv`
   已提交
7. 当前正在做：
   退役旧 `Trade` 运行时路径
8. 这条提交之后，才考虑：
   历史数据层清理 / Phase 3 后续能力扩展

关键提醒：

- `展柜 CTA` 已完成并提交，不要回退到旧 trade CTA 路径
- `Trade 模型重做` 不是取消，而是已经转向 `BarterProposal` 方案
- 不要再恢复 modal barter 路线
- 不要再把旧 `Trade` 当成主业务模型继续补功能

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
- 优先使用仓库里现成的 `make mcp-*` 入口，不要绕过 `with-devenv`

## 8. 已经明确不要再做的事

- 不要自动导入钱包
- 不要把 MetaMask gallery 当作唯一真相
- 不要把开盒和 mint 再次耦合回去
- 不要接管用户日常 Chrome
- 不要把旧 `Trade` 硬补成目标 barter 模型
- 不要让 `mcp-headed` 直接复用 seed profile
- 不要为“环境兜底”继续堆复杂 fallback
- 不要假设“在仓库目录里”就等于“已经进入 devenv shell”

## 9. 接手时先看哪些文件

- [Manul.md](/home/donk/development/flarum-ext-aigc-collectibles/Manul.md)
- [CLAUDE.md](/home/donk/development/flarum-ext-aigc-collectibles/CLAUDE.md)
- [ROADMAP.md](/home/donk/development/flarum-ext-aigc-collectibles/ROADMAP.md)
- [AGENT_HANDOFF.md](/home/donk/development/flarum-ext-aigc-collectibles/AGENT_HANDOFF.md)
- [js/src/forum/utils/privateMessages.ts](/home/donk/development/flarum-ext-aigc-collectibles/js/src/forum/utils/privateMessages.ts)
- [js/src/forum.tsx](/home/donk/development/flarum-ext-aigc-collectibles/js/src/forum.tsx)
- [js/src/forum/components/CollectibleDetailModal.tsx](/home/donk/development/flarum-ext-aigc-collectibles/js/src/forum/components/CollectibleDetailModal.tsx)
- [js/src/forum/components/CollectibleProofModal.tsx](/home/donk/development/flarum-ext-aigc-collectibles/js/src/forum/components/CollectibleProofModal.tsx)
- [js/src/forum/components/BarterThreadPanel.tsx](/home/donk/development/flarum-ext-aigc-collectibles/js/src/forum/components/BarterThreadPanel.tsx)
- [js/src/forum/components/BarterComposerPanel.tsx](/home/donk/development/flarum-ext-aigc-collectibles/js/src/forum/components/BarterComposerPanel.tsx)
- [scripts/forum/locale-status.php](/home/donk/development/flarum-ext-aigc-collectibles/scripts/forum/locale-status.php)
- [scripts/forum/set-default-locale.php](/home/donk/development/flarum-ext-aigc-collectibles/scripts/forum/set-default-locale.php)
- [resources/locale/zh-Hans.yml](/home/donk/development/flarum-ext-aigc-collectibles/resources/locale/zh-Hans.yml)
- [js/src/forum/components/BlindBoxOpener.tsx](/home/donk/development/flarum-ext-aigc-collectibles/js/src/forum/components/BlindBoxOpener.tsx)
- [js/src/global.d.ts](/home/donk/development/flarum-ext-aigc-collectibles/js/src/global.d.ts)
- [src/Api/Resource/BlindBoxResource.php](/home/donk/development/flarum-ext-aigc-collectibles/src/Api/Resource/BlindBoxResource.php)
- [src/Model/BlindBox.php](/home/donk/development/flarum-ext-aigc-collectibles/src/Model/BlindBox.php)
- [src/Api/Resource/BarterProposalResource.php](/home/donk/development/flarum-ext-aigc-collectibles/src/Api/Resource/BarterProposalResource.php)
- [src/Model/BarterProposal.php](/home/donk/development/flarum-ext-aigc-collectibles/src/Model/BarterProposal.php)
- [src/Service/BarterService.php](/home/donk/development/flarum-ext-aigc-collectibles/src/Service/BarterService.php)
- [src/Service/BlindBoxService.php](/home/donk/development/flarum-ext-aigc-collectibles/src/Service/BlindBoxService.php)
- [src/Access/CollectiblePolicy.php](/home/donk/development/flarum-ext-aigc-collectibles/src/Access/CollectiblePolicy.php)
- [src/Service/CollectibleProofService.php](/home/donk/development/flarum-ext-aigc-collectibles/src/Service/CollectibleProofService.php)
- [scripts/runtime/with-devenv.sh](/home/donk/development/flarum-ext-aigc-collectibles/scripts/runtime/with-devenv.sh)
- [scripts/playwright/mcp.config.json](/home/donk/development/flarum-ext-aigc-collectibles/scripts/playwright/mcp.config.json)
- [scripts/playwright/mcp-cli/mcp-client.cjs](/home/donk/development/flarum-ext-aigc-collectibles/scripts/playwright/mcp-cli/mcp-client.cjs)

## 10. 一句话总结

项目当前不是“功能没做”，而是已经从旧 `Trade` 过渡到私信线程内 proposal，并正在把旧运行时残留退役：

- proof 已落地
- showcase 已落地
- Phase 1 的 CTA -> 私信 已提交
- 第二条小修已提交
- 第三条 locale 已提交
- 第四条 BlindBox 资产化与两段式开盒已提交
- Phase 2 的私信藏品上下文也已提交
- Phase 3 的 proposal-in-PM 主链也已提交
- MCP / verify 的环境抖动已做结构性修复
- 当前进行中的是：
  退役旧 `Trade` 运行时路径
- 旧 `Trade` 不再是目标模型，也不再应出现在运行时主链路

下一位 agent 不要偏航。先看清当前 dirty tree 里哪些属于质量提交、哪些属于 Phase 3，然后在每条 commit 前都先做 headed MCP 实测。
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

## 13. 2026-04-02 Phase 3 已落地

这一条非常关键，下一位 agent 不要继续沿用旧思路：

- 用户已经明确否决 `CreateBarterProposalModal`
- 原因不是实现难度，而是产品方向不对
- 用户不想在一个局促 modal 里“为了交易而交易”
- 用户希望 barter 仍然发生在私信上下文中

当前已确认的新方向：

- 不做独立 barter modal
- 协商配置直接放进 PM composer
- 用户在同一个 composer 里：
  - 正常输入私信正文
  - 选择“我愿意给什么资产”
  - 选择“我想要对方什么资产”
- 不再单独维护 barter `附言`
- 真正想说的话就直接发在私信正文里
- proposal 作为附带的协商对象创建

### 13.1 已提交实现状态

已落地并已提交的 Phase 3 代码包括：

- 后端 barter 生命周期骨架：
  - `BarterProposal`
  - `BarterProposalItem`
  - create / accept / reject / cancel
  - mixed asset settle
- 新增 thread 资产接口：
  - `GET /api/barter-assets`
  - controller:
    [ListBarterAssetsController.php](/home/donk/development/flarum-ext-aigc-collectibles/src/Api/Controller/ListBarterAssetsController.php)
  - service formatter:
    [BarterAssetFormatter.php](/home/donk/development/flarum-ext-aigc-collectibles/src/Service/BarterAssetFormatter.php)
- 前端 composer 内联 barter：
  - [BarterComposerPanel.tsx](/home/donk/development/flarum-ext-aigc-collectibles/js/src/forum/components/BarterComposerPanel.tsx)
  - [barterComposer.ts](/home/donk/development/flarum-ext-aigc-collectibles/js/src/forum/utils/barterComposer.ts)
  - [BarterThreadPanel.tsx](/home/donk/development/flarum-ext-aigc-collectibles/js/src/forum/components/BarterThreadPanel.tsx)
  - [forum.tsx](/home/donk/development/flarum-ext-aigc-collectibles/js/src/forum.tsx)

已经移除：

- `CreateBarterProposalModal.tsx`

### 13.2 当前 Phase 3 交互设计

私信线程里现在应当是这样的结构：

- 右侧：
  `BarterThreadPanel`
  展示协商历史、接受/拒绝/取消
- 底部 composer：
  `BarterComposerPanel`
  负责开启/关闭协商草稿、勾选双方资产、随消息一起发 proposal

当前实现逻辑：

- 不启用 barter 时：
  `MessageComposer` 保持原生私信发送
- 启用 barter 时：
  - 仍然先发送 PM 正文
  - 然后立即创建 `barter-proposal`
  - proposal 不再带单独 `message`
  - 协商文本以 PM 正文为准

这与用户的意图一致：

- 聊天是主轴
- 协商对象挂在线程里
- 不是打开一个独立交易弹窗

### 13.3 当前验证状态

已通过的静态/集成验证：

- `cd js && npm run check-typings -- --pretty false`
- `cd js && npm run build`
- `vendor/bin/phpunit -c tests/phpunit.integration.xml --filter BarterProposalChainTest`

其中新增了一条 API 测试：

- `barter_assets_endpoint_returns_both_sides_assets_for_a_direct_dialog`

注意：

- `ListBarterAssetsController` 不能直接依赖 `$request->getQueryParams()`
  读出 `filter[...]`
- 已仿照 `BlindBoxResource` 加了：
  `parse_str($request->getUri()->getQuery(), $query)`
  作为回退
- 否则集成测试里会得到：
  `Thread ID is required.`

2026-04-02 本轮新增确认：

- 已实际核对 `flarum/messages` 上游源码：
  - `MessageStream` 官方回复入口就是
    `ReplyPlaceholder -> app.composer.load(() => import('./MessageComposer'))`
  - 当前本地 `openBarterComposer()` 复用这条链路是正确方向
- 已核对运行时 registry：
  - 正确可扩展路径是：
    `ext:flarum/messages/forum/components/DialogSection`
    `ext:flarum/messages/forum/components/MessageComposer`
  - 不是：
    `flarum/messages/forum/components/...`
- 当前 `openBarterComposer()` 的稳定策略是：
  - 先尝试点击当前线程里的 `.ReplyPlaceholder`
  - 复用官方 `MessageComposer` 打开链路
  - 只有失败时才 fallback 到手工 `composer.load(...)`
  - 这样可避开之前手工 load 导致的 `ReplyComposer.bind` 类崩溃
- 已新增 composer-inline 验收脚本：
  - [mcp-inspect-barter-composer.cjs](/home/donk/development/flarum-ext-aigc-collectibles/scripts/playwright/mcp-cli/mcp-inspect-barter-composer.cjs)
  - [mcp-validate-barter-composer.cjs](/home/donk/development/flarum-ext-aigc-collectibles/scripts/playwright/mcp-cli/mcp-validate-barter-composer.cjs)
  - [mcp-validate-barter-composer-validation.cjs](/home/donk/development/flarum-ext-aigc-collectibles/scripts/playwright/mcp-cli/mcp-validate-barter-composer-validation.cjs)
- `mcp-inspect-barter-composer.cjs` 最新实测结果：
  - `DialogSection-streamWrap = true`
  - `BarterThreadPanel = true`
  - 点击“发起协商”后：
    - `composerVisible = true`
    - `editorExists = true`
    - `barterPanelExists = true`
    - buyer 侧资产：
      - collectibles `4`
      - blind boxes `5`
    - seller 侧资产：
      - collectibles `0`
      - blind boxes `4`
- `mcp-validate-barter-composer.cjs` 最新 full validation 已通过：
  - buyer 发 proposal 成功
  - seller accept 成功
  - 当前脚本已经不再依赖 `.BarterProposalCard:first` 的脆弱选择器
  - `BarterThreadPanel` 现在会：
    - 前端显式按 `createdAt desc` / `revisionNumber desc` / `id desc` 排序
    - 为每张卡输出：
      `data-proposal-id`
      `data-proposal-status`
  - 因此 headed 验收现在可以稳定追踪“本次刚创建的 proposal”
  - 2026-04-02 最新实测结果为：
    - `buyerView.myCount = 8`
    - `buyerView.theirCount = 4`
    - `buyerView.composerVisible = false`
    - `buyerView.proposalStatus = 协商中`
    - `buyerView.proposalActions = ["发起还价","取消"]`
    - `sellerView.status = 已成交`
    - `sellerView.actionTexts = []`
- `mcp-validate-barter-composer-validation.cjs` 最新实测已通过：
  - `empty-assets -> 至少选择一项资产。`
  - `missing-counterparty-assets -> 协商必须同时包含双方资产。`
  - `missing-message -> 请先在私信输入框里写下你要发送的话，再发送协商。`
- `mcp-validate-barter-history.cjs` 最新实测已通过：
  - 在真实私信线程里连续创建两版协商
  - 前一版卡片会显示“已被第 N 版替代”
  - 新一版卡片会显示“替代第 N 版”
  - 成交后当前版卡片会显示“buyer 接受”
- 2026-04-02 当前测试基线：
  - 目标 unit tests：
    `BlindBoxServiceTest|CollectibleProofServiceTest`
    `14 / 14` 通过
  - 全量 unit coverage：
    - Classes `1.52% (1/66)`
    - Methods `13.72% (31/226)`
    - Lines `18.27% (364/1992)`
  - 当前高价值服务覆盖：
    - `BlindBoxService` lines `42.77%`
    - `CollectibleProofService` lines `88.83%`
- 2026-04-02 后续噪声清理结果：
  - unit test 中遗留的 `PHPUnit Deprecations: 26` 已清零
  - 当前 `tests/phpunit.unit.xml` 运行结果为：
    - `33 tests`
    - `155 assertions`
    - `OK`

### 13.4 当前还没做的事

- 当前不再需要决定“是否先提交 Phase 3”，因为它已经提交
- 当前真正未做的是：
  - 退役旧 `Trade` 运行时路径后的 headed MCP 重新验收
  - 后续是否继续清理历史数据层：
    `trades` 表 / `collectible_events.trade_id` 的最终去留
  - Phase 3 的后续能力扩展：
    更细粒度协商修订、历史呈现、语义清理

### 13.5 脚本现状提醒

当前已有的 MCP 脚本里：

- 优先使用已经存在的 composer-inline 脚本：
  - `mcp-inspect-barter-composer.cjs`
  - `mcp-validate-barter-composer.cjs`
- 优先使用 `make` 入口：
  - `make mcp-barter-inspect`
  - `make mcp-barter`
- 2026-04-02 当前已确认根因：
  - `.env` 只提供项目配置
  - `direnv` / `devenv` 才提供 Playwright / MCP / mysql / NODE_PATH 等工具链环境
  - 当前 shell 在仓库目录里，不等于已经进入 `devenv`
  - 当前修复方向是统一通过
    `scripts/runtime/with-devenv.sh`
    显式进入项目环境
- 不要继续围绕 `.CreateBarterProposalModal` 做诊断
- 2026-04-02 当前仓库已删除 modal-era barter MCP 脚本：
  - `mcp-inspect-barter-create-modal.cjs`
  - `mcp-inspect-barter-modal.cjs`
  - `mcp-validate-barter-thread.cjs`
