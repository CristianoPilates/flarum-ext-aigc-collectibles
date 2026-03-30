# Agent Handoff

这份文档给后续 Code agent / 新会话 Codex 用。

它只记录当前现场，不负责解释完整架构。完整规则看 `CLAUDE.md`，具体操作看 `Manul.md`。

## 1. 用户偏好与硬约束

- 全程优先中文，少噪音，先给证据再下结论
- 不接管用户日常 Chrome
- 只用 headed Chromium + 真实 MetaMask unpacked extension
- 只保留一份共享 profile：
  `.devenv/state/playwright-profile`
- 钱包导入由用户手工完成，之后长期复用同一份 profile
- 不要自动化导入钱包
- mint 必须是用户主动动作
- 证明 NFT 已经成立时，不要只依赖 MetaMask NFT 列表
- 每条 commit 之前，都要先用 `mcp-headed` 做实际验收

不要把用户密码写进仓库。

## 2. 当前现场快照

截至 2026-03-30：

- 当前工作分支：
  `main`
- 已落在 `HEAD` 的最近提交：
  `f672b73`
- 当前工作树是 dirty 的
- dirty 内容主要包括：
  - Phase 1 展柜 CTA -> 私信 的前端实现与 smoke
  - proof/detail/messages 文案与样式改动
  - 少量与本轮任务无关的既有脏文件，例如：
    `contracts/CollectibleNFT.sol`

## 3. 已确认的事实

### 3.1 浏览器 / MetaMask

- `pw-manual` 与 `mcp-headed` 已统一复用：
  `.devenv/state/playwright-profile`
- 真实 MetaMask unpacked extension 目录：
  `e2e/support/nkbihfbeogaeaoehlefnkodbefgpgknn`
- mock `window.ethereum` 已移除
- GUI 路径已经证明能看到真实 MetaMask provider
- 钱包导入策略已经定为人工一次导入，长期复用 profile

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

本轮已经把 Phase 1 主链打通。

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

- Phase 1 主链已可提交
- 下一步可以切第一条 commit

### 3.5 Trade / barter

必须明确：

- 用户已同意不要继续把旧 `Trade` 模型硬补成 barter
- 当前只是在 Phase 1 中绕开旧 `Trade`，把 CTA 先接到私信
- `Trade/barter` 领域模型重做仍未开始

现状：

- 旧 `TradeRequestModal` 还在代码里
- 旧 `Trade` 仍然是“盲盒数量换单个 collectible”的窄模型
- 用户后续还额外报告了一个旧 bug：
  `set offer` 后 modal 无法正常关闭

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

当前仓库 `vendor/` 中已能看到：

- `vendor/flarum-lang/chinese-simplified`

但是否在站点里启用，仍需实际确认。

### 3.8 前端技术栈 / 诊断

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

所以 PHP 侧若要做静态分析，要先补配置/依赖，或者改走现有测试与运行时校验路径。

## 4. 当前 dirty 文件

当前可见 dirty 文件：

- `contracts/CollectibleNFT.sol`
- `e2e/app.e2e.spec.cjs`
- `js/src/forum.tsx`
- `js/src/forum/components/CollectibleDetailModal.tsx`
- `js/src/forum/components/PostCollectibleShowcase.tsx`
- `resources/less/forum.less`
- `resources/locale/en.yml`
- `resources/locale/zh-hans.yml`
- `js/src/forum/utils/privateMessages.ts`

说明：

- 其中 `contracts/CollectibleNFT.sol` 不是本轮主任务改动，应避免误回滚
- 其余大部分与 Phase 1 CTA 私信改造直接相关

## 5. 已跑过的验证

本轮已确认：

- `npm run build` in `js/`
  通过
- headed MCP：
  展柜 CTA -> 私信 composer
  通过
- headed MCP：
  buyer 发送后跳转 `/messages/dialog/2`
  通过
- headed MCP：
  `/messages/dialog/2` 消息流正文渲染
  通过

注意：

- 真正要进入每条 commit 之前，必须先走 `mcp-headed` 的实际 GUI 验收
- 不是只靠 headless smoke

## 6. 当前执行顺序

这是当前认可的顺序，后续会话不要改丢：

1. 先收口 Phase 1：
   修复并验收 `展柜 CTA -> 私信`
2. 然后修一批短平快问题：
   - 旧 `TradeRequestModal` 关闭 bug
   - detail / proof 的 owner 展示与 profile 跳转
3. 然后做语言切换与诊断清理
4. 然后把 BlindBox 从余额重构为一等资产并做独立页面
5. 然后把开盒流程改成“先鉴定，后 open”
6. 最后才重做 barter / Trade 领域模型并挂入私信线程

关键提醒：

- `展柜 CTA` 还没完成，不要因为讨论了 BlindBox / owner / i18n 就把它搁置
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

## 8. 已经明确不要再做的事

- 不要再搞第二份 profile
- 不要自动导入钱包
- 不要把 MetaMask gallery 当作唯一真相
- 不要把开盒和 mint 再次耦合回去
- 不要接管用户日常 Chrome
- 不要把旧 `Trade` 硬补成目标 barter 模型

## 9. 接手时先看哪些文件

- [Manul.md](/home/donk/development/flarum-ext-aigc-collectibles/Manul.md)
- [CLAUDE.md](/home/donk/development/flarum-ext-aigc-collectibles/CLAUDE.md)
- [ROADMAP.md](/home/donk/development/flarum-ext-aigc-collectibles/ROADMAP.md)
- [AGENT_HANDOFF.md](/home/donk/development/flarum-ext-aigc-collectibles/AGENT_HANDOFF.md)
- [js/src/forum/utils/privateMessages.ts](/home/donk/development/flarum-ext-aigc-collectibles/js/src/forum/utils/privateMessages.ts)
- [js/src/forum.tsx](/home/donk/development/flarum-ext-aigc-collectibles/js/src/forum.tsx)
- [js/src/forum/components/PostCollectibleShowcase.tsx](/home/donk/development/flarum-ext-aigc-collectibles/js/src/forum/components/PostCollectibleShowcase.tsx)
- [js/src/forum/components/CollectibleDetailModal.tsx](/home/donk/development/flarum-ext-aigc-collectibles/js/src/forum/components/CollectibleDetailModal.tsx)
- [js/src/forum/components/CollectibleProofModal.tsx](/home/donk/development/flarum-ext-aigc-collectibles/js/src/forum/components/CollectibleProofModal.tsx)
- [js/src/forum/components/TradeRequestModal.tsx](/home/donk/development/flarum-ext-aigc-collectibles/js/src/forum/components/TradeRequestModal.tsx)
- [js/src/forum/components/BlindBoxOpener.tsx](/home/donk/development/flarum-ext-aigc-collectibles/js/src/forum/components/BlindBoxOpener.tsx)
- [src/Api/Resource/BlindBoxResource.php](/home/donk/development/flarum-ext-aigc-collectibles/src/Api/Resource/BlindBoxResource.php)
- [src/Model/BlindBox.php](/home/donk/development/flarum-ext-aigc-collectibles/src/Model/BlindBox.php)
- [src/Model/Trade.php](/home/donk/development/flarum-ext-aigc-collectibles/src/Model/Trade.php)
- [src/Service/BlindBoxService.php](/home/donk/development/flarum-ext-aigc-collectibles/src/Service/BlindBoxService.php)

## 10. 一句话总结

项目当前不是“所有东西都没做”，而是已经进入一个明确但未收口的过渡态：

- proof 已落地
- showcase 已落地
- Phase 1 的 CTA -> 私信 已接上但未验收通过
- BlindBox 资产化与两段式开盒还未开始
- Trade/barter 重做仍在后续阶段

下一位 agent 不要偏航。先把 `展柜 CTA -> 私信` 真正打通，再继续后面的 commit 序列。
