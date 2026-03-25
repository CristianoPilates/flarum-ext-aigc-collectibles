<?php

namespace Donk\AigcCollectibles\Tests\integration\api;

use Flarum\Testing\integration\TestCase;

class DefaultBlindBoxContentTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('donk-aigc-collectibles');
    }

    /** @test */
    public function it_seeds_default_phrases_and_draw_rules_for_reward_boxes(): void
    {
        $this->assertTrue(
            $this->database()->table('blindbox_draw_rules')
                ->where('blindbox_type', 'checkin_reward')
                ->where('pool_category', 'subject')
                ->where('required', 1)
                ->exists()
        );

        $this->assertTrue(
            $this->database()->table('blindbox_draw_rules')
                ->where('blindbox_type', 'checkin_reward')
                ->where('pool_category', 'style')
                ->where('required', 1)
                ->exists()
        );

        $this->assertTrue(
            $this->database()->table('blindbox_draw_rules')
                ->where('blindbox_type', 'checkin_reward')
                ->where('pool_category', 'mood')
                ->where('required', 0)
                ->exists()
        );

        $this->assertTrue(
            $this->database()->table('blindbox_draw_rules')
                ->where('blindbox_type', 'trade_reward')
                ->where('pool_category', 'subject')
                ->where('required', 1)
                ->exists()
        );

        $this->assertTrue(
            $this->database()->table('blindbox_draw_rules')
                ->where('blindbox_type', 'trade_reward')
                ->where('pool_category', 'style')
                ->where('required', 1)
                ->exists()
        );

        $this->assertTrue(
            $this->database()->table('blindbox_draw_rules')
                ->where('blindbox_type', 'trade_reward')
                ->where('pool_category', 'mood')
                ->where('required', 0)
                ->exists()
        );

        $this->assertTrue(
            $this->database()->table('phrase_pools')
                ->where('category', 'subject')
                ->where('phrase', 'dragon')
                ->exists()
        );

        $this->assertTrue(
            $this->database()->table('phrase_pools')
                ->where('category', 'style')
                ->where('phrase', 'oil painting')
                ->exists()
        );

        $this->assertTrue(
            $this->database()->table('phrase_pools')
                ->where('category', 'mood')
                ->where('phrase', 'ethereal glow')
                ->exists()
        );
    }
}
