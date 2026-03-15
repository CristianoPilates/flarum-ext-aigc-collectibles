<?php

namespace Donk\AigcCollectibles\Service;

use Flarum\Settings\SettingsRepositoryInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use RuntimeException;

class AIGCService
{
    protected SettingsRepositoryInterface $settings;
    protected Client $client;

    public function __construct(SettingsRepositoryInterface $settings)
    {
        $this->settings = $settings;
        $this->client = new Client([
            'timeout' => 120,
        ]);
    }

    public function generateImage(string $prompt, string $rarity): string
    {
        $apiUrl = $this->settings->get('donk-aigc-collectibles.aigc-api-url');
        $apiKey = $this->settings->get('donk-aigc-collectibles.aigc-api-key');

        if (empty($apiUrl) || empty($apiKey)) {
            throw new RuntimeException('AIGC API is not configured.');
        }

        $enhancedPrompt = $this->buildPrompt($prompt, $rarity);

        try {
            $response = $this->client->post($apiUrl, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'prompt' => $enhancedPrompt,
                    'n' => 1,
                    'size' => '512x512',
                    'response_format' => 'b64_json',
                ],
            ]);

            $body = json_decode($response->getBody()->getContents(), true);

            if (!isset($body['data'][0]['b64_json'])) {
                throw new RuntimeException('Unexpected AIGC API response format.');
            }

            return base64_decode($body['data'][0]['b64_json']);
        } catch (GuzzleException $e) {
            throw new RuntimeException('AIGC API request failed: ' . $e->getMessage(), 0, $e);
        }
    }

    protected function buildPrompt(string $basePrompt, string $rarity): string
    {
        $qualityModifiers = [
            'common' => 'simple, clean digital art style',
            'rare' => 'detailed, vibrant digital illustration',
            'epic' => 'highly detailed, dramatic lighting, epic digital painting',
            'legendary' => 'masterpiece, ultra-detailed, cinematic lighting, legendary digital artwork',
        ];

        $modifier = $qualityModifiers[$rarity] ?? $qualityModifiers['common'];

        return $basePrompt . ', ' . $modifier . ', collectible card art, centered composition, no text';
    }

    public function isConfigured(): bool
    {
        $apiUrl = $this->settings->get('donk-aigc-collectibles.aigc-api-url');
        $apiKey = $this->settings->get('donk-aigc-collectibles.aigc-api-key');

        return !empty($apiUrl) && !empty($apiKey);
    }
}
