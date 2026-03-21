<?php

namespace Donk\AigcCollectibles\Service;

use Donk\AigcCollectibles\Service\Contracts\AIGCServiceInterface;
use Flarum\Settings\SettingsRepositoryInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use RuntimeException;

/**
 * Service responsible for generating collectible card images via the configured AIGC API.
 *
 * This service enhances the input prompt based on rarity, sends an image generation
 * request, and returns the decoded binary image content.
 */
class AIGCService implements AIGCServiceInterface
{
    protected Client $client;

    /**
     * Create a new instance.
     *
     * Initializes the settings repository and HTTP client. If no client is provided,
     * a default client is created with a 120-second timeout.
     *
     * @param  SettingsRepositoryInterface  $settings  Settings repository instance.
     * @param  Client|null  $client  Optional HTTP client instance.
     */
    public function __construct(
        protected SettingsRepositoryInterface $settings,
        ?Client $client = null
    ) {
        $this->client = $client ?? new Client([
            'timeout' => 120,
        ]);
    }

    /**
     * Generates a collectible image using the configured AIGC API.
     *
     * Builds an enhanced prompt from the provided input and rarity, requests a single
     * 512x512 image as base64 JSON, and returns the decoded binary image data.
     *
     * @param  string  $prompt  Base prompt describing the desired image.
     * @param  string  $rarity  Rarity level used to augment the prompt.
     * @return string Decoded binary image contents.
     *
     * @throws RuntimeException If API configuration is missing, the response format is invalid,
     *                          or the API request fails.
     */
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
                    'Authorization' => 'Bearer '.$apiKey,
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

            if (! isset($body['data'][0]['b64_json'])) {
                throw new RuntimeException('Unexpected AIGC API response format.');
            }

            return base64_decode($body['data'][0]['b64_json']);
        } catch (GuzzleException $e) {
            throw new RuntimeException('AIGC API request failed: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Build a finalized card-art prompt by appending a rarity-based quality modifier
     * and standard composition directives to the provided base prompt.
     *
     * Unknown rarity values fall back to the "common" modifier.
     *
     * @param  string  $basePrompt  Base prompt describing the card subject.
     * @param  string  $rarity  Card rarity used to select visual quality.
     * @return string Complete prompt for image generation.
     */
    protected function buildPrompt(string $basePrompt, string $rarity): string
    {
        $qualityModifiers = [
            'common' => 'simple, clean digital art style',
            'rare' => 'detailed, vibrant digital illustration',
            'epic' => 'highly detailed, dramatic lighting, epic digital painting',
            'legendary' => 'masterpiece, ultra-detailed, cinematic lighting, legendary digital artwork',
        ];

        $modifier = $qualityModifiers[$rarity] ?? $qualityModifiers['common'];

        return $basePrompt.', '.$modifier.', collectible card art, centered composition, no text';
    }

    /**
     * Determine whether the AIGC integration is fully configured.
     *
     * The integration is considered configured only when both the API URL
     * and API key are present and non-empty.
     */
    public function isConfigured(): bool
    {
        $apiUrl = $this->settings->get('donk-aigc-collectibles.aigc-api-url');
        $apiKey = $this->settings->get('donk-aigc-collectibles.aigc-api-key');

        return ! empty($apiUrl) && ! empty($apiKey);
    }
}
