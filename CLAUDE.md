# AIGC Blind Box Digital Collectible Generation & Circulation System

> A Flarum forum extension (`donk/flarum-ext-aigc-collectibles`) that integrates daily check-in, AIGC-powered blind box collectible generation, IPFS storage, P2P trading, and optional ERC-721 NFT minting.

## Project Identity

- **Extension ID**: `donk-aigc-collectibles`
- **Namespace**: `Donk\AigcCollectibles`
- **Frontend entry**: `js/src/forum.ts`, `js/src/admin.ts`
- **Backend entry**: `extend.php`
- **Flarum version**: 2.0+
- **PHP**: ^8.2
- **License**: MIT

## Core Concepts

| Concept                  | Description                                                                                                                                                              |
| ------------------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| **Blind Box (盲盒)**     | Virtual currency earned via daily check-in. Spent to generate collectibles. Also used as trading currency in P2P exchanges.                                              |
| **Collectible (藏品)**   | AIGC-generated digital artwork. Stored on IPFS. Displayed next to user posts. Has rarity level. Optionally minted as ERC-721 NFT.                                        |
| **Rarity (稀有度)**      | Common (60%), Rare (25%), Epic (12%), Legendary (3%). Determined by weighted random roll on generation.                                                                  |
| **Trade (交易)**         | P2P exchange: Buyer offers N blind boxes for Seller's specific collectible. No marketplace, no fiat currency.                                                            |
| **Showcase (展示)**      | Each user can select one collectible to display beside their posts (like an enhanced badge/avatar frame).                                                                |
| **IPFS without Pinning** | Images uploaded to IPFS but intentionally NOT pinned. Unpopular collectibles may naturally "disappear" over time via IPFS garbage collection, creating organic scarcity. |

## Tech Stack

| Layer         | Technology                                                            |
| ------------- | --------------------------------------------------------------------- |
| Forum Engine  | Flarum 2.0+ (PHP, Laravel 12 components)                              |
| Backend ORM   | Eloquent (Active Record pattern via `Flarum\Database\AbstractModel`)  |
| API Protocol  | JSON:API (via Flarum Resources + Endpoints, based on tobyz/json-api-server) |
| Frontend      | Mithril.js (Flarum's built-in frontend framework)                     |
| Database      | MySQL                                                                 |
| AIGC          | External API (DALL-E / Stable Diffusion / compatible service)         |
| Image Storage | IPFS (Pinata API or self-hosted node, HTTP Gateway)                   |
| Blockchain    | Local EVM chain (Hardhat/Ganache for dev), ERC-721 smart contract     |
| Real-time     | WebSocket (for trade notifications, generation completion)            |
| Queue         | Laravel Queue (for async AIGC generation + IPFS upload + NFT minting) |
| Wallet        | MetaMask (EVM only, no Dotsama/Substrate support)                     |

---

## Architecture Diagram (系统架构图)

```plantuml
@startuml System_Architecture
!theme plain
skinparam componentStyle rectangle
skinparam packageStyle frame
skinparam linetype ortho
skinparam nodesep 60
skinparam ranksep 50
skinparam defaultFontSize 12
skinparam packageFontSize 14
skinparam componentFontSize 11

title AIGC Blind Box Digital Collectible System — Architecture

package "Browser Client" as browser <<Cloud>> {
    package "Flarum Frontend (Mithril.js)" as frontend {
        package "Extension Frontend Components" as fe_ext #E8F5E9 {
            component [CheckinButton] as cb
            component [BlindBoxOpener] as bbo
            component [CollectibleGallery] as cg
            component [CollectibleCard] as cc
            component [TradePanel] as tp
            component [TradeRequestModal] as trm
            component [WalletConnector] as wc
        }
        component [Flarum Forum UI\n(Discussion, Posts, UserProfile)] as flarum_ui #BBDEFB
        component [Flarum JS Extender\n(extend.js)] as js_extender #BBDEFB
    }
    component [MetaMask\nBrowser Extension] as metamask #FFE0B2
}

package "Communication Layer" as comm_layer {
    interface "JSON:API\n(REST/HTTPS)" as jsonapi
    interface "WebSocket\n(Real-time)" as ws
    interface "EIP-1193\nProvider API" as eip1193
}

package "Flarum Application Server (PHP)" as server {
    component [Flarum Core Engine] as flarum_core #BBDEFB
    component [IoC Container (Laravel)] as ioc #BBDEFB
    component [Extender Registry\n(extend.php)] as extender_reg #BBDEFB
    component [JSON:API Layer\n(Resources, Endpoints)] as api_layer #BBDEFB
    component [Event Dispatcher] as event_disp #BBDEFB
    component [Queue Worker\n(Laravel Queue)] as queue_worker #BBDEFB

    package "Extension Backend Modules" as be_ext #E8F5E9 {
        component [CheckinService] as checkin_svc
        component [BlindBoxService] as bb_svc
        component [CollectibleService] as col_svc
        component [TradeService] as trade_svc
        component [Web3Service] as web3_svc
        component [AIGCService] as aigc_svc
        component [IPFSService] as ipfs_svc
        component [GenerateCollectibleJob\n(Queued)] as gen_job
    }
}

database "MySQL Database" as mysql {
    component [users (extended)] as tbl_users
    component [collectibles] as tbl_col
    component [trades] as tbl_trades
    component [checkin_records] as tbl_checkin
    component [web3_accounts] as tbl_web3
    component [collectible_events] as tbl_events
}

package "External Services" as external {
    cloud "AIGC API\n(DALL-E / SD)" as aigc_api #FFF9C4
    cloud "IPFS Node\n+ HTTP Gateway" as ipfs_node #E1BEE7
    cloud "EVM Blockchain\n(Hardhat / Ganache)" as evm_chain #FFE0B2 {
        component [ERC-721\nCollectible Contract] as nft_contract
    }
}

js_extender -down-> flarum_ui : extends UI
fe_ext -up-> js_extender : registered via
fe_ext -down-> jsonapi : HTTP requests
fe_ext <-down- ws : push notifications
wc -down-> eip1193 : wallet interaction
eip1193 -left-> metamask : sign messages
jsonapi -down-> api_layer
ws -down-> event_disp
extender_reg --> flarum_core : registers extension
ioc --> be_ext : dependency injection
checkin_svc --> bb_svc : awards blind boxes
bb_svc --> gen_job : dispatches async job
gen_job --> aigc_svc : requests image
gen_job --> ipfs_svc : uploads image
trade_svc --> bb_svc : transfers balance
trade_svc --> col_svc : transfers ownership
col_svc --> web3_svc : mints NFT
queue_worker --> gen_job : executes
be_ext -down-> mysql : Eloquent ORM
aigc_svc -down-> aigc_api : HTTPS
ipfs_svc -down-> ipfs_node : IPFS HTTP API
web3_svc -down-> evm_chain : JSON-RPC

legend bottom left
  |= Color |= Meaning |
  | <#BBDEFB> | Flarum Core / Framework |
  | <#E8F5E9> | Extension Modules (our code) |
  | <#FFF9C4> | AI Generation Service |
  | <#E1BEE7> | Decentralized Storage |
  | <#FFE0B2> | Web3 / Blockchain |
endlegend

@enduml
```

---

## Use Case Diagram (按角色拆分用例图)

```plantuml
@startuml Use_Cases

left to right direction
skinparam actorStyle awesome
skinparam packageStyle rectangle
skinparam usecaseBackgroundColor<<common>> #DDFFD6
skinparam usecaseBackgroundColor<<admin>> #FFE0B2
skinparam usecaseBackgroundColor<<system>> #B3E5FC
skinparam usecaseBorderColor #555555
skinparam packageBorderColor #333333
skinparam packageFontStyle bold
skinparam arrowColor #666666
skinparam defaultFontSize 12

actor "论坛用户\n(Regular User)" as User
actor "管理员\n(Admin)" as Admin
actor "系统自动化\n(System)" as System

package "签到与盲盒\n(Check-in & Blind Box)" as PKG_CHECKIN {
    usecase "每日签到" as UC_CHECKIN <<common>>
    usecase "领取盲盒奖励" as UC_REWARD <<common>>
    usecase "查看盲盒余额" as UC_BALANCE <<common>>
    usecase "开启盲盒" as UC_OPEN <<common>>
    usecase "触发AIGC生成" as UC_TRIGGER_AIGC <<system>>
    usecase "随机决定稀有度" as UC_RARITY <<system>>
    usecase "每日签到重置" as UC_RESET <<system>>
}

package "藏品管理\n(Collectible Management)" as PKG_COLLECT {
    usecase "查看个人藏品库" as UC_GALLERY <<common>>
    usecase "查看藏品详情" as UC_DETAIL <<common>>
    usecase "设置展示藏品" as UC_SHOWCASE <<common>>
    usecase "帖子旁展示藏品" as UC_DISPLAY <<common>>
    usecase "浏览他人藏品" as UC_BROWSE <<common>>
    usecase "查看所有权历史" as UC_HISTORY <<common>>
}

package "P2P交易\n(P2P Trading)" as PKG_TRADE {
    usecase "发起交易请求" as UC_INIT_TRADE <<common>>
    usecase "接收交易请求" as UC_RECV_TRADE <<common>>
    usecase "接受交易" as UC_ACCEPT <<common>>
    usecase "拒绝交易" as UC_REJECT <<common>>
    usecase "取消交易请求" as UC_CANCEL <<common>>
    usecase "查看交易历史" as UC_TRADE_HIST <<common>>
    usecase "扣减/转移盲盒" as UC_TRANSFER <<system>>
    usecase "转移藏品所有权" as UC_TRANSFER_OWN <<system>>
}

package "Web3钱包\n(Web3 Wallet)" as PKG_WALLET {
    usecase "绑定EVM钱包" as UC_BIND <<common>>
    usecase "解绑EVM钱包" as UC_UNBIND <<common>>
    usecase "MetaMask签名验证" as UC_METAMASK <<common>>
    usecase "链上NFT铸造" as UC_MINT_NFT <<system>>
}

package "系统管理\n(Administration)" as PKG_ADMIN {
    usecase "配置签到奖励" as UC_CFG_CHECKIN <<admin>>
    usecase "配置AIGC API" as UC_CFG_AIGC <<admin>>
    usecase "配置IPFS节点" as UC_CFG_IPFS <<admin>>
    usecase "配置区块链" as UC_CFG_CHAIN <<admin>>
    usecase "配置稀有度概率" as UC_CFG_RARITY <<admin>>
    usecase "查看系统统计" as UC_STATS <<admin>>
    usecase "管理提示词/主题" as UC_THEMES <<admin>>
    usecase "启用/禁用功能" as UC_TOGGLE <<admin>>
    usecase "审查管理交易" as UC_MOD_TRADE <<admin>>
    usecase "铸造特别藏品" as UC_SPECIAL <<admin>>
}

package "系统后台任务\n(Background Tasks)" as PKG_SYSTEM {
    usecase "AIGC图像生成" as UC_AIGC <<system>>
    usecase "IPFS上传" as UC_IPFS <<system>>
    usecase "WebSocket通知" as UC_WS <<system>>
    usecase "IPFS可用性检查" as UC_IPFS_CHECK <<system>>
}

User --> UC_CHECKIN
User --> UC_BALANCE
User --> UC_OPEN
User --> UC_GALLERY
User --> UC_DETAIL
User --> UC_SHOWCASE
User --> UC_BROWSE
User --> UC_INIT_TRADE
User --> UC_RECV_TRADE
User --> UC_ACCEPT
User --> UC_REJECT
User --> UC_CANCEL
User --> UC_TRADE_HIST
User --> UC_BIND
User --> UC_UNBIND

Admin --> UC_CFG_CHECKIN
Admin --> UC_CFG_AIGC
Admin --> UC_CFG_IPFS
Admin --> UC_CFG_CHAIN
Admin --> UC_CFG_RARITY
Admin --> UC_STATS
Admin --> UC_THEMES
Admin --> UC_TOGGLE
Admin --> UC_MOD_TRADE
Admin --> UC_SPECIAL
Admin --|> User

System --> UC_RESET
System --> UC_AIGC
System --> UC_IPFS
System --> UC_MINT_NFT
System --> UC_WS
System --> UC_IPFS_CHECK
System --> UC_TRANSFER
System --> UC_TRANSFER_OWN

UC_CHECKIN ..> UC_REWARD : <<include>>
UC_OPEN ..> UC_TRIGGER_AIGC : <<include>>
UC_TRIGGER_AIGC ..> UC_RARITY : <<include>>
UC_TRIGGER_AIGC ..> UC_AIGC : <<include>>
UC_AIGC ..> UC_IPFS : <<include>>
UC_AIGC ..> UC_WS : <<include>>
UC_ACCEPT ..> UC_TRANSFER : <<include>>
UC_ACCEPT ..> UC_TRANSFER_OWN : <<include>>
UC_BIND ..> UC_METAMASK : <<include>>
UC_SHOWCASE ..> UC_DISPLAY : <<include>>
UC_DETAIL ..> UC_HISTORY : <<include>>
UC_SPECIAL ..> UC_AIGC : <<include>>

note bottom of PKG_TRADE
  纯P2P: 用N个盲盒交换指定藏品
  无市场, 无法币
end note

note right of PKG_SYSTEM
  所有生成与上链操作
  通过异步队列执行
end note

@enduml
```

---

## System Module Division (系统模块划分示意图)

```plantuml
@startuml Module_Division
!theme plain
skinparam packageStyle frame
skinparam linetype ortho
skinparam nodesep 40
skinparam ranksep 35
skinparam defaultFontSize 11
skinparam packageFontSize 13

title Module Division — Extension Internal Structure

package "Entry Points" as entry #E3F2FD {
    class "extend.php" as extend_php <<Backend>> {
        Frontend, Routes, Model
        ApiSerializer, ServiceProvider
        Event subscriber, Console
    }
    class "extend.js" as extend_js <<Frontend>> {
        app.initializers
        extend(PostUser)
        extend(UserPage)
    }
}

package "Backend (PHP)" as backend #E8F5E9 {

    package "Access" as access #C8E6C9 {
        class "CollectiblePolicy"
        class "TradePolicy"
        class "CheckinPolicy"
    }

    package "Api" as api #C8E6C9 {
        package "Resource" {
            class "CollectibleResource" { type: "collectibles" }
            class "TradeResource" { type: "trades" }
            class "CheckinRecordResource" { type: "checkin-records" }
            class "Web3AccountResource" { type: "web3-accounts" }
            class "CollectibleEventResource" { type: "collectible-events" }
        }
    }

    package "Command (CQRS)" as command #C8E6C9 {
        class "Checkin + Handler"
        class "OpenBlindBox + Handler"
        class "CreateTrade + Handler"
        class "AcceptTrade + Handler"
        class "CancelTrade + Handler"
        class "BindWallet + Handler"
        class "MintCollectible + Handler"
    }

    package "Event" as event #C8E6C9 {
        class "CheckedIn"
        class "BlindBoxOpened"
        class "CollectibleGenerated"
        class "TradeCreated"
        class "TradeCompleted"
        class "WalletBound"
        class "CollectibleMinted"
    }

    package "Job (Queue)" as job #C8E6C9 {
        class "GenerateCollectibleJob" {
            handle(AIGCService, IPFSService)
        }
    }

    package "Model" as model #C8E6C9 {
        class "Collectible" { rarity, ipfs_cid, token_id }
        class "Trade" { status, offered_boxes }
        class "CheckinRecord" { checked_in_at }
        class "Web3Account" { address, type }
        class "CollectibleEvent" { event_type }
    }

    package "Repository" as repository #C8E6C9 {
        class "CollectibleRepository"
        class "TradeRepository"
        class "CheckinRepository"
    }

    package "Service" as service #C8E6C9 {
        class "AIGCService" { generateImage(prompt) }
        class "IPFSService" { upload(data): CID }
        class "BlockchainService" { mintNFT(), verifySignature() }
        class "CheckinService" { performCheckin(user) }
        class "BlindBoxService" { award(), spend(), balanceOf() }
        class "TradeService" { createOffer(), acceptTrade() }
    }

    package "Validator" as validator #C8E6C9 {
        class "TradeValidator"
        class "Web3LoginValidator"
        class "CheckinValidator"
    }

    package "Provider" as provider #C8E6C9 {
        class "CollectibleServiceProvider" {
            register(), boot()
        }
    }

    package "Migration" as migration #C8E6C9 {
        class "create_collectibles_table"
        class "create_trades_table"
        class "create_checkin_records_table"
        class "create_web3_accounts_table"
        class "create_collectible_events_table"
        class "add_blindbox_fields_to_users"
    }
}

package "Frontend (Mithril.js / TS)" as frontend #FFF3E0 {
    package "components" #FFE0B2 {
        class "CheckinButton"
        class "BlindBoxOpener"
        class "CollectibleCard"
        class "CollectibleGallery"
        class "TradePanel"
        class "TradeRequestModal"
        class "WalletConnector"
        class "PostCollectibleBadge"
    }
    package "models" #FFE0B2 {
        class "Collectible" as fm_col
        class "Trade" as fm_trade
        class "CheckinRecord" as fm_checkin
    }
    package "utils" #FFE0B2 {
        class "web3" { connectWallet(), signMessage() }
        class "ipfs" { gatewayUrl(cid) }
        class "notifications" { wsConnect() }
    }
}

extend_php -down-> provider : registers
extend_php -down-> api : defines routes
extend_php -down-> migration : runs
extend_js -down-> frontend : registers

legend bottom left
  |= Color |= Layer |
  | <#E3F2FD> | Entry Points |
  | <#C8E6C9> | Backend PHP |
  | <#FFE0B2> | Frontend TS |
endlegend

@enduml
```

---

## Core Business Flow: Daily Check-in (每日签到)

```plantuml
@startuml Flow_Checkin
title Daily Check-in & Blind Box Earning

|User (Frontend)|
start
:Click "Check-in" button;
:Send **POST /api/checkin**;

|Flarum API (Backend)|
:Authenticate user;
if (Auth valid?) then (yes)
else (no)
  :Return 401;
  stop
endif

|Database|
:Query checkin_records\nWHERE user_id AND DATE = TODAY;

|Flarum API (Backend)|
if (Already checked in?) then (yes)
  :Return 409 "Already checked in";
  |User (Frontend)|
  :Show error toast;
  stop
else (no)
endif

:Calculate reward_amount (default=1);

|Database|
:BEGIN TRANSACTION;
:INSERT checkin_records;
:UPDATE users SET\nblind_box_count += reward_amount;
:COMMIT;

|Flarum API (Backend)|
:Return 200 {blind_box_count, reward};

|User (Frontend)|
fork
  :Update balance display;
fork again
  :Disable check-in button;
fork again
  :Show success animation;
end fork

stop
@enduml
```

---

## Core Business Flow: Open Blind Box (开启盲盒 — 核心流程)

```plantuml
@startuml Flow_OpenBlindBox
title Open Blind Box → Generate Collectible (Core Flow)

|User (Frontend)|
start
:Click "Open Blind Box";
:Send **POST /api/collectibles/generate**;

|Flarum API (Backend)|
:Authenticate user;

|Database|
:SELECT blind_box_count FROM users\nWHERE id = :uid FOR UPDATE;

|Flarum API (Backend)|
if (blind_box_count >= 1?) then (yes)
else (no)
  :Return 422 "Insufficient blind boxes";
  stop
endif

:Roll rarity (weighted random:\nCommon 60%, Rare 25%,\nEpic 12%, Legendary 3%);

|Database|
:BEGIN TRANSACTION;
:UPDATE users SET blind_box_count -= 1;
:INSERT collectibles\n(user_id, rarity, status="generating");
:COMMIT;

|Flarum API (Backend)|
:Dispatch **GenerateCollectibleJob** to queue;
:Return 202 {collectible.id, status:"generating", rarity};

|User (Frontend)|
:Show "Generating..." animation\n(rarity-themed);
:Subscribe to WebSocket;

|Queue Worker|
:Pick up GenerateCollectibleJob;
:Build AIGC prompt from rarity + theme;

|AIGC API|
:POST generate image with prompt;

|Queue Worker|
if (AIGC success?) then (yes)
  :Receive image data;
else (no)
  if (Retry < 3?) then (yes)
    :Re-queue with backoff;
    stop
  else (no)
    |Database|
    :UPDATE collectible status="failed";
    :Refund blind_box_count += 1;
    |Queue Worker|
    :Notify user via WebSocket;
    stop
  endif
endif

|IPFS Node|
:Upload image → get image CID;

|Queue Worker|
:Build metadata JSON\n{name, image:"ipfs://CID",\nattributes:[{rarity}]};

|IPFS Node|
:Upload metadata → get metadata CID;

note right #FFEECC
  NOT pinned intentionally.
  IPFS GC creates natural
  scarcity over time.
end note

|Queue Worker|
if (User has wallet?) then (yes)
  |Blockchain|
  :Call ERC-721 mint(to, tokenURI);
  |Queue Worker|
  :Extract token_id from receipt;
else (no)
  :Skip minting (can mint later);
endif

|Database|
:UPDATE collectibles SET\nstatus="completed",\nipfs_cid, metadata_cid,\naigc_prompt, token_id;

|Queue Worker|
:Send WebSocket notification;

|User (Frontend)|
:Receive "collectible.ready" event;
:Fetch GET /api/collectibles/{id};
:Play reveal animation;
:Display collectible card;

stop
@enduml
```

---

## Core Business Flow: P2P Trade (P2P藏品交易)

```plantuml
@startuml Flow_Trade
title P2P Collectible Trade

|Buyer (Frontend)|
start
:Browse Seller's collectibles;
:Click "Offer Trade" on target;
:Enter blind box offer amount;
:Send **POST /api/trades**\n{to_user_id, collectible_id, offered_boxes};

|Flarum API (Backend)|
:Authenticate Buyer;

|Database|
:Load Buyer (blind_box_count);
:Load Collectible (user_id);

|Flarum API (Backend)|
if (Buyer == Owner?) then (yes)
  :Return 422 "Cannot self-trade";
  stop
else (no)
endif

if (Enough blind boxes?) then (yes)
else (no)
  :Return 422 "Insufficient";
  stop
endif

if (Collectible belongs to Seller?) then (yes)
else (no)
  :Return 422 "Wrong owner";
  stop
endif

|Database|
:INSERT trades (status="pending");

|Flarum API (Backend)|
:WebSocket notify Seller;
:Return 201 {trade.id, status:"pending"};

|Buyer (Frontend)|
:Show "Offer sent" toast;

|Seller (Frontend)|
:Receive WebSocket notification;
:View trade request detail;

if (Decision?) then (Accept)

  :Send **POST /api/trades/{id}/accept**;

  |Flarum API (Backend)|
  :Validate trade still pending;

  |Database|
  :BEGIN TRANSACTION;
  :Buyer.blind_box_count -= offered_boxes\n(check affected rows=1);

  if (Buyer still has enough?) then (yes)
  else (no)
    :ROLLBACK;
    :Return 409 "Buyer insufficient";
    stop
  endif

  :Seller.blind_box_count += offered_boxes;
  :Collectible.user_id = Buyer.id;
  :Trade.status = "accepted";
  :Cancel OTHER pending trades\nfor this collectible;
  :COMMIT;

  |Flarum API (Backend)|
  if (Both have wallets?) then (yes)
    :Dispatch TransferNFTJob;
  else (no)
  endif
  :WebSocket notify Buyer;

  |Buyer (Frontend)|
  :Celebration animation;
  :Update gallery + balance;

  |Seller (Frontend)|
  :Success toast;
  :Update balance (increased);
  stop

else (Reject)

  |Seller (Frontend)|
  :Send **POST /api/trades/{id}/reject**;

  |Database|
  :Trade.status = "rejected";

  |Flarum API (Backend)|
  :WebSocket notify Buyer;

  |Buyer (Frontend)|
  :Show "Offer rejected";
  stop

endif
@enduml
```

---

## Core Business Flow: Wallet Binding (钱包绑定)

```plantuml
@startuml Flow_WalletBinding
title Wallet Binding (MetaMask)

|User (Frontend / MetaMask)|
start
:Click "Bind Wallet";

if (MetaMask installed?) then (yes)
else (no)
  :Show "Install MetaMask" prompt;
  stop
endif

:Request MetaMask connection\neth_requestAccounts;

if (User approves?) then (yes)
  :Get wallet address;
else (no)
  :Show "Connection denied";
  stop
endif

:Send **GET /api/web3/nonce**\n?address={addr};

|Flarum API (Backend)|
:Authenticate user;

|Database|
:Check address not already bound;

|Flarum API (Backend)|
if (Already bound to another?) then (yes)
  :Return 409;
  stop
else (no)
endif

:Generate random nonce;
:Build sign message with\nnonce + domain + timestamp;

|Database|
:Store nonce (expires 5min);

|Flarum API (Backend)|
:Return {nonce, message_to_sign};

|User (Frontend / MetaMask)|
:Request personal_sign via MetaMask;

if (User signs?) then (yes)
  :Get signature;
else (no)
  :Show "Signing cancelled";
  stop
endif

:Send **POST /api/web3/verify**\n{address, signature, nonce};

|Flarum API (Backend)|
:Lookup nonce record;

if (Nonce valid & not expired?) then (yes)
else (no)
  :Return 400/410;
  stop
endif

:Recover address via ecrecover\n(PHP GMP / Elliptic Curve);

if (Recovered == submitted address?) then (yes)
else (no)
  :Return 401 "Verification failed";
  stop
endif

|Database|
:BEGIN TRANSACTION;
:DELETE nonce record;
:INSERT web3_accounts\n(user_id, address, type="evm");
:COMMIT;

|Flarum API (Backend)|
:Return 200 {address, type};

|User (Frontend / MetaMask)|
fork
  :Display wallet address (truncated);
fork again
  :Show "Unbind" option;
fork again
  :Success toast;
end fork

stop
@enduml
```

---

## Database ER Diagram (数据库 ER 图)

```plantuml
@startuml ER_Diagram

skinparam entity {
  BackgroundColor<<core>> #E3F2FD
  BorderColor<<core>> #1565C0
  BackgroundColor<<collectible>> #FFF3E0
  BorderColor<<collectible>> #E65100
  BackgroundColor<<trading>> #E8F5E9
  BorderColor<<trading>> #2E7D32
  BackgroundColor<<web3>> #F3E5F5
  BorderColor<<web3>> #6A1B9A
  BackgroundColor<<event>> #FBE9E7
  BorderColor<<event>> #BF360C
}

skinparam linetype ortho
skinparam roundcorner 8
skinparam shadowing false
skinparam defaultFontSize 12
skinparam titleFontSize 18

title Database ER Diagram

package "Core (Flarum)" #E3F2FD {
  entity "users" as users <<core>> {
    * **id** : BIGINT UNSIGNED <<PK>>
    --
    .. Flarum core columns ..
    username : VARCHAR(100)
    email : VARCHAR(150)
    ...
    --
    .. Extension columns (added) ..
    blind_box_count : INT UNSIGNED DEFAULT 0
    showcase_collectible_id : INT NULLABLE <<FK>>
    last_checkin_at : TIMESTAMP NULLABLE
  }
}

package "Collectible Domain" #FFF3E0 {
  entity "collectibles" as collectibles <<collectible>> {
    * **id** : BIGINT UNSIGNED <<PK>>
    --
    user_id : BIGINT UNSIGNED NOT NULL <<FK>>
    original_user_id : BIGINT UNSIGNED NOT NULL <<FK>>
    name : VARCHAR(255) NOT NULL
    ipfs_cid : VARCHAR(100) NULLABLE
    metadata_cid : VARCHAR(100) NULLABLE
    aigc_prompt : TEXT NULLABLE
    rarity : ENUM('common','rare','epic','legendary')
    status : ENUM('generating','completed','failed','burned')
    token_id : BIGINT UNSIGNED NULLABLE
    generation_params : JSON NULLABLE
    times_traded : INT UNSIGNED DEFAULT 0
    --
    created_at : TIMESTAMP
    updated_at : TIMESTAMP
  }

  entity "collectible_events" as events <<event>> {
    * **id** : BIGINT UNSIGNED <<PK>>
    --
    collectible_id : BIGINT UNSIGNED NOT NULL <<FK>>
    event_type : ENUM('generated','traded','burned','minted')
    from_user_id : BIGINT UNSIGNED NULLABLE <<FK>>
    to_user_id : BIGINT UNSIGNED NULLABLE <<FK>>
    trade_id : BIGINT UNSIGNED NULLABLE <<FK>>
    metadata : JSON NULLABLE
    --
    created_at : TIMESTAMP
  }
}

package "Trading Domain" #E8F5E9 {
  entity "trades" as trades <<trading>> {
    * **id** : BIGINT UNSIGNED <<PK>>
    --
    from_user_id : BIGINT UNSIGNED NOT NULL <<FK>>
    to_user_id : BIGINT UNSIGNED NOT NULL <<FK>>
    collectible_id : BIGINT UNSIGNED NOT NULL <<FK>>
    offered_boxes : INT UNSIGNED NOT NULL
    status : ENUM('pending','accepted','rejected','cancelled')
    note : VARCHAR(500) NULLABLE
    --
    completed_at : TIMESTAMP NULLABLE
    created_at : TIMESTAMP
    updated_at : TIMESTAMP
  }

  entity "checkin_records" as checkins <<trading>> {
    * **id** : BIGINT UNSIGNED <<PK>>
    --
    user_id : BIGINT UNSIGNED NOT NULL <<FK>>
    reward_amount : INT UNSIGNED DEFAULT 1
    --
    checked_in_at : TIMESTAMP NOT NULL
    ==
    INDEX(user_id, checked_in_at)
  }
}

package "Web3 Domain" #F3E5F5 {
  entity "web3_accounts" as web3 <<web3>> {
    * **id** : BIGINT UNSIGNED <<PK>>
    --
    user_id : BIGINT UNSIGNED NOT NULL <<FK>>
    address : VARCHAR(80) NOT NULL <<UNIQUE>>
    source : VARCHAR(50) DEFAULT 'metamask'
    type : VARCHAR(20) DEFAULT 'evm'
    --
    attached_at : TIMESTAMP NULLABLE
    last_verified_at : TIMESTAMP NULLABLE
  }
}

users ||--o{ collectibles : "owns (user_id)"
users ||--o{ collectibles : "generated (original_user_id)"
users |o--o| collectibles : "showcases"
users ||--o{ checkins : "checks in"
users ||--o{ web3 : "binds wallet"
users ||--o{ trades : "offers (from_user_id)"
users ||--o{ trades : "receives (to_user_id)"
collectibles ||--o{ trades : "traded in"
collectibles ||--o{ events : "has events"
trades ||--o{ events : "logs event"
users ||--o{ events : "event source"

@enduml
```

---

## Flarum Extension Conventions & Patterns

### Backend Architecture Patterns

**Extender System**: All backend registration goes through `extend.php`. Key extenders:

- `Extend\Frontend('forum')` — register JS/CSS assets
- `Extend\ApiResource(CollectibleResource::class)` — register a new Resource (auto-registers routes)
- `Extend\ApiResource(UserResource::class)->fields(...)` — add fields to an existing Resource
- `Extend\Model(User::class)` — add casts, defaults, relationships to existing models
- `Extend\Routes('api')` — register custom non-Resource routes (rarely needed)
- `Extend\ServiceProvider` — register services into IoC container
- `Extend\Event` — subscribe to domain events

**Resource Layer** (Flarum 2.x JSON:API):

- Each API entity is a Resource class extending `Flarum\Api\Resource\AbstractDatabaseResource`
- A single Resource replaces the old Controller + Serializer pair
- Resources define: `type()`, `model()`, `endpoints()`, `fields()`, `sorts()`, `scope()`
- Endpoints: `Endpoint\Index`, `Endpoint\Show`, `Endpoint\Create`, `Endpoint\Update`, `Endpoint\Delete`, `Endpoint\Endpoint` (custom)
- Fields: `Schema\Str`, `Schema\Integer`, `Schema\Boolean`, `Schema\DateTime`, `Schema\Arr`
- Relationships: `Schema\Relationship\ToOne`, `Schema\Relationship\ToMany`
- Routes are auto-registered based on `type()` — e.g. type `'collectibles'` → `/api/collectibles`
- Lifecycle hooks: `creating()`, `updating()`, `deleting()`, `saving()`, `saved()`, etc.

**Model Layer** (Eloquent Active Record):

- All models extend `Flarum\Database\AbstractModel` (which extends `Illuminate\Database\Eloquent\Model`)
- One class = one table, one instance = one row, properties = columns
- Relationships: `hasOne`, `belongsTo`, `hasMany`, `belongsToMany`
- Use `Migration::createTable()` helper (returns `['up' => fn, 'down' => fn]` array)
- Migration naming: `YYYY_MM_DD_HHMMSS_snake_case_description.php`

**CQRS Command Pattern**: Resource endpoint actions dispatch Command objects, Handler classes process them. This decouples API handling from business logic.

**JSON:API Protocol**: All API responses follow strict JSON:API spec via `tobyz/json-api-server`. Resources define field schemas that automatically serialize models. Frontend uses `app.store` to cache and access model instances.

### Frontend Architecture Patterns

**Mithril.js Components**: Extend existing Flarum components via `extend()` utility:

```js
import { extend } from "flarum/common/extend";
import PostUser from "flarum/forum/components/PostUser";
```

**Frontend Models**: Extend `flarum/common/Model`, register with `Extend.Store().add('collectibles', Collectible)` in `extend.js`.

**Path Aliases**: `'flarum/common/...'` paths are Webpack aliases to Flarum core globals, NOT physical node_modules paths.

### Key Technical Decisions

1. **EVM only** — No Dotsama/Substrate support. MetaMask + GMP for signature verification (no Rust FFI needed).
2. **IPFS without pinning** — Intentional design: creates natural scarcity via IPFS garbage collection.
3. **Async generation** — AIGC + IPFS + minting all happen in queue jobs, not in the HTTP request cycle.
4. **Blind box as currency** — No fiat currency, no marketplace. Pure P2P barter with blind boxes as the medium of exchange.
5. **Optional NFT minting** — Collectibles exist in MySQL first. On-chain minting only happens if user has bound wallet. Can be done retroactively.
6. **DB transaction safety** — All balance transfers and ownership changes wrapped in DB transactions with row-level locking (`FOR UPDATE`).
7. **WebSocket notifications** — Real-time push for trade requests, generation completion, trade outcomes.

### NFT Data Architecture (Three-Layer Model)

| Layer                | Stores                                                       | Purpose                                                |
| -------------------- | ------------------------------------------------------------ | ------------------------------------------------------ |
| **Blockchain (EVM)** | token_id, owner address, tokenURI                            | Decentralized ownership proof. Survives if MySQL dies. |
| **IPFS**             | Image file (CID), Metadata JSON (CID)                        | Decentralized storage. tokenURI points here.           |
| **MySQL**            | user_id, ipfs_cid, metadata_cid, rarity, aigc_prompt, status | Fast queries, business logic, caching, relationships.  |

Metadata JSON format (ERC-721 standard):

```json
{
  "name": "Collectible #1024",
  "image": "ipfs://QmImageCID...",
  "attributes": [
    { "trait_type": "rarity", "value": "Epic" },
    { "trait_type": "generation", "value": "aigc" }
  ]
}
```

---

## Extension Directory Structure

```
donk/flarum-ext-aigc-collectibles/
├── extend.php                          # Backend extender registration
├── composer.json
├── js/
│   ├── src/
│   │   ├── forum.ts                    # Forum frontend entry
│   │   ├── admin.ts                    # Admin frontend entry
│   │   ├── forum/
│   │   │   ├── components/
│   │   │   │   ├── CheckinButton.tsx
│   │   │   │   ├── BlindBoxOpener.tsx
│   │   │   │   ├── CollectibleCard.tsx
│   │   │   │   ├── CollectibleGallery.tsx
│   │   │   │   ├── TradePanel.tsx
│   │   │   │   ├── TradeRequestModal.tsx
│   │   │   │   ├── WalletConnector.tsx
│   │   │   │   └── PostCollectibleBadge.tsx
│   │   │   ├── models/
│   │   │   │   ├── Collectible.ts
│   │   │   │   ├── Trade.ts
│   │   │   │   └── CheckinRecord.ts
│   │   │   └── utils/
│   │   │       ├── web3.ts
│   │   │       ├── ipfs.ts
│   │   │       └── notifications.ts
│   │   └── admin/
│   │       └── components/
│   │           └── AigcCollectiblesSettings.tsx
│   ├── dist/                           # Compiled output
│   ├── package.json
│   ├── tsconfig.json
│   └── webpack.config.js
├── src/
│   ├── Access/
│   │   ├── CollectiblePolicy.php
│   │   ├── TradePolicy.php
│   │   └── CheckinPolicy.php
│   ├── Api/
│   │   └── Resource/
│   │       ├── CollectibleResource.php
│   │       ├── TradeResource.php
│   │       ├── CheckinRecordResource.php
│   │       ├── Web3AccountResource.php
│   │       └── CollectibleEventResource.php
│   ├── Command/
│   │   ├── Checkin.php
│   │   ├── CheckinHandler.php
│   │   ├── OpenBlindBox.php
│   │   ├── OpenBlindBoxHandler.php
│   │   ├── CreateTrade.php
│   │   ├── CreateTradeHandler.php
│   │   ├── AcceptTrade.php
│   │   ├── AcceptTradeHandler.php
│   │   ├── CancelTrade.php
│   │   ├── CancelTradeHandler.php
│   │   ├── BindWallet.php
│   │   ├── BindWalletHandler.php
│   │   ├── MintCollectible.php
│   │   └── MintCollectibleHandler.php
│   ├── Event/
│   │   ├── CheckedIn.php
│   │   ├── BlindBoxOpened.php
│   │   ├── CollectibleGenerated.php
│   │   ├── TradeCreated.php
│   │   ├── TradeCompleted.php
│   │   ├── WalletBound.php
│   │   └── CollectibleMinted.php
│   ├── Job/
│   │   └── GenerateCollectibleJob.php
│   ├── Model/
│   │   ├── Collectible.php
│   │   ├── Trade.php
│   │   ├── CheckinRecord.php
│   │   ├── Web3Account.php
│   │   └── CollectibleEvent.php
│   ├── Repository/
│   │   ├── CollectibleRepository.php
│   │   ├── TradeRepository.php
│   │   └── CheckinRepository.php
│   ├── Service/
│   │   ├── AIGCService.php
│   │   ├── IPFSService.php
│   │   ├── BlockchainService.php
│   │   ├── CheckinService.php
│   │   ├── BlindBoxService.php
│   │   └── TradeService.php
│   ├── Validator/
│   │   ├── TradeValidator.php
│   │   ├── Web3LoginValidator.php
│   │   └── CheckinValidator.php
│   └── Provider/
│       └── CollectibleServiceProvider.php
├── migrations/
│   ├── 2026_01_01_000001_create_collectibles_table.php
│   ├── 2026_01_01_000002_create_trades_table.php
│   ├── 2026_01_01_000003_create_checkin_records_table.php
│   ├── 2026_01_01_000004_create_web3_accounts_table.php
│   ├── 2026_01_01_000005_create_collectible_events_table.php
│   └── 2026_01_01_000006_add_blindbox_fields_to_users.php
├── resources/
│   ├── locale/
│   │   ├── en.yml
│   │   └── zh-hans.yml
│   └── less/
│       └── forum.less
└── contracts/
    └── CollectibleNFT.sol              # ERC-721 Solidity contract
```

---

## MVP Development Roadmap

### Phase 1 — Check-in & Blind Box (签到与盲盒)

- Migration: add `blind_box_count` to users, create `checkin_records` table
- Backend: CheckinController, CheckinService, BlindBoxService
- Frontend: CheckinButton component, balance display in header
- Goal: User clicks check-in → blind_box_count increases

### Phase 2 — Collectible Display (藏品展示)

- Migration: create `collectibles` table
- Backend: CollectibleController, CollectibleSerializer
- Frontend: CollectibleCard, CollectibleGallery, PostCollectibleBadge
- Goal: Manually insert test collectibles → display on posts and profile

### Phase 3 — AIGC Generation + IPFS (盲盒开启)

- Backend: AIGCService, IPFSService, GenerateCollectibleJob (queue)
- Frontend: BlindBoxOpener with animation, WebSocket listener
- Goal: Open blind box → AIGC generates image → upload to IPFS → display result

### Phase 4 — P2P Trading (P2P交易)

- Migration: create `trades` table, `collectible_events` table
- Backend: TradeService, TradeController, all Trade commands
- Frontend: TradePanel, TradeRequestModal, WebSocket notifications
- Goal: Full P2P trade flow with balance transfer and ownership change

### Phase 5 — Web3 & NFT (钱包与NFT)

- Migration: create `web3_accounts` table
- Backend: BlockchainService, Web3AccountController, MintCollectible command
- Frontend: WalletConnector, MetaMask integration
- Smart contract: ERC-721 CollectibleNFT.sol (Hardhat)
- Goal: Bind wallet → mint collectibles as on-chain NFTs

### Phase 6 — Admin Panel & Polish

- Admin settings page (AIGC config, IPFS config, rarity weights)
- System statistics dashboard
- i18n (Chinese + English)
- Error handling, edge cases, security hardening

---

## Reference Plugins for Learning

| Plugin                              | What to Study                                                            |
| ----------------------------------- | ------------------------------------------------------------------------ |
| `v17development/flarum-user-badges` | Model+relationship design, PostUser extension, badge display on posts    |
| `ziiven/flarum-daily-check-in`      | Check-in record table, daily state tracking, frontend button             |
| `antoinefr/flarum-ext-money`        | User balance field, safe increment/decrement, concurrency protection     |
| `sycho/flarum-profile-cover`        | Image display on user profile, file handling                             |
| `fof/byobu`                         | Private messaging between two users (reference for trade negotiation UX) |
| `blomstra/web3`                     | Web3Account model, wallet binding flow, EVM signature verification       |

---

## Coding Guidelines — Patterns & Anti-Patterns

> This section codifies conventions already established in the codebase. Follow these rules to prevent drift as the project grows.

### MUST Follow (Good Patterns to Preserve)

#### 1. Interface-Oriented DI for All Services
Every service class MUST have a corresponding interface in `src/Service/Contracts/` and MUST be bound via `CollectibleServiceProvider`. Handlers and jobs type-hint the interface, never the concrete class.
```php
// GOOD — in Handler constructor
public function __construct(private BlindBoxServiceInterface $blindBox) {}

// BAD — concrete class coupling
public function __construct(private BlindBoxService $blindBox) {}
```

#### 2. CQRS Command/Handler Separation
All mutating API actions MUST flow through Command → Handler → Service. Resource endpoints dispatch commands; they do NOT contain business logic.
```php
// GOOD — resource endpoint dispatches command
$this->bus->dispatch(new Checkin($actor));

// BAD — business logic in endpoint closure
$user->blind_box_count += 1; $user->save();
```

#### 3. Transaction Safety for Balance & Ownership Operations
Any operation that modifies `blind_box_count`, `collectible.user_id`, or `trade.status` MUST be wrapped in `$this->db->transaction()` with `lockForUpdate()` on the rows being modified.
```php
// GOOD — atomic balance operations in BlindBoxService
$affected = $this->db->table('users')
    ->where('id', $user->id)
    ->where('blind_box_count', '>=', $amount)
    ->decrement('blind_box_count', $amount);
if ($affected !== 1) { throw new ValidationException(...); }
```

#### 4. Event Dispatching After State Changes
All significant state transitions MUST dispatch a domain event. Events live in `src/Event/` and carry the actor, the affected model, and relevant context.
```
State change → dispatch event → listeners react
Checkin → CheckedIn → (award blind boxes)
Trade accepted → TradeCompleted → (log event, notify users)
```

#### 5. Eloquent Model Conventions
- Models extend `Flarum\Database\AbstractModel`
- Define `$table`, typed relationship methods, and `@property` PHPDoc
- Use factory methods for creation: `Collectible::createForUser(...)`, `Trade::createOffer(...)`
- Use `ScopeVisibilityTrait` for models that need access control scoping

#### 6. Resource-Based API (Flarum 2.x)
Each API entity is a single Resource class extending `AbstractDatabaseResource` that defines `type()`, `model()`, `endpoints()`, `fields()`. This replaces the old Controller+Serializer pair.

#### 7. Frontend Component Structure
- Components use Mithril.js class-based components extending Flarum base classes
- State is managed via class properties + `m.redraw()`
- Models extend `flarum/common/Model` and are registered via `app.store`
- Flarum imports use path aliases (`'flarum/common/...'`), NOT node_modules paths

#### 8. Unit Test Conventions
- Tests live in `tests/unit/` and `tests/integration/`
- Unit tests mock dependencies via Mockery; use `Flarum\Testing\unit\TestCase`
- External services (AIGC, IPFS, Blockchain) use injected HTTP clients that can be replaced with Guzzle `MockHandler` or Mockery mocks
- Test methods use `@test` annotation + descriptive `it_*` naming

---

### MUST NOT Do (Anti-Patterns to Avoid)

#### 1. No Magic Strings for Status Values or Setting Keys
Status values (`'pending'`, `'accepted'`, `'generating'`, `'completed'`) and rarity levels (`'common'`, `'rare'`, `'epic'`, `'legendary'`) appear as bare strings throughout the codebase. When adding new code, use the same string values consistently. (TODO: Extract to class constants in a future refactor.)
```php
// Current pattern (acceptable for now, keep consistent):
$trade->status = 'accepted';
$collectible->rarity = 'legendary';

// DO NOT invent new values or misspell existing ones.
```

#### 2. No Business Logic in Resource Endpoint Closures
Resource `creating()`, `updating()`, and custom endpoint closures should only: extract parameters, dispatch commands, and return results. All validation and state mutation belongs in Handlers or Services.

#### 3. No Silent Exception Swallowing
Never catch exceptions without at least logging them. The job layer had a case of `catch (\Throwable) { /* silent */ }` — this makes debugging impossible.
```php
// BAD
catch (\Throwable $e) { /* non-critical, skip */ }

// GOOD
catch (\Throwable $e) {
    resolve('log')->warning('NFT minting failed', ['error' => $e->getMessage()]);
}
```

#### 4. No Direct DB Queries in Handlers
Handlers orchestrate Commands → Services. They should NOT write raw DB queries. Balance operations go through `BlindBoxService`; model persistence goes through Eloquent models or repositories.

#### 5. No Hardcoded Timeouts or URLs
Service constructors accept injected HTTP clients. Timeouts and base URLs come from `SettingsRepositoryInterface`. When adding new external service calls, follow the same pattern as `AIGCService` (settings + injectable client).

#### 6. No Inconsistent Constructor Patterns in Handlers
All Handlers MUST use PHP 8.1+ constructor promotion and use `$this->` to access injected dependencies. Do not assign constructor parameters to properties and then re-read from `$command->actor` instead.
```php
// GOOD
public function __construct(
    private CheckinServiceInterface $checkinService,
    private Dispatcher $bus,
) {}

// BAD — assigns to $this but reads from $command
protected $checkinService;
public function __construct($service) { $this->checkinService = $service; }
// then later: $command->actor instead of using injected deps
```

#### 7. No Empty Validators
If a Validator class has no rules, delete it. An empty validator adds confusion without value.

---

### Frontend-Specific Rules

#### 1. Error Handling Consistency
All API calls MUST show user-facing error messages on failure. Use `app.alerts.show()` for errors. Do NOT silently swallow fetch failures.

#### 2. State Reset on View Changes
When switching tabs, filters, or modals: reset pagination offset, loading state, and error state. The `CollectibleGallery` filter change must reset `offset = 0`.

#### 3. WebSocket + Polling Fallback
Long-running async operations (AIGC generation) MUST implement both WebSocket listening AND polling fallback with `MAX_POLL_ATTEMPTS` to prevent infinite loops.

#### 4. Correct HTTP Methods
Match the HTTP method to the backend endpoint. `GET` for nonce retrieval, `POST` for state mutations. The `WalletConnector` nonce request should use the method matching the backend Resource endpoint definition.

---

### Testing Strategy

#### Test Pyramid
```
Integration Tests (API → Handler → Service → DB)
         ▲ covers full call chains
Unit Tests (Service logic with mocked deps)
         ▲ covers business rules
```

#### What to Mock
| Dependency | Mock Strategy |
|-----------|--------------|
| AIGC API | Guzzle `MockHandler` or fake implementation of `AIGCServiceInterface` |
| IPFS API | Guzzle `MockHandler` or fake implementation of `IPFSServiceInterface` |
| Blockchain | Fake `BlockchainServiceInterface` (returns predetermined token_ids) |
| Database | Real SQLite (integration) or Mockery `ConnectionInterface` (unit) |
| Events | Mockery `Dispatcher` (unit) or assert via DB state (integration) |

#### Five Call Chains to Test
1. **Checkin**: POST /api/checkin-records → CheckinHandler → CheckinService → blind_box_count++
2. **OpenBlindBox**: POST /api/collectibles → OpenBlindBoxHandler → BlindBoxService → GenerateCollectibleJob
3. **BindWallet**: POST /api/web3-accounts → BindWalletHandler → BlockchainService.verify → Web3Account created
4. **MintCollectible**: POST /api/collectibles/{id}/mint → MintCollectibleHandler → BlockchainService.mint → token_id set
5. **Trade**: POST /api/trades → CreateTradeHandler; POST /api/trades/{id}/accept → AcceptTradeHandler → ownership transfer
