<?php

namespace Donk\AigcCollectibles\Service\Contracts;

interface AIGCServiceInterface
{
    public function generateImage(string $prompt, string $rarity): string;

    public function isConfigured(): bool;
}
