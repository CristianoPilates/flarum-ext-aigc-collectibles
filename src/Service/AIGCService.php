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
        $enhancedPrompt = $this->buildPrompt($prompt, $rarity);

        if (empty($apiUrl) || empty($apiKey)) {
            return $this->generateFallbackImage($enhancedPrompt, $rarity);
        }

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
            return $this->generateFallbackImage($enhancedPrompt, $rarity);
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

    protected function generateFallbackImage(string $prompt, string $rarity): string
    {
        $seed = hash('sha256', $prompt.'|'.$rarity);
        $palette = [
            'common' => ['#1f2937', '#374151', '#9ca3af'],
            'rare' => ['#0f766e', '#0891b2', '#67e8f9'],
            'epic' => ['#312e81', '#7c3aed', '#c4b5fd'],
            'legendary' => ['#7c2d12', '#f59e0b', '#fde68a'],
        ];
        $colors = $palette[$rarity] ?? $palette['common'];
        $accentA = substr($seed, 0, 6);
        $accentB = substr($seed, 6, 6);
        $headline = strtoupper($rarity);
        $caption = strtoupper(substr($seed, 0, 8));
        $promptLines = $this->wrapText($this->sanitizeForSvg($prompt), 34, 3);

        $textNodes = [];
        foreach ($promptLines as $index => $line) {
            $y = 308 + ($index * 28);
            $textNodes[] = '<text x="48" y="'.$y.'" fill="#f8fafc" font-size="22" font-family="monospace">'.$line.'</text>';
        }

        $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="1024" height="1024" viewBox="0 0 1024 1024">
  <defs>
    <linearGradient id="bg" x1="0%%" y1="0%%" x2="100%%" y2="100%%">
      <stop offset="0%%" stop-color="{$colors[0]}"/>
      <stop offset="55%%" stop-color="#{$accentA}"/>
      <stop offset="100%%" stop-color="{$colors[1]}"/>
    </linearGradient>
    <linearGradient id="card" x1="0%%" y1="0%%" x2="0%%" y2="100%%">
      <stop offset="0%%" stop-color="rgba(255,255,255,0.18)"/>
      <stop offset="100%%" stop-color="rgba(255,255,255,0.04)"/>
    </linearGradient>
  </defs>
  <rect width="1024" height="1024" fill="url(#bg)"/>
  <circle cx="820" cy="210" r="170" fill="#{$accentB}" opacity="0.18"/>
  <circle cx="220" cy="820" r="220" fill="{$colors[2]}" opacity="0.16"/>
  <rect x="36" y="36" width="952" height="952" rx="44" fill="none" stroke="rgba(255,255,255,0.22)" stroke-width="4"/>
  <rect x="48" y="48" width="928" height="928" rx="36" fill="rgba(7,10,18,0.24)" stroke="rgba(255,255,255,0.08)" stroke-width="2"/>
  <rect x="48" y="48" width="928" height="440" rx="28" fill="url(#card)"/>
  <text x="48" y="112" fill="rgba(248,250,252,0.72)" font-size="28" font-family="monospace">AIGC COLLECTIBLE</text>
  <text x="48" y="178" fill="#ffffff" font-size="88" font-weight="700" font-family="monospace">{$headline}</text>
  <text x="48" y="234" fill="rgba(248,250,252,0.72)" font-size="30" font-family="monospace">FALLBACK RENDER {$caption}</text>
  <path d="M120 760 C220 560, 420 520, 512 680 C602 834, 796 844, 892 676" fill="none" stroke="rgba(255,255,255,0.28)" stroke-width="18" stroke-linecap="round"/>
  <path d="M176 660 C286 482, 442 422, 590 488 C712 542, 794 496, 848 420" fill="none" stroke="rgba(255,255,255,0.12)" stroke-width="10" stroke-linecap="round"/>
  <rect x="48" y="520" width="928" height="456" rx="28" fill="rgba(2,6,23,0.32)" stroke="rgba(255,255,255,0.08)" stroke-width="2"/>
  <text x="48" y="576" fill="{$colors[2]}" font-size="26" font-family="monospace">PROMPT</text>
  %s
</svg>
SVG;

        return sprintf($svg, implode('', $textNodes));
    }

    /**
     * @return string[]
     */
    protected function wrapText(string $text, int $lineLength, int $maxLines): array
    {
        $wrapped = wordwrap(trim($text), $lineLength, "\n", true);
        $lines = array_filter(explode("\n", $wrapped), static fn (string $line) => $line !== '');
        $lines = array_slice($lines, 0, $maxLines);

        if (count($lines) === $maxLines && strlen($text) > array_sum(array_map('strlen', $lines))) {
            $lastIndex = $maxLines - 1;
            $lines[$lastIndex] = rtrim(substr($lines[$lastIndex], 0, max(0, $lineLength - 3))).'...';
        }

        return $lines ?: ['Mystical collectible'];
    }

    protected function sanitizeForSvg(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
