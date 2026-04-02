// SPDX-License-Identifier: MIT
pragma solidity ^0.8.20;

import "@openzeppelin/contracts/token/ERC721/extensions/ERC721URIStorage.sol";
import "@openzeppelin/contracts/access/Ownable.sol";
//

/**
 * @title CollectibleNFT
 * @notice ERC-721 contract for AIGC-generated digital collectibles.
 *         Only the contract owner (the Flarum backend minting wallet) can mint new tokens.
 *         Token owners may burn their own tokens.
 *         tokenURI points to IPFS metadata JSON following the ERC-721 metadata standard.
 */
contract CollectibleNFT is ERC721URIStorage, Ownable {
    uint256 private _nextTokenId;

    event CollectibleMinted(uint256 indexed tokenId, address indexed to, string tokenURI);
    event CollectibleBurned(uint256 indexed tokenId, address indexed burner);

    constructor()
    //初始化父类ERC721
        ERC721("AigcCollectible", "AIGC")
        //初始化父类Ownable, 部署这个合约的钱包地址自动成为 owner
        Ownable(msg.sender)
    {
        _nextTokenId = 1;
    }

    /**
     * @notice Mint a new collectible NFT.
     * @dev Only callable by the contract owner (Flarum backend minting wallet).
     * @param to The address that will own the minted token.
     * @param tokenURI_ The IPFS URI pointing to the token metadata JSON.
     * @return tokenId The ID of the newly minted token.
     */
    function mint(address to, string calldata tokenURI_) external onlyOwner returns (uint256) {
        uint256 tokenId = _nextTokenId;
        _nextTokenId++;

        _safeMint(to, tokenId);
        _setTokenURI(tokenId, tokenURI_);

        //这里发送一次日志广播
        emit CollectibleMinted(tokenId, to, tokenURI_);

        return tokenId;
    }

    /**
     * @notice Burn a collectible NFT.
     * @dev Only the token owner can burn their own token.
     * @param tokenId The ID of the token to burn.
     */
    function burn(uint256 tokenId) external {
        require(ownerOf(tokenId) == msg.sender, "CollectibleNFT: caller is not the token owner");
        _burn(tokenId);

        emit CollectibleBurned(tokenId, msg.sender);
    }

    /**
     * @notice Get the total number of tokens that have been minted.
     * @return The total count of minted tokens (includes burned tokens in the count).
     */
    function totalMinted() external view returns (uint256) {
        return _nextTokenId - 1;
    }
}
