# Roadmap

这份文档是当前唯一有效的执行计划。

旧的 `Notes/*.md` 已全部废弃。如果某个计划不在这里，就把它视为未定方向。

## 1. 北极星

项目的核心不是“发一个 NFT 就结束”。

当前已经明确的产品北极星是：

> 以私信为中心的 P2P 社交交易系统。用户先通过签到与开盒拥有盲盒和藏品，再在交流中协商、展示、议价、互换，NFT 只是其中一条可信资产证明路径。

## 2. 已完成基础

这些已经不再是规划，而是已落地事实：

- 真实 MetaMask 路线打通
- mock `window.ethereum` 移除
- `pw-manual` 与 `mcp-headed` 收敛到唯一 shared profile
- 用户手工导入钱包后可长期复用
- 开盒与 mint 已解耦
- reply 右侧 showcase 第一版已完成
- proof modal 第一版已完成
- PoW 稀有度阈值已调高
- phrase pool 已扩充
- `akashgen` 1 小时自动退出问题已修掉
- **Barter proposals 集成到私信线程** (Phase 2, 4, 5 已完成)
- **多资产协商模型** - 支持盲盒+藏品混合交换,双向多件资产打包
- **Locale 管理** - zh-Hans 规范化,新增 locale 管理脚本
- **Legacy Trade 模型已退役** - 运行时路径、测试、前端组件全部移除

## 3. 当前优先级顺序

### Phase 1: 提交并收口当前证明链

状态：
**已完成**

已落地：

- proof modal、proof API、proof tests 已收口
- "应用 -> 链上 -> metadata -> image"四层证据链已成为稳定基线
- `make mcp-proof` 稳定通过
- proof modal 中四层信息完整可见

### Phase 2-5: Barter 系统集成到私信

状态：
**已完成** (Phase 2, 4, 5 合并完成)

已落地：

- `flarum/messages` 私信扩展已启用并集成
- `BarterProposal` 多资产协商模型已实现
- 支持盲盒+藏品混合交换,双向多件资产打包
- `BarterThreadPanel` 在私信线程中展示协商历史
- `BarterComposerPanel` 资产选择器和提案创建
- `BarterProposalCard` 提案摘要卡片
- 状态机支持提案、接受、拒绝、取消、结算
- Legacy `Trade` 模型已完全退役

验收已通过：

- 用户可在私信线程中创建 barter proposal
- 支持多轮协商(创建新提案覆盖旧提案)
- 接受后自动结算资产转移
- 协商历史可回溯查看

### Phase 3: Blind box 视觉化资产卡片

状态：
**部分完成**

已落地：

- `BlindBoxInventory` 组件已实现
- 盲盒在个人资产页面可见
- 显示 `type`、`seed`、`status`、`rarity`
- 支持分阶段开盒流程(鉴定 -> 开盒)

待完善：

- 更丰富的视觉设计和动画
- 在 barter composer 中的卡片展示优化

## 4. 证明与演示标准

后续所有“NFT 已经成立”的演示，都按四层证据链执行：

1. 应用层
   collectible detail / proof modal
2. 链上
   `ownerOf(tokenId)` 与 `tokenURI(tokenId)`
3. metadata
   metadata JSON 可访问且字段正确
4. image
   image CID 可访问

MetaMask NFT 标签页不是强制验收项。

## 5. 明确暂不做的事

- 不自动导入钱包
- 不接管用户日常 Chrome
- 不把 mint 再次塞回开盒流程
- 不为了兼容历史噪音而继续保留多份说明文档
- 不把当前窄 `Trade` 模型硬扛到私信 barter 场景里

## 6. 一句话总结

后面的重点不是“再补一个 NFT 小功能”，而是把：

- blind box
- collectible
- proof
- private messages
- barter

收敛成一套一致的资产与社交交易系统。
