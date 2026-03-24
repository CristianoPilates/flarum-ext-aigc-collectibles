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

| Layer         | Technology                                                                  |
| ------------- | --------------------------------------------------------------------------- |
| Forum Engine  | Flarum 2.0+ (PHP, Laravel 12 components)                                    |
| Backend ORM   | Eloquent (Active Record pattern via `Flarum\Database\AbstractModel`)        |
| API Protocol  | JSON:API (via Flarum Resources + Endpoints, based on tobyz/json-api-server) |
| Frontend      | Mithril.js (Flarum's built-in frontend framework)                           |
| Database      | MySQL                                                                       |
| AIGC          | External API (DALL-E / Stable Diffusion / compatible service)               |
| Image Storage | IPFS (Pinata API or self-hosted node, HTTP Gateway)                         |
| Blockchain    | Local EVM chain (Hardhat/Ganache for dev), ERC-721 smart contract           |
| Real-time     | WebSocket (for trade notifications, generation completion)                  |
| Queue         | Laravel Queue (for async AIGC generation + IPFS upload + NFT minting)       |
| Wallet        | MetaMask (EVM only, no Dotsama/Substrate support)                           |

---
