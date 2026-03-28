# Showcase And Proof Plan

这份文档是执行计划，不是 handoff，也不是纯使用说明。

目的：

- 让接下来几轮工作有明确的阶段边界
- 让用户能监督每一步是否偏题
- 让后续 agent 知道哪些事情是“已定方向”，哪些还只是备选

---

## Execution Snapshot

截至当前会话：

- `Phase B` 的“右侧展柜第一版”已经实现
- 已补一个专门的 headed MCP 验收入口：
  `make mcp-showcase`
- 它会在真实 GUI 里完成：
  - 登录
  - 选定现有 completed collectible 为 showcase
  - 创建 discussion + reply
  - 进入真实 discussion/reply 页面
  - 截图并断言右侧展柜存在
  - 点击展柜打开 detail modal
- 最近一次验收结果：
  - showcase collectible: `#44`
  - discussion id: `3`
  - layout: `502px 210px`
  - screenshots:
    - `.devenv/state/playwright-mcp-output/showcase-discussion.png`
    - `.devenv/state/playwright-mcp-output/showcase-modal.png`

---

## 0. 当前共识

已经确认的方向：

1. 藏品 mint 必须保持“用户主动触发”
2. “只开盒抽样”和“mint NFT”要彻底分开
3. 帖子展示不应继续停留在小 badge
4. 条带 / 透明背景图会损失藏品细节，不是当前首选
5. 更合适的第一版是：每条 reply 右侧固定 showcase 区域
6. NFT 的证明不应只依赖 MetaMask UI，要用“应用页 + 链上 + metadata + IPFS”四层证据链
7. IPFS 演示以 `8888` 网关为主，`5001` API 为辅

---

## 1. 阶段拆分

### Phase A: 抽样路径与 mint 脱钩

目标：

- 让开发/测试可以反复开盒，不顺手 mint
- 降低调分布、调 phrase、调 AIGC 效果时的成本和干扰

输出：

- 一个新的 MCP CLI 脚本，例如：
  `scripts/playwright/mcp-cli/mcp-sample-blindbox.cjs`

功能边界：

- 登录
- 打开盲盒
- 等待 collectible 完成
- 返回：
  - `collectibleId`
  - `blindBoxId`
  - `budget`
  - `rarity`
  - `aigcPrompt`
  - `ipfsCid`
  - `metadataCid`

不做：

- 钱包绑定
- mint
- MetaMask confirm

验收：

- 连续运行多次不会污染 tokenId
- 能直接用于观察 rarity 分布与 prompt 多样性

---

### Phase B: 右侧展柜第一版

目标：

- 把当前帖子里的小 badge 升级为真正可见的 showcase
- 每条 reply 都能展示，不依赖主题帖
- 不遮挡文字，不丢细节

当前推荐方案：

- 桌面端把每条 `CommentPost` 内容区改成双栏
- 左侧：正文内容
- 右侧：固定宽度 showcase panel
- 移动端降级成上下堆叠

为什么不做背景图：

- 细节损失大
- 对文字可读性干扰更强
- 更像氛围层，不像“展示柜”

为什么不做顶部条带：

- 仍会压缩图片信息
- 对 reply 的持续展示感不如右侧柜体

### Phase B.1 结构策略

技术上优先考虑：

- 扩展 `CommentPost` 内容结构
- 在 comment 内容区域插入一个并列的 showcase 容器
- 不去做真正复杂的 `shape-outside`
- 不去重写整套 Flarum post 模板

第一版 DOM 目标：

- `.CollectibleShowcasePost`
- `.CollectibleShowcasePost-body`
- `.CollectibleShowcasePost-content`
- `.CollectibleShowcasePost-panel`

右侧 panel 内容建议：

- 藏品图
- 名称
- rarity
- `NFT #tokenId`（若已 mint）
- 点击打开 detail modal

### Phase B.2 视觉目标

第一版要达到：

- 一眼看得出这是“藏品展示”，不是头像挂件
- 保留图像细节
- 不压坏正文阅读
- 不过度喧宾夺主

第一版不要追求：

- 动态背景特效
- 复杂 hover 动画
- 过重装饰框

推荐尺寸：

- 桌面端右栏：`180px - 220px`
- 图片比例：正方形或轻微竖卡
- 文字信息控制在 2-3 行

### Phase B.3 风险点

需要关注：

- 长楼层正文在窄窗口下的可读性
- 引用块 / 图片贴 / 代码块与双栏布局的兼容
- Post controls / footer 在双栏下是否错位
- 移动端是否需要彻底下沉展示区

验收：

- reply 页面中，每条展示贴都能稳定看到右侧展柜
- 正文不会被遮挡
- showcase 点击可进 detail
- 移动端能自然堆叠

---

### Phase C: NFT 证明页 / 证明流程

目标：

- 让用户能拿一条清晰的 GUI 证据链去展示“这个 NFT 已经在我的钱包地址下”

证据链设计：

1. 应用 detail 页：
   - `Token ID`
   - `metadataCid`
   - `ipfsCid`
2. 链上 proof：
   - `ownerOf(tokenId)`
   - `tokenURI(tokenId)`
3. metadata JSON：
   - `name`
   - `image`
   - `attributes`
4. image 页面：
   - 打开实际图片

说明：

- 当前 mint 是后端 owner/minter wallet 发交易
- 归属证明靠 `ownerOf(tokenId)`，不是“用户亲自在 MetaMask 里点了发送”

后续可选输出：

- 一个“Proof”按钮
- 一个最小 proof modal
- 一个 CLI / PHP debug 脚本

验收：

- 对任意已 mint 的 collectible，可以从应用数据走到链上 owner 与 tokenURI
- tokenURI 与 metadataCid 对得上
- metadata JSON 与 image CID 对得上

---

### Phase D: IPFS 演示页 / 验收链

目标：

- 让用户可以打开网关直接说“看，这些内容就在 IPFS 上”

演示优先顺序：

1. 网关：
   - `http://127.0.0.1:8888/ipfs/<metadataCid>`
   - `http://127.0.0.1:8888/ipfs/<ipfsCid>`
2. API：
   - `POST /api/v0/cat?arg=<cid>`
   - `POST /api/v0/id`

原因：

- `8888` 更适合人看
- `5001` 更适合工程验收

后续可选输出：

- 应用内 “Open Proof Bundle” 按钮
- 文档里提供一套固定演示顺序

---

## 2. 实施顺序建议

推荐顺序：

1. `Phase A`
   先把抽样路径和 mint 脱钩
2. `Phase B`
   上线右侧展柜第一版
3. `Phase C`
   补 NFT 证明链
4. `Phase D`
   补 IPFS 演示链

原因：

- `A` 会提高后续开发效率
- `B` 是最直接的用户感知提升
- `C/D` 是演示和验收闭环

---

## 3. 当前已具备的基础

已经具备：

- 真实 MetaMask + 唯一 shared profile
- headed MCP 真实 GUI 验证通
- 最小 NFT 路径已跑通一次
- `GenerateCollectibleJob` 已去掉 auto-mint
- PoW rarity 映射已校准
- phrase pool 已扩容
- 相关 blind box tests 已更新并通过

这意味着：

- 现在做 `Phase A/B` 的风险已经明显低于前几轮

---

## 4. 完成定义

### “展柜第一版完成”的定义

- 小 badge 不再是主展示
- 每条 reply 都有清晰可见的 showcase 区
- showcase 可点击查看详情
- 桌面 / 移动端都可用
- headed MCP 能看到真实 GUI 效果

### “NFT 证明完成”的定义

- 用户可从应用页看到 tokenId
- 可以查到 ownerOf(tokenId)
- 可以查到 tokenURI(tokenId)
- tokenURI 对应 metadata JSON
- metadata JSON 对应 image CID

### “IPFS 演示完成”的定义

- 用户知道 `8888` 是主演示入口
- 用户能直接打开 metadata 和 image
- 工程侧能通过 `5001` API 验证内容确实存在

---

## 5. 明确不做

这一轮不优先做：

- 复杂发光边框 / 稀有度动态动画
- MetaMask UI 驱动型 NFT gallery 兼容修复
- 真正意义上的链上浏览器替代品
- 将 mint 强制改为“必须用户钱包签交易”

---

## 6. 立即下一步

如果没有新方向变更，下一步直接开始：

- 实现 `Phase B`：
  “右侧展柜第一版”

同时保持：

- 用 `mcp-headed` 做真实 GUI 监督
- 不再把展示逻辑做回 badge-only
