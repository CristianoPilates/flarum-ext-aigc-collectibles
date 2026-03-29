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

不要把用户密码写进仓库。

## 2. 当前现场快照

截至 2026-03-29：

- 当前工作分支：
  `state-machine`
- 已落在 `HEAD` 的最近提交：
  `3b63288` `add reply showcase validation flow`
- 当前工作树是 dirty 的
- dirty 内容主要包含两类：
  - proof modal 功能与测试
  - 本轮文档清理

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

### 3.3 Showcase

reply 右侧展柜第一版已经实现并通过 headed MCP 验收。

已验证过：

- `make mcp-showcase`
- reply 页面存在右侧 showcase panel
- 点击 panel 可打开 collectible detail modal

### 3.4 Proof

proof modal 第一版已经实现并跑通过 headed MCP 验收。

当前新增内容包括：

- `GET /api/collectibles/{id}/proof`
- `CollectibleDetailModal` 里的 `View Proof` 按钮
- `CollectibleProofModal`
- `make mcp-proof`
- `scripts/playwright/mcp-cli/mcp-validate-proof.cjs`

proof modal 当前验证四层：

1. `App Record`
2. `Chain Proof`
3. `Metadata JSON`
4. `Image Asset`

最近一次成功验证的关键值：

- `Collectible #43`
- `Token ID = 8`
- `Metadata CID = QmcLVCHYo8eBbwHGbCYuUkavmZz9XG9gphNjocoEN9nq6L`
- `Image CID = QmcDxqofG2Wpou9HG7WbsmaatZgCcGrxk9aKc5uvAiwXTx`
- `ownerOf = 0xabb6bc9ec4c33cf50b4013ff8bb3b4855e35168f`
- `tokenURI = ipfs://QmcLVCHYo8eBbwHGbCYuUkavmZz9XG9gphNjocoEN9nq6L`

截图产物：

- `.devenv/state/playwright-mcp-output/collectible-proof-modal.png`

### 3.5 Blind box / rarity / phrase pool

- 10 秒 PoW 阈值已调高，不再容易稳定刷高稀有度
- phrase pool 已明显扩充，不再几乎全是 dragon 题材
- 开盒后自动 mint 已移除

### 3.6 AIGC runtime

- `akashgen` 之前 1 小时后自动退出的问题已经在外部仓库修掉
- 现在按常驻服务处理，健康检查看：
  `http://127.0.0.1:6571/health`

## 4. 当前 dirty 文件

本轮之前已存在的功能性未提交改动：

- `Makefile`
- `js/src/forum/components/CollectibleDetailModal.tsx`
- `resources/less/forum.less`
- `resources/locale/en.yml`
- `resources/locale/zh-hans.yml`
- `scripts/playwright/mcp-cli/README.md`
- `src/Api/Resource/CollectibleResource.php`
- `src/Provider/CollectibleServiceProvider.php`
- `js/src/forum/components/CollectibleProofModal.tsx`
- `scripts/playwright/mcp-cli/mcp-validate-proof.cjs`
- `src/Service/CollectibleProofService.php`
- `src/Service/Contracts/CollectibleProofServiceInterface.php`
- `tests/Fake/FakeCollectibleProofService.php`
- `tests/integration/api/CollectibleProofChainTest.php`
- `tests/unit/Service/CollectibleProofServiceTest.php`

本轮新增的是文档清理改动。

## 5. 已跑过的验证

- `npm run build` in `js/`
- `vendor/bin/phpunit -c tests/phpunit.unit.xml --filter CollectibleProofServiceTest`
- `vendor/bin/phpunit -c tests/phpunit.integration.xml --filter CollectibleProofChainTest`
- `make mcp-showcase`
- `make mcp-proof`

已知情况：

- phpunit 会有 deprecations / warnings，但测试通过
- headed MCP 如果 browser context 死掉，`mcp-proof` 之类脚本会报 `Session not found` 或 `Target page, context or browser has been closed`
- 这种情况下优先重启 `make mcp-headed`

## 6. 当前文档体系

清理后，Markdown 只保留这几份：

- `Manul.md`
  使用说明 / 运维说明 / 验收说明
- `CLAUDE.md`
  架构规则与编码规则
- `AGENT_HANDOFF.md`
  当前现场和接手说明
- `ROADMAP.md`
  当前唯一有效的计划文档
- `scripts/playwright/mcp-cli/README.md`
  Playwright MCP CLI 包装器说明

旧的 `NOTE.md` 与 `Notes/*.md` 已视为历史噪音，不再维护。

## 7. 下阶段推荐顺序

按当前产品共识，推荐顺序如下：

1. 提交 proof modal + 文档清理
2. 启用 `flarum/messages`
3. 把 blind box 做成可见的一等资产，而不是纯数字
4. 基于私信重做 barter 领域模型

原因：

- 用户真正关心的是私信中的 P2P 社交交易，不是纯 NFT 展示
- 当前 `Trade` 模型仍然偏“买家出盲盒，卖家出单个 collectible”的窄模型
- 这和目标中的双向议价、多资产交换、任意组合交换不匹配

## 8. 已经明确不要再做的事

- 不要再搞第二份 profile
- 不要自动导入钱包
- 不要把 MetaMask gallery 当作唯一真相
- 不要把开盒和 mint 再次耦合回去
- 不要接管用户日常 Chrome

## 9. 接手时先看哪些文件

- [Manul.md](/home/donk/development/flarum-ext-aigc-collectibles/Manul.md)
- [CLAUDE.md](/home/donk/development/flarum-ext-aigc-collectibles/CLAUDE.md)
- [ROADMAP.md](/home/donk/development/flarum-ext-aigc-collectibles/ROADMAP.md)
- [scripts/playwright/mcp.config.json](/home/donk/development/flarum-ext-aigc-collectibles/scripts/playwright/mcp.config.json)
- [scripts/playwright/launch-manual.cjs](/home/donk/development/flarum-ext-aigc-collectibles/scripts/playwright/launch-manual.cjs)
- [scripts/playwright/mcp-cli/README.md](/home/donk/development/flarum-ext-aigc-collectibles/scripts/playwright/mcp-cli/README.md)
- [js/src/forum/components/CollectibleDetailModal.tsx](/home/donk/development/flarum-ext-aigc-collectibles/js/src/forum/components/CollectibleDetailModal.tsx)
- [js/src/forum/components/CollectibleProofModal.tsx](/home/donk/development/flarum-ext-aigc-collectibles/js/src/forum/components/CollectibleProofModal.tsx)
- [src/Service/CollectibleProofService.php](/home/donk/development/flarum-ext-aigc-collectibles/src/Service/CollectibleProofService.php)
- [src/Model/Trade.php](/home/donk/development/flarum-ext-aigc-collectibles/src/Model/Trade.php)
- [src/Service/BlindBoxService.php](/home/donk/development/flarum-ext-aigc-collectibles/src/Service/BlindBoxService.php)

## 10. 一句话总结

项目已经从“多份 profile、mock provider、不稳定验证”收敛到：

- 单一 shared profile
- 真实 MetaMask
- 真实 GUI 验收
- mint 与开盒解耦
- reply showcase 已落地
- proof modal 已落地

下一个真正大的工程阶段，不是补更多小按钮，而是把 blind box 和 PM barter 重新做成一套合理的产品模型。
