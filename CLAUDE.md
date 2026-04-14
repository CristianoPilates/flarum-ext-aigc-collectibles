# AIGC Collectibles Architecture Guide

这份文档只定义三类内容：

- 当前产品模型
- 必须遵守的代码结构
- 必须遵守的测试与演进规则

运行方式看 `Manul.md`，当前现场看 `AGENT_HANDOFF.md`，执行计划看 `ROADMAP.md`。

## 1. 当前产品模型

项目不是“单纯 NFT 展示站”。

当前更准确的定位是：

> 一个 Flarum 扩展：用户通过签到获得盲盒，开盒生成 AIGC 藏品，藏品可展示、可选择性 mint 为 ERC-721，并最终朝着私信里的 P2P 社交交易演进。

当前已经成立的实体：

- `BlindBox`
  一等资产，不应只作为数字余额被理解
- `Collectible`
  开盒后生成的藏品，应用层主实体
- `Showcase`
  用户在帖子 / 回复中的展示能力
- `Proof`
  应用、链上、metadata、image 四层证据链
- `NFT`
  可选链上投射，不等于整个产品本体

当前已经落地的新实体:

- `BarterProposal`
  多资产协商模型,支持盲盒+藏品混合交换,已集成到私信线程
- `BarterProposalItem`
  提案中的资产明细,支持双向多件资产打包
- `BarterThreadPanel`
  私信线程中的协商历史展示

当前尚未完成但方向已定的部分:

- blind box 的完整视觉化资产卡片
- 更丰富的协商交互(还价、计数器报价)

## 2. 当前技术现实

- Forum engine:
  Flarum 2.x
- Frontend:
  Mithril.js + Flarum frontend extension pattern
- Backend:
  PHP 8.2 + Flarum resource / command / service pattern
- DB:
  MySQL
- Chain:
  Anvil, chain id `31337`
- Wallet:
  真实 MetaMask unpacked extension
- Storage:
  本地 Kubo IPFS

当前真实 NFT 路径的事实：

- MetaMask 已真实接入
- mint 仍由后端 minter wallet 发交易
- NFT 归属到用户绑定地址
- MetaMask 是否展示 NFT，不是唯一验收标准

## 3. 仓库结构心智模型

关键入口：

- `extend.php`
  扩展注册入口
- `js/src/forum.ts`
  forum 前端入口
- `src/Api/Resource/*`
  JSON:API resource 定义与 endpoint 入口
- `src/Command/*`
  命令与 handler
- `src/Service/*`
  业务逻辑
- `src/Model/*`
  持久化模型
- `tests/unit/*`
  纯服务级测试
- `tests/integration/*`
  资源到数据库的链路测试

推荐的阅读顺序：

1. Resource
2. Command / Handler
3. Service interface
4. Service implementation
5. Model
6. Integration test

## 4. 必须遵守的后端规则

### 4.1 变更入口必须清晰

所有有状态变更的 API，都应遵守：

`Resource -> Command -> Handler -> Service`

不要把业务逻辑直接塞进 Resource endpoint closure。

### 4.2 依赖注入必须面向接口

新增 service 时：

- 在 `src/Service/Contracts/` 定义接口
- 在 `CollectibleServiceProvider` 里绑定
- Handler / Job 只依赖接口

不要直接把 concrete service type-hint 到所有调用点。

### 4.3 交易、余额、所有权变更必须走事务

凡是改这些状态：

- blind box 数量或状态
- collectible 所有权
- trade / barter 状态

都必须用数据库事务保护。涉及并发争用时，加行级锁。

### 4.4 状态名必须保持一致

当前代码里已有的状态字符串与 rarity 字符串是隐式协议。

新增逻辑时不要：

- 自创相近但不同的字符串
- 在前后端各写一套不一致的命名

### 4.5 不要吞异常

允许降级，不能静默。

如果 catch 了异常，至少要：

- 写日志
- 或显式转成用户可理解的错误

### 4.6 不要把 Web3 mock 再混回产品代码

当前真实 MetaMask 路线已经打通。

不要再为图省事：

- 注入 fake `window.ethereum`
- 写只服务于假 provider 的前端逻辑
- 让 smoke 路径和真实路径长期分叉

## 5. 必须遵守的前端规则

### 5.1 继续使用 Flarum / Mithril 习惯用法

- 扩展现有组件优先于整块重写
- 模型经 `app.store` 注册与读取
- 状态变化后显式 `m.redraw()`

### 5.2 展示类 UI 不要退化成“小徽章思维”

关于 collectible / blind box 展示，当前产品共识已经变了：

- 不是把 badge 做得更大
- 而是把资产做成独立、可辨认、可点击的展示单元

### 5.3 mint 必须是用户主动动作

前端流程不能再次把：

- 开盒
- 生成
- mint

揉成一个“一路自动到底”的动作。

### 5.4 proof UI 比钱包 UI 更权威

本地链 + 自定义网络 + IPFS metadata 的 NFT，在 MetaMask 中的显示并不稳定。

因此前端应该优先维护：

- 应用层 proof
- 链上 proof
- metadata proof
- image proof

而不是把 MetaMask NFT gallery 当主展示位。

## 6. 当前测试策略

只保留三种清晰测试层：

### 6.1 Unit

目标：

- 验证纯服务逻辑
- 用 fake / mock 隔离外部依赖

### 6.2 Integration

目标：

- 验证 API -> Handler -> Service -> DB 整条调用链

优先给这些垂直链路补 integration test：

- check-in
- blind box appraise / open
- wallet bind
- mint
- proof
- future barter settlement

### 6.3 Headed Playwright MCP

目标：

- 验证真实 GUI
- 验证真实 MetaMask
- 验证用户可见行为

不要用 headless smoke 冒充这类验收。

## 7. 演进规则

### 7.1 新行为先补测试

不要求形式主义 TDD，但要求最小纪律：

1. 先写失败测试，或先明确补哪条 guardrail
2. 写最小实现
3. 跑通后再收敛命名和结构

### 7.2 小修可以直接做，大改先写计划

以下情况直接改即可：

- 单点 bug fix
- 文案 / 结构小调整
- 已有模式内的小扩展

以下情况先写到 `ROADMAP.md` 再动：

- 新领域模型
- 新的资产流转规则
- 改 API 资源边界
- 改用户心智或核心交互

### 7.3 先删旧语义，再加新语义

如果旧模型已经偏题，不要靠“再补一层兼容抽象”拖着走。

用户当前更偏好：

- 干净收敛
- 少历史包袱
- 少认知噪音

## 8. 当前明确的产品边界

### 8.1 已做成的

- blind box 与 collectible 的主流程
- 真实 MetaMask 绑定
- 用户主动 mint
- reply 右侧 showcase 第一版
- proof modal 第一版

### 8.2 还没做成的

- 基于私信的完整社交交易
- 多盲盒 / 多藏品 / 双向交换的 barter 模型
- blind box 的可视化资产卡片

### 8.3 不要误判的

- MetaMask 不显示 NFT，不等于 mint 失败
- IPFS WebUI `Files` 为空，不等于内容没进 IPFS

## 9. 一句话总结

写这个项目时，优先级永远是：

1. 保持领域边界清晰
2. 保持真实 GUI 路径可信
3. 保持文档与当前事实一致
4. 不为历史遗留噪音继续加新噪音
