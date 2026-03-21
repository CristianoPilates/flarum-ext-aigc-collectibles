<?php

namespace Donk\AigcCollectibles\Service;

use Flarum\Settings\SettingsRepositoryInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use RuntimeException;

class IPFSService
{
    protected Client $client;

    public function __construct(
        protected SettingsRepositoryInterface $settings,
        ?Client $client = null
    ) {
        $this->client = $client ?? new Client([
            'timeout' => 60,
        ]);
    }

    public function upload(string $data): string
    {
        $apiUrl = $this->getApiUrl();

        try {
            $response = $this->client->post($apiUrl.'/api/v0/add', [
                'multipart' => [
                    [
                        'name' => 'file',
                        'contents' => $data,
                        'filename' => 'collectible.png',
                    ],
                ],
                'query' => [
                    'pin' => 'false',
                ],
            ]);

            $body = json_decode($response->getBody()->getContents(), true);

            if (! isset($body['Hash'])) {
                throw new RuntimeException('Unexpected IPFS API response format.');
            }

            return $body['Hash'];
        } catch (GuzzleException $e) {
            throw new RuntimeException('IPFS upload failed: '.$e->getMessage(), 0, $e);
        }
    }

    public function uploadJson(array $metadata): string
    {
        $jsonData = json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $apiUrl = $this->getApiUrl();

        try {
            $response = $this->client->post($apiUrl.'/api/v0/add', [
                'multipart' => [
                    [
                        'name' => 'file',
                        'contents' => $jsonData,
                        'filename' => 'metadata.json',
                    ],
                ],
                'query' => [
                    'pin' => 'false',
                ],
            ]);

            $body = json_decode($response->getBody()->getContents(), true);

            if (! isset($body['Hash'])) {
                throw new RuntimeException('Unexpected IPFS API response format.');
            }

            return $body['Hash'];
        } catch (GuzzleException $e) {
            throw new RuntimeException('IPFS metadata upload failed: '.$e->getMessage(), 0, $e);
        }
    }

    public function getGatewayUrl(string $cid): string
    {
        $gatewayUrl = $this->settings->get('donk-aigc-collectibles.ipfs-gateway-url', 'https://ipfs.io/ipfs/');

        return rtrim($gatewayUrl, '/').'/'.$cid;
    }

    protected function getApiUrl(): string
    {
        $apiUrl = $this->settings->get('donk-aigc-collectibles.ipfs-api-url');

        if (empty($apiUrl)) {
            throw new RuntimeException('IPFS API URL is not configured.');
        }

        return rtrim($apiUrl, '/');
    }
}
