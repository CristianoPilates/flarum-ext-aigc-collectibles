# Agent Handoff

这份文档给后续的 Code agent / 新会话 Codex 用。

目标不是解释整个项目，而是让接手的人快速知道：

- 现在什么已经验证过了
- 哪些现场状态必须保留
- 哪些坑已经踩过，不要再踩
- 现阶段下一步应该做什么

---

## 1. 用户偏好与硬约束

这些是当前用户明确要求过的：

- 全程优先中文，少噪音，先给证据再下结论
- 只用 headed Chromium，不接管用户日常 Chrome
- 只用真实 MetaMask unpacked extension，不再用 mock `window.ethereum`
- 只保留一份共享 profile：
  `.devenv/state/playwright-profile`
- 钱包导入由用户手动完成，之后长期复用同一份 shared profile
- 不要自动化导入钱包
- mint 应该是用户主动动作，不应该在“开盒/抽样”路径里顺手自动做掉
- 对“是否真的接入 MetaMask / 是否真的像样的 NFT 路径”要用真实 GUI 证明，不能只说 smoke 过了

不要把用户提供的钱包密码写进仓库文件。

---

## 2. 当前已确认的事实

### 2.1 浏览器 / MetaMask

- `pw-manual` 和 `mcp-headed` 现在复用同一份 profile：
  `.devenv/state/playwright-profile`
- 真实 MetaMask unpacked extension 目录：
  `e2e/support/nkbihfbeogaeaoehlefnkodbefgpgknn`
- 当前应用页能看到真实 provider：
  `window.ethereum.isMetaMask === true`
- 已验证共享 profile 中有真实钱包账户，应用层已绑定地址：
  `0xabb6bc9ec4c33cf50b4013ff8bb3b4855e35168f`

### 2.2 最小 NFT 路径

真实 GUI + 真实 MetaMask + 真实应用路径已经跑通过一次完整闭环：

- 登录
- 开盒生成藏品
- 钱包已绑定
- 手动 mint

已确认的样本：

- `Collectible #43`
- `tokenId = 8`
- `metadataCid = QmcLVCHYo8eBbwHGbCYuUkavmZz9XG9gphNjocoEN9nq6L`
- `ipfsCid = QmcDxqofG2Wpou9HG7WbsmaatZgCcGrxk9aKc5uvAiwXTx`

注意：

- 当前 `mint` 不是由 MetaMask 发起每一笔交易确认
- 当前实现是后端使用配置的 minter key 调链上合约 mint
- MetaMask 在当前产品里主要负责“钱包绑定 / 签名证明地址归属”

所以：

- “真实 MetaMask 已接入”这件事是真的
- “链上 NFT 已经真的被 mint 出来”这件事也是真的
- 但“每次 mint 都要弹 MetaMask confirm”现在还不是这个产品的实现方式

### 2.3 AIGC / 生成链路

- 之前真正的大阻塞之一是 `akashgen` 会在 1 小时后自动退出
- 根因在外部仓库：
  `/home/donk/development/akashgen-api-go/main.go`
- 已修复为标准常驻服务：只在收到 `SIGINT/SIGTERM` 时退出
- 现在 `http://127.0.0.1:6571/health` 可正常返回

另外，本地最小可用生成链路已经兜底：

- [AIGCService.php](/home/donk/development/flarum-ext-aigc-collectibles/src/Service/AIGCService.php)
  在外部 AIGC 不可用或失败时，会 fallback 生成 deterministic SVG
- [IPFSService.php](/home/donk/development/flarum-ext-aigc-collectibles/src/Service/IPFSService.php)
  上传 SVG 时会正确命名为 `collectible.svg`

### 2.4 auto-mint

- 生成成功后自动 mint 的逻辑已经从
  [GenerateCollectibleJob.php](/home/donk/development/flarum-ext-aigc-collectibles/src/Job/GenerateCollectibleJob.php)
  移除
- 现在生成完成后 `token_id` 保持 `null`
- 手动 mint 路径仍保留在
  [CollectibleDetailModal.tsx](/home/donk/development/flarum-ext-aigc-collectibles/js/src/forum/components/CollectibleDetailModal.tsx)
  和后端 mint handler 中

这符合当前用户意图：

- 生成是生成
- mint 是 mint
- 两者不要在产品逻辑里混在一起

---

## 3. 本轮新增的重要改动

### 3.1 稀有度分布修正

之前 PoW 阈值过低，10 秒鉴定几乎稳定刷到高档位，导致“总是 Rare / Legendary”。

现在前后端阈值已统一为：

- `5` 个前导零 => `rare`
- `6` 个前导零 => `epic`
- `7` 个前导零 => `legendary`
- 其余 => `common`

对应文件：

- [BlindBoxService.php](/home/donk/development/flarum-ext-aigc-collectibles/src/Service/BlindBoxService.php)
- [BlindBoxOpener.tsx](/home/donk/development/flarum-ext-aigc-collectibles/js/src/forum/components/BlindBoxOpener.tsx)

真实抽样证据：

- 新开出的 `Collectible #44` 已经回到 `common`
- 说明“10 秒 PoW 总刷到高品质”的问题已经被打破

### 3.2 Phrase pool 再扩一轮

用户认为当前 phrase pool 太浅，容易总出现 dragon 系题材。

已新增更多：

- `subject`
- `style`
- `mood`
- `theme`

新增 migration：

- [2026_03_28_000005_expand_phrase_pool_again.php](/home/donk/development/flarum-ext-aigc-collectibles/migrations/2026_03_28_000005_expand_phrase_pool_again.php)

现有 phrase 规模测试阈值也已提升。

### 3.3 测试已更新

已更新：

- [DefaultBlindBoxContentTest.php](/home/donk/development/flarum-ext-aigc-collectibles/tests/integration/api/DefaultBlindBoxContentTest.php)
- [BlindBoxLifecycleTest.php](/home/donk/development/flarum-ext-aigc-collectibles/tests/integration/api/BlindBoxLifecycleTest.php)

已验证通过：

```bash
vendor/bin/phpunit -c tests/phpunit.integration.xml --filter "DefaultBlindBoxContentTest|BlindBoxLifecycleTest"
```

注意：

- 如果 `flarum_test` 没初始化，先运行：

```bash
php tests/integration/setup.php
```

---

## 4. 现在推荐怎么接着做

### 4.1 应该优先补的脚本

当前 [mcp-minimal-nft.cjs](/home/donk/development/flarum-ext-aigc-collectibles/scripts/playwright/mcp-cli/mcp-minimal-nft.cjs) 仍然偏“开盒后继续 mint”。

这不适合做抽样，也不适合长期开发验证，因为：

- mint 有成本
- 会污染“只想看生成结果”的测试
- 不够符合用户实际使用心智

下一步建议新增一个更小脚本，例如：

- `scripts/playwright/mcp-cli/mcp-sample-blindbox.cjs`

只做：

- 登录
- 打开盲盒
- 等待 collectible 完成
- 返回 `collectibleId / blindBoxId / budget / rarity / aigcPrompt / ipfsCid / metadataCid`

不做：

- 绑定钱包
- mint

### 4.2 帖子里的“炫耀展示”还没做

当前帖子里的展示形态只是一个小 badge。

用户已明确不满意：

- 太小
- 看不清细节
- 不利于交易欲望
- 不够“炫耀”

后续应考虑把展示升级为“帖子内容背景层 / 帖子头图 / 局部氛围卡片”，而不是继续把 badge 做大。

### 4.3 链上证明 / IPFS 验收还没整理成完整验收脚本

虽然已经有链上 mint 成功样本，但还没有专门的验收文档或脚本去同时证明：

- `ownerOf(tokenId)` 指向用户地址
- `tokenURI(tokenId)` 指向 `ipfs://metadataCid`
- metadata JSON 中的 `image` 指向 `ipfs://ipfsCid`
- 网关可访问 metadata / image

这是后续应补的一块。

---

## 5. 常见坑

### 5.1 不要再搞第二份 profile

凡是出现“manual 里钱包是好的，MCP 里却在 onboarding”，优先怀疑：

- profile 没复用
- 启动参数改坏了
- 又回到了其他 profile

### 5.2 不要自动导入钱包

这是脆弱路径，而且用户已经明确拒绝过。

### 5.3 不要把 sandbox 内的本地 socket 错误误判成业务问题

仓库里通过 Node 调 MCP HTTP 时，sandbox 内经常会遇到：

- `fetch failed`
- `EPERM 127.0.0.1:8931`

这通常是本地 socket / sandbox 限制，不代表业务逻辑一定坏了。

### 5.4 集成测试库可能需要重建

如果 `phpunit` 报测试库初始化问题，先跑：

```bash
php tests/integration/setup.php
```

---

## 6. 接手时先看哪些文件

优先看这些：

- [Manul.md](/home/donk/development/flarum-ext-aigc-collectibles/Manul.md)
- [scripts/playwright/mcp.config.json](/home/donk/development/flarum-ext-aigc-collectibles/scripts/playwright/mcp.config.json)
- [scripts/playwright/launch-manual.cjs](/home/donk/development/flarum-ext-aigc-collectibles/scripts/playwright/launch-manual.cjs)
- [scripts/playwright/mcp-cli/mcp-minimal-nft.cjs](/home/donk/development/flarum-ext-aigc-collectibles/scripts/playwright/mcp-cli/mcp-minimal-nft.cjs)
- [BlindBoxOpener.tsx](/home/donk/development/flarum-ext-aigc-collectibles/js/src/forum/components/BlindBoxOpener.tsx)
- [CollectibleDetailModal.tsx](/home/donk/development/flarum-ext-aigc-collectibles/js/src/forum/components/CollectibleDetailModal.tsx)
- [BlindBoxService.php](/home/donk/development/flarum-ext-aigc-collectibles/src/Service/BlindBoxService.php)
- [GenerateCollectibleJob.php](/home/donk/development/flarum-ext-aigc-collectibles/src/Job/GenerateCollectibleJob.php)
- [MintCollectibleHandler.php](/home/donk/development/flarum-ext-aigc-collectibles/src/Command/MintCollectibleHandler.php)
- [NftMintingService.php](/home/donk/development/flarum-ext-aigc-collectibles/src/Service/NftMintingService.php)

---

## 7. 一句话总结

当前项目已经从“mock 的 Web3 / 不稳定的生成链路 / 分叉的 Playwright profile”收敛到：

- 单一 shared profile
- 真实 MetaMask
- 真实 GUI
- 已验证最小 NFT 链路
- 更合理的 PoW 稀有度分布
- 更丰富的默认 phrase pool

下一步最值得做的是：

- 把“只开盒抽样”和“主动 mint”彻底拆开
- 补链上 / IPFS 证明型验收
- 升级帖子里的藏品展示形态

---

## 8. 新增设计共识

### 8.1 帖子展示不应继续做小 badge

用户已明确认为：

- badge 太小
- 条带 / 纯背景会损失藏品细节
- 不应只在发主题帖时展示，回复也应该展示

当前更值得尝试的方向是：

- 在每条 `CommentPost` 右侧做固定宽度的 collectible showcase 区域
- 正文区在左侧自适应收缩
- 移动端再退化为上下堆叠

这比“背景图”更能保留藏品细节，也比“顶部条带”更像持续展示的个人展柜。

技术上是可行的，原因：

- Flarum `AbstractPost` / `CommentPost` 结构里有稳定的 `.Post-main`
- 可以通过扩展 `CommentPost.prototype.content()` 或覆盖 comment 内容组件，在内容区增加一个并列的 showcase 容器
- 不必去做真正复杂的 `shape-outside` 文本绕排；第一版直接用双栏 layout 更稳

建议第一版：

- 桌面端：`content + showcase` 双栏，右栏固定 180-240px
- 移动端：showcase 折叠到正文下方
- 点击 showcase 可打开大图 modal / collectible detail

### 8.2 “NFT 已经真归你”要怎么演示

用户这里要的不是工程内部证明，而是可展示的 GUI 证据链。

最像样的演示链应该是：

1. 应用页的 collectible detail：
   显示 `Token ID`
2. 钱包或链上浏览界面：
   显示该 `tokenId` 的 owner 是用户钱包地址
3. token metadata 页面：
   能看到 `tokenURI`
4. metadata JSON：
   能看到 `image: ipfs://...`
5. IPFS 网关页面：
   能看到 metadata 和实际图片

当前项目的 mint 架构是：

- 后端 owner/minter wallet 调合约 mint
- NFT 归属到用户绑定的钱包地址

所以 GUI 证明的关键不是“MetaMask 亲手发起了交易”，而是：

- `ownerOf(tokenId)` 指向用户地址
- `tokenURI(tokenId)` 与应用中 `metadataCid` 对得上

### 8.3 IPFS “后台演示”认知

用户想要的是一种“看，这些内容真的在 IPFS 里”的展示方式。

需要注意：

- `127.0.0.1:5001` 是 Kubo API 端口，不是主要的人类友好浏览界面
- 更适合演示内容的是网关：
  `127.0.0.1:8888/ipfs/<cid>`

演示时应优先展示：

- metadata 网关地址
- image 网关地址
- metadata JSON 内的 `image` 字段
- 应用页中对应的 `metadataCid / ipfsCid`

如果后续要补“后台风格”的演示，可以单独加一份验收说明或脚本，把：

- 应用层 CID
- 链上 tokenURI
- IPFS 网关访问
- Kubo API `cat`

这四层串起来。
