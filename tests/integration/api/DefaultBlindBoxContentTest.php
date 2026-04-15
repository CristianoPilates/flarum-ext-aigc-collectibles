<?php

namespace Donk\AigcCollectibles\Tests\integration\api;

use Flarum\Testing\integration\TestCase;
use PHPUnit\Framework\Attributes\Test;

class DefaultBlindBoxContentTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('donk-aigc-collectibles');
    }

    #[Test]
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
                ->where('blindbox_type', 'checkin_reward')
                ->where('pool_category', 'theme')
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
            $this->database()->table('blindbox_draw_rules')
                ->where('blindbox_type', 'trade_reward')
                ->where('pool_category', 'theme')
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

        $this->assertTrue(
            $this->database()->table('phrase_pools')
                ->where('category', 'subject')
                ->where('phrase', 'phoenix')
                ->exists()
        );

        $this->assertTrue(
            $this->database()->table('phrase_pools')
                ->where('category', 'style')
                ->where('phrase', 'ink wash painting')
                ->exists()
        );

        $this->assertTrue(
            $this->database()->table('phrase_pools')
                ->where('category', 'mood')
                ->where('phrase', 'cosmic awe')
                ->exists()
        );

        $this->assertTrue(
            $this->database()->table('phrase_pools')
                ->where('category', 'theme')
                ->where('phrase', 'midnight bazaar')
                ->exists()
        );

        $this->assertGreaterThanOrEqual(
            20,
            $this->database()->table('phrase_pools')->where('category', 'subject')->count()
        );

        $this->assertGreaterThanOrEqual(
            20,
            $this->database()->table('phrase_pools')->where('category', 'style')->count()
        );

        $this->assertGreaterThanOrEqual(
            18,
            $this->database()->table('phrase_pools')->where('category', 'mood')->count()
        );

        $this->assertGreaterThanOrEqual(
            18,
            $this->database()->table('phrase_pools')->where('category', 'theme')->count()
        );

        $this->assertTrue(
            $this->database()->table('phrase_pools')
                ->where('category', 'subject')
                ->where('phrase', 'library automaton')
                ->exists()
        );

        $this->assertTrue(
            $this->database()->table('phrase_pools')
                ->where('category', 'subject')
                ->where('phrase', 'subway koi spirit')
                ->exists()
        );

        $this->assertTrue(
            $this->database()->table('phrase_pools')
                ->where('category', 'style')
                ->where('phrase', 'baroque etching')
                ->exists()
        );

        $this->assertTrue(
            $this->database()->table('phrase_pools')
                ->where('category', 'style')
                ->where('phrase', 'stop-motion miniature set')
                ->exists()
        );

        $this->assertTrue(
            $this->database()->table('phrase_pools')
                ->where('category', 'mood')
                ->where('phrase', 'electric triumph')
                ->exists()
        );

        $this->assertTrue(
            $this->database()->table('phrase_pools')
                ->where('category', 'theme')
                ->where('phrase', 'archive of lost constellations')
                ->exists()
        );
    }
}
