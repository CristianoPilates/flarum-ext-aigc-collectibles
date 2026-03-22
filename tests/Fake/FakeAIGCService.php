<?php

namespace Donk\AigcCollectibles\Tests\Fake;

use Donk\AigcCollectibles\Service\Contracts\AIGCServiceInterface;

/**
 * Fake AIGC service that returns deterministic image data for testing.
 */
class FakeAIGCService implements AIGCServiceInterface
{
    public bool $configured = true;
    public int $callCount = 0;
    public ?string $lastPrompt = null;
    public ?string $lastRarity = null;
    public bool $shouldFail = false;

    public function generateImage(string $prompt, string $rarity): string
    {
        $this->callCount++;
        $this->lastPrompt = $prompt;
        $this->lastRarity = $rarity;

        if ($this->shouldFail) {
            throw new \RuntimeException('Fake AIGC generation failed.');
        }

        return 'fake-image-data-' . $rarity;
    }

    public function isConfigured(): bool
    {
        return $this->configured;
    }
}
