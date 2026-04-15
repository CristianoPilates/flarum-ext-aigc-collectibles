<?php

namespace Donk\AigcCollectibles\Tests\unit\Model;

use Donk\AigcCollectibles\Model\Collectible;
use Flarum\Testing\unit\TestCase;
use PHPUnit\Framework\Attributes\Test;

class CollectibleTest extends TestCase
{
    #[Test]
    public function getNameAttribute_returns_custom_name_when_set(): void
    {
        $collectible = new Collectible();
        $collectible->id = 42;
        $collectible->name = 'My Cool NFT';

        $result = $collectible->name;

        $this->assertSame('My Cool NFT', $result);
    }

    #[Test]
    public function getNameAttribute_returns_fallback_when_null(): void
    {
        $collectible = new Collectible();
        $collectible->id = 99;
        // name is null by default (attribute not set)

        $result = $collectible->name;

        $this->assertSame('Collectible #99', $result);
    }

    #[Test]
    public function getNameAttribute_returns_fallback_when_empty_string(): void
    {
        $collectible = new Collectible();
        $collectible->id = 7;
        $collectible->name = '';

        $result = $collectible->name;

        $this->assertSame('Collectible #7', $result);
    }

    #[Test]
    public function getNameAttribute_returns_fallback_when_draft_has_no_id(): void
    {
        $collectible = new Collectible();
        // id is null for a new unsaved model

        $result = $collectible->name;

        $this->assertSame('Collectible #Draft', $result);
    }
}
