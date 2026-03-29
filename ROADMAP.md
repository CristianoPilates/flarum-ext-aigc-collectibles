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

## 3. 当前优先级顺序

### Phase 1: 提交并收口当前证明链

状态：
进行中

目标：

- 把 proof modal、proof API、proof tests、文档清理一起收口
- 让“应用 -> 链上 -> metadata -> image”四层证据链变成稳定基线

验收：

- `make mcp-proof` 稳定通过
- proof modal 中四层信息完整可见
- 至少有一组已 mint 样本能从应用一路跳到 IPFS 内容

### Phase 2: 启用 `flarum/messages`

状态：
计划中

目标：

- 正式启用私信扩展
- 建立后续 barter / negotiation 的真实承载场景

为什么先做它：

- 用户真正关心的是“在私信里交流并交易”
- 没有私信上下文，后面的交易模型会一直悬空

验收：

- 论坛侧能正常进入私信
- 扩展不会破坏现有 collectible / blind box 功能
- 后续可在 message thread 中挂接资产展示与提案 UI

### Phase 3: 让 blind box 成为可见的一等资产

状态：
计划中

目标：

- 盲盒不再只是数字余额
- 为 `checkin_reward` 盲盒做统一视觉卡片
- 显示 `type`、`seed`、`status`、`budget`

为什么重要：

- 用户已经明确指出 blind box 是核心实体
- 如果它在 UI 里只是计数器，后续交易、展示、议价都没有抓手

第一版建议：

- 在个人资产区加入 blind box 卡片
- 保留 seed 可见性
- 不急着做复杂动画，先把资产感做出来

验收：

- 用户能明确区分“未鉴定 / 已鉴定 / 已开盒”
- seed 在 UI 可见
- 后续私信交易 UI 可以直接复用这张卡片

### Phase 4: 重做 barter 领域模型

状态：
计划中

目标：

- 从当前狭窄的 `Trade` 模型升级到真正的多资产协商模型

当前模型的问题：

- 偏固定 buyer / seller 结构
- 偏“盲盒换单个 collectible”
- 不支持双方各自挑选资产并反复议价

目标模型应支持：

- 任意一方先发起
- 只聊天不交易
- 一方报价，另一方还价
- 盲盒换盲盒
- 藏品换藏品
- 盲盒加藏品混合交换
- 双方多件资产打包交换

这不是小修，应该拆成单独设计与实现阶段。

验收：

- 新模型不再依赖“买家 / 卖家”硬编码心智
- 资产明细可以双向表达
- 状态机能描述提案、还价、接受、取消、结算

### Phase 5: 把交易真正嵌入私信

状态：
计划中

目标：

- 在私信线程里完成展示、挑选、报价、还价、成交

最低可用版本应包含：

- 资产 picker
- 提案摘要卡片
- 对方可视化还价
- 成交后资产转移结果可回看

验收：

- 用户不离开私信就能完成一次完整 barter
- 交易记录可以回溯
- 资产状态变化与 UI 状态一致

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
