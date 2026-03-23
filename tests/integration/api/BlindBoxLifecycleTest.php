<?php

namespace Donk\AigcCollectibles\Tests\integration\api;

use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;

class BlindBoxLifecycleTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    private const SEED = 'a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2';

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('donk-aigc-collectibles');

        $this->prepareDatabase([
            'users' => [
                $this->normalUser(),
            ],
            'phrase_pools' => [
                ['id' => 1, 'category' => 'subject', 'phrase' => 'dragon',       'cost' => 5, 'is_active' => 1, 'created_at' => '2025-01-01 00:00:00', 'updated_at' => '2025-01-01 00:00:00'],
                ['id' => 2, 'category' => 'subject', 'phrase' => 'forest',       'cost' => 3, 'is_active' => 1, 'created_at' => '2025-01-01 00:00:00', 'updated_at' => '2025-01-01 00:00:00'],
                ['id' => 3, 'category' => 'style',   'phrase' => 'oil painting', 'cost' => 5, 'is_active' => 1, 'created_at' => '2025-01-01 00:00:00', 'updated_at' => '2025-01-01 00:00:00'],
                ['id' => 4, 'category' => 'style',   'phrase' => 'pixel art',    'cost' => 3, 'is_active' => 1, 'created_at' => '2025-01-01 00:00:00', 'updated_at' => '2025-01-01 00:00:00'],
                ['id' => 5, 'category' => 'mood',    'phrase' => 'ethereal glow', 'cost' => 3, 'is_active' => 1, 'created_at' => '2025-01-01 00:00:00', 'updated_at' => '2025-01-01 00:00:00'],
                ['id' => 6, 'category' => 'mood',    'phrase' => 'dark',          'cost' => 1, 'is_active' => 1, 'created_at' => '2025-01-01 00:00:00', 'updated_at' => '2025-01-01 00:00:00'],
            ],
            'blindbox_draw_rules' => [
                ['id' => 1, 'blindbox_type' => 'checkin_reward', 'pool_category' => 'subject', 'required' => 1, 'created_at' => '2025-01-01 00:00:00', 'updated_at' => '2025-01-01 00:00:00'],
                ['id' => 2, 'blindbox_type' => 'checkin_reward', 'pool_category' => 'style',   'required' => 1, 'created_at' => '2025-01-01 00:00:00', 'updated_at' => '2025-01-01 00:00:00'],
                ['id' => 3, 'blindbox_type' => 'checkin_reward', 'pool_category' => 'mood',    'required' => 0, 'created_at' => '2025-01-01 00:00:00', 'updated_at' => '2025-01-01 00:00:00'],
            ],
            'blindboxes' => [
                ['id' => 1, 'user_id' => 2, 'type' => 'checkin_reward', 'seed' => self::SEED, 'status' => 'unappraised', 'budget' => null, 'collectible_id' => null, 'created_at' => '2025-01-01 00:00:00', 'updated_at' => '2025-01-01 00:00:00'],
            ],
        ]);
    }

    /* ═══════════════════════ Appraise ═══════════════════════ */

    /** @test */
    public function appraise_with_valid_pow_transitions_to_appraised(): void
    {
        $pow = $this->computePow(self::SEED);

        $response = $this->send(
            $this->request('POST', '/api/blindboxes/1/appraise', [
                'authenticatedAs' => 2,
                'json' => [
                    'nonce' => $pow['nonce'],
                    'hash'  => $pow['hash'],
                ],
            ])
        );
        $this->assertEquals(200, $response->getStatusCode());

        $box = $this->database()->table('blindboxes')->where('id', 1)->first();
        $this->assertEquals('appraised', $box->status);
        $this->assertNotNull($box->budget);
        $this->assertGreaterThan(0, $box->budget);
    }

    /** @test */
    public function appraise_rejects_invalid_hash(): void
    {
        $response = $this->send(
            $this->request('POST', '/api/blindboxes/1/appraise', [
                'authenticatedAs' => 2,
                'json' => [
                    'nonce' => 'bogus',
                    'hash'  => str_repeat('f', 64),
                ],
            ])
        );

        $this->assertEquals(422, $response->getStatusCode());

        $box = $this->database()->table('blindboxes')->where('id', 1)->first();
        $this->assertEquals('unappraised', $box->status);
    }

    /** @test */
    public function cannot_appraise_already_appraised(): void
    {
        $this->database()->table('blindboxes')
            ->where('id', 1)
            ->update(['status' => 'appraised', 'budget' => 40]);

        $pow = $this->computePow(self::SEED);

        $response = $this->send(
            $this->request('POST', '/api/blindboxes/1/appraise', [
                'authenticatedAs' => 2,
                'json' => [
                    'nonce' => $pow['nonce'],
                    'hash'  => $pow['hash'],
                ],
            ])
        );

        $this->assertEquals(422, $response->getStatusCode());
    }

    /** @test */
    public function cannot_appraise_other_users_blindbox(): void
    {
        $this->database()->table('blindboxes')
            ->where('id', 1)
            ->update(['user_id' => 1]);

        $pow = $this->computePow(self::SEED);

        $response = $this->send(
            $this->request('POST', '/api/blindboxes/1/appraise', [
                'authenticatedAs' => 2,
                'json' => [
                    'nonce' => $pow['nonce'],
                    'hash'  => $pow['hash'],
                ],
            ])
        );

        $this->assertEquals(404, $response->getStatusCode());
    }

    /* ═══════════════════════ Open ═══════════════════════ */

    /** @test */
    public function open_creates_collectible_and_transitions_to_opened(): void
    {
        $this->database()->table('blindboxes')
            ->where('id', 1)
            ->update(['status' => 'appraised', 'budget' => 20]);

        $response = $this->send(
            $this->request('POST', '/api/blindboxes/1/open', [
                'authenticatedAs' => 2,
            ])
        );

        $this->assertEquals(200, $response->getStatusCode());

        $box = $this->database()->table('blindboxes')->where('id', 1)->first();
        $this->assertEquals('opened', $box->status);
        $this->assertNotNull($box->collectible_id);

        $collectible = $this->database()->table('collectibles')
            ->where('id', $box->collectible_id)->first();
        $this->assertNotNull($collectible);
        $this->assertEquals(2, $collectible->owner_id);
        $this->assertEquals('common', $collectible->rarity);
        $this->assertEquals('draft', $collectible->status);
        $this->assertNotEmpty($collectible->aigc_prompt);
        $this->assertEquals(0, $collectible->times_traded);
    }

    /** @test */
    public function open_with_epic_budget_yields_epic_rarity(): void
    {
        $this->database()->table('blindboxes')
            ->where('id', 1)
            ->update(['status' => 'appraised', 'budget' => 120]);

        $response = $this->send(
            $this->request('POST', '/api/blindboxes/1/open', [
                'authenticatedAs' => 2,
            ])
        );

        $this->assertEquals(200, $response->getStatusCode());

        $box = $this->database()->table('blindboxes')->where('id', 1)->first();
        $collectible = $this->database()->table('collectibles')
            ->where('id', $box->collectible_id)->first();
        $this->assertEquals('epic', $collectible->rarity);
    }

    /** @test */
    public function cannot_open_unappraised(): void
    {
        // status is still 'unappraised' from setUp
        $response = $this->send(
            $this->request('POST', '/api/blindboxes/1/open', [
                'authenticatedAs' => 2,
            ])
        );

        $this->assertEquals(422, $response->getStatusCode());

        $box = $this->database()->table('blindboxes')->where('id', 1)->first();
        $this->assertEquals('unappraised', $box->status);
        $this->assertNull($box->collectible_id);
    }

    /** @test */
    public function cannot_open_already_opened(): void
    {
        $this->database()->table('blindboxes')
            ->where('id', 1)
            ->update(['status' => 'opened', 'budget' => 20]);

        $response = $this->send(
            $this->request('POST', '/api/blindboxes/1/open', [
                'authenticatedAs' => 2,
            ])
        );

        $this->assertEquals(422, $response->getStatusCode());
    }

    /* ═══════════════════════ Full Lifecycle ═══════════════════════ */

    /** @test */
    public function full_lifecycle_appraise_then_open(): void
    {
        // 1. Appraise
        $pow = $this->computePow(self::SEED);

        $response = $this->send(
            $this->request('POST', '/api/blindboxes/1/appraise', [
                'authenticatedAs' => 2,
                'json' => [
                    'nonce' => $pow['nonce'],
                    'hash'  => $pow['hash'],
                ],
            ])
        );
        $this->assertEquals(200, $response->getStatusCode());

        $box = $this->database()->table('blindboxes')->where('id', 1)->first();
        $this->assertEquals('appraised', $box->status);
        $this->assertGreaterThan(0, $box->budget);

        // 2. Open
        $response = $this->send(
            $this->request('POST', '/api/blindboxes/1/open', [
                'authenticatedAs' => 2,
            ])
        );
        $this->assertEquals(200, $response->getStatusCode());

        $box = $this->database()->table('blindboxes')->where('id', 1)->first();
        $this->assertEquals('opened', $box->status);

        $collectible = $this->database()->table('collectibles')
            ->where('id', $box->collectible_id)->first();
        $this->assertEquals(2, $collectible->owner_id);
        $this->assertNotEmpty($collectible->aigc_prompt);

        // Prompt should contain at least one subject and one style (both required)
        $prompt = $collectible->aigc_prompt;
        $hasSubject = str_contains($prompt, 'dragon') || str_contains($prompt, 'forest');
        $hasStyle   = str_contains($prompt, 'oil painting') || str_contains($prompt, 'pixel art');
        $this->assertTrue($hasSubject, "Prompt should contain a subject phrase: {$prompt}");
        $this->assertTrue($hasStyle, "Prompt should contain a style phrase: {$prompt}");
    }

    /* ═══════════════════════ Helpers ═══════════════════════ */

    /**
     * Brute-force a PoW nonce for the given seed.
     * Finds the nonce that produces the most leading zeros in SHA-256(seed + nonce_hex).
     * Stops early once ≥2 leading zeros are found (sufficient for testing).
     *
     * @return array{nonce: string, hash: string, zeros: int}
     */
    private function computePow(string $seed, int $maxIterations = 100000): array
    {
        $nonce = '0';
        $hash  = hash('sha256', $seed . '0');
        $bestZeros = strspn($hash, '0');

        for ($i = 1; $i < $maxIterations; $i++) {
            $candidateNonce = dechex($i);
            $candidateHash  = hash('sha256', $seed . $candidateNonce);
            $zeros = strspn($candidateHash, '0');

            if ($zeros > $bestZeros) {
                $bestZeros = $zeros;
                $hash  = $candidateHash;
                $nonce = $candidateNonce;

                if ($zeros >= 2) {
                    break;
                }
            }
        }

        return ['nonce' => $nonce, 'hash' => $hash, 'zeros' => $bestZeros];
    }
}
