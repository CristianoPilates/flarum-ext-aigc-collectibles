<?php

namespace Donk\AigcCollectibles\Service;

use Donk\AigcCollectibles\Model\Collectible;
use Donk\AigcCollectibles\Service\Contracts\CollectibleProofServiceInterface;
use Donk\AigcCollectibles\Service\Contracts\IPFSServiceInterface;
use Flarum\Settings\SettingsRepositoryInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use JsonException;
use RuntimeException;

class CollectibleProofService implements CollectibleProofServiceInterface
{
    protected Client $client;

    public function __construct(
        protected SettingsRepositoryInterface $settings,
        protected IPFSServiceInterface $ipfs,
        ?Client $client = null
    ) {
        $this->client = $client ?? new Client([
            'timeout' => 20,
        ]);
    }

    public function buildProof(Collectible $collectible): array
    {
        if (method_exists($collectible, 'relationLoaded') && !$collectible->relationLoaded('owner')) {
            $collectible->loadMissing('owner');
        }

        $metadataLayer = $this->buildMetadataLayer($collectible);
        $imageLayer = $this->buildImageLayer($collectible, $metadataLayer);

        return [
            'app' => [
                'collectibleId' => $collectible->id,
                'name' => $collectible->name,
                'status' => $collectible->status,
                'rarity' => $collectible->rarity,
                'tokenId' => $collectible->token_id,
                'metadataCid' => $collectible->metadata_cid,
                'ipfsCid' => $collectible->ipfs_cid,
                'ownerId' => $collectible->owner?->id,
                'ownerSlug' => $collectible->owner?->slug,
                'ownerUsername' => $collectible->owner?->username,
                'expectedTokenUri' => $collectible->metadata_cid ? 'ipfs://'.$this->normalizeCid($collectible->metadata_cid) : null,
                'metadataGatewayUrl' => $collectible->metadata_cid ? $this->gatewayUrl($collectible->metadata_cid) : null,
                'imageGatewayUrl' => $collectible->ipfs_cid ? $this->gatewayUrl($collectible->ipfs_cid) : null,
            ],
            'chain' => $this->buildChainLayer($collectible),
            'metadata' => $metadataLayer,
            'image' => $imageLayer,
        ];
    }

    protected function buildChainLayer(Collectible $collectible): array
    {
        if ($collectible->token_id === null) {
            return [
                'available' => false,
                'state' => 'not_minted',
                'error' => 'Collectible has not been minted yet.',
            ];
        }

        $rpcUrl = $this->settings->get('donk-aigc-collectibles.blockchain-rpc-url');
        $contractAddress = $this->settings->get('donk-aigc-collectibles.nft-contract-address');

        if (empty($rpcUrl) || empty($contractAddress)) {
            return [
                'available' => false,
                'state' => 'not_configured',
                'tokenId' => $collectible->token_id,
                'error' => 'Blockchain proof is not configured.',
            ];
        }

        try {
            $chainIdHex = (string) $this->rpcCall($rpcUrl, 'eth_chainId', []);
            $ownerHex = (string) $this->ethCall($rpcUrl, $contractAddress, $this->encodeOwnerOfCall((int) $collectible->token_id));
            $tokenUri = $this->decodeAbiString(
                (string) $this->ethCall($rpcUrl, $contractAddress, $this->encodeTokenUriCall((int) $collectible->token_id))
            );

            return [
                'available' => true,
                'state' => 'verified',
                'tokenId' => $collectible->token_id,
                'chainId' => hexdec($chainIdHex),
                'chainIdHex' => $chainIdHex,
                'contractAddress' => $contractAddress,
                'ownerOf' => $this->decodeAbiAddress($ownerHex),
                'tokenURI' => $tokenUri,
                'tokenUriGatewayUrl' => $this->toGatewayUrl($tokenUri),
                'matchesMetadataCid' => $collectible->metadata_cid
                    ? $this->normalizeCid($tokenUri) === $this->normalizeCid($collectible->metadata_cid)
                    : null,
            ];
        } catch (\Throwable $e) {
            return [
                'available' => false,
                'state' => 'error',
                'tokenId' => $collectible->token_id,
                'contractAddress' => $contractAddress,
                'error' => $e->getMessage(),
            ];
        }
    }

    protected function buildMetadataLayer(Collectible $collectible): array
    {
        if (empty($collectible->metadata_cid)) {
            return [
                'available' => false,
                'state' => 'missing',
                'error' => 'Metadata CID is not available.',
            ];
        }

        $metadataCid = $this->normalizeCid($collectible->metadata_cid);
        $metadataGatewayUrl = $this->gatewayUrl($metadataCid);

        try {
            $json = $this->fetchMetadataJson($metadataCid);
            $imageField = is_array($json) ? ($json['image'] ?? null) : null;
            $imageCid = $this->extractCid($imageField);

            return [
                'available' => true,
                'state' => 'verified',
                'cid' => $metadataCid,
                'gatewayUrl' => $metadataGatewayUrl,
                'json' => $json,
                'imageField' => $imageField,
                'imageCid' => $imageCid,
                'imageGatewayUrl' => $imageCid ? $this->gatewayUrl($imageCid) : null,
            ];
        } catch (\Throwable $e) {
            return [
                'available' => false,
                'state' => 'error',
                'cid' => $metadataCid,
                'gatewayUrl' => $metadataGatewayUrl,
                'error' => $e->getMessage(),
            ];
        }
    }

    protected function buildImageLayer(Collectible $collectible, array $metadataLayer): array
    {
        $metadataImageCid = is_string($metadataLayer['imageCid'] ?? null) ? $metadataLayer['imageCid'] : null;
        $imageCid = $collectible->ipfs_cid ? $this->normalizeCid($collectible->ipfs_cid) : $metadataImageCid;

        if (!$imageCid) {
            return [
                'available' => false,
                'state' => 'missing',
                'error' => 'Image CID is not available.',
            ];
        }

        return [
            'available' => true,
            'state' => 'verified',
            'cid' => $imageCid,
            'gatewayUrl' => $this->gatewayUrl($imageCid),
            'source' => $collectible->ipfs_cid ? 'collectible.ipfsCid' : 'metadata.image',
            'matchesMetadataImage' => $metadataImageCid ? $metadataImageCid === $imageCid : null,
        ];
    }

    protected function fetchMetadataJson(string $cid): array
    {
        $response = $this->client->get($this->gatewayUrl($cid), [
            'headers' => [
                'Accept' => 'application/json',
            ],
        ]);

        $raw = (string) $response->getBody();

        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException('Metadata JSON is invalid: '.$e->getMessage(), 0, $e);
        }

        if (!is_array($decoded)) {
            throw new RuntimeException('Metadata JSON payload must decode to an object.');
        }

        return $decoded;
    }

    protected function ethCall(string $rpcUrl, string $contractAddress, string $data): string
    {
        return (string) $this->rpcCall($rpcUrl, 'eth_call', [[
            'to' => $contractAddress,
            'data' => $data,
        ], 'latest']);
    }

    protected function rpcCall(string $rpcUrl, string $method, array $params): mixed
    {
        try {
            $response = $this->client->post($rpcUrl, [
                'json' => [
                    'jsonrpc' => '2.0',
                    'method' => $method,
                    'params' => $params,
                    'id' => 1,
                ],
            ]);
        } catch (GuzzleException $e) {
            throw new RuntimeException('RPC call failed: '.$e->getMessage(), 0, $e);
        }

        try {
            $body = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException('RPC response JSON is invalid: '.$e->getMessage(), 0, $e);
        }

        if (isset($body['error'])) {
            throw new RuntimeException('RPC error: '.($body['error']['message'] ?? 'Unknown'));
        }

        return $body['result'] ?? null;
    }

    protected function encodeOwnerOfCall(int $tokenId): string
    {
        return '0x6352211e'.str_pad(dechex($tokenId), 64, '0', STR_PAD_LEFT);
    }

    protected function encodeTokenUriCall(int $tokenId): string
    {
        return '0xc87b56dd'.str_pad(dechex($tokenId), 64, '0', STR_PAD_LEFT);
    }

    protected function decodeAbiAddress(string $hexValue): string
    {
        $hex = $this->stripHexPrefix($hexValue);

        if (strlen($hex) < 64) {
            throw new RuntimeException('ABI address payload is too short.');
        }

        return '0x'.substr($hex, -40);
    }

    protected function decodeAbiString(string $hexValue): string
    {
        $hex = $this->stripHexPrefix($hexValue);

        if (strlen($hex) < 128) {
            throw new RuntimeException('ABI string payload is too short.');
        }

        $offset = hexdec(substr($hex, 0, 64));
        $lengthPosition = $offset * 2;
        $length = hexdec(substr($hex, $lengthPosition, 64));
        $dataPosition = $lengthPosition + 64;
        $data = substr($hex, $dataPosition, $length * 2);

        return rtrim(hex2bin($data) ?: '', "\0");
    }

    protected function toGatewayUrl(?string $value): ?string
    {
        if (!$value) {
            return null;
        }

        $cid = $this->extractCid($value);

        return $cid ? $this->gatewayUrl($cid) : $value;
    }

    protected function gatewayUrl(string $value): string
    {
        $cid = $this->normalizeCid($value);

        return $this->ipfs->getGatewayUrl($cid);
    }

    protected function extractCid(?string $value): ?string
    {
        if (!$value) {
            return null;
        }

        if (preg_match('#/ipfs/([^/?#]+)#', $value, $matches)) {
            return $matches[1];
        }

        if (str_starts_with($value, 'ipfs://')) {
            return $this->normalizeCid($value);
        }

        if (preg_match('/^(Qm[1-9A-HJ-NP-Za-km-z]{44}|bafy[1-9A-Za-z]+)/', $value, $matches)) {
            return $matches[1];
        }

        return null;
    }

    protected function normalizeCid(string $value): string
    {
        $clean = preg_replace('#^ipfs://#', '', trim($value)) ?? trim($value);
        $clean = preg_replace('#^/ipfs/#', '', $clean) ?? $clean;

        return trim($clean, '/');
    }

    protected function stripHexPrefix(string $value): string
    {
        return str_starts_with($value, '0x') ? substr($value, 2) : $value;
    }
}
