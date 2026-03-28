<?php

namespace Donk\AigcCollectibles\Tests\integration\api;

use Donk\AigcCollectibles\Service\Contracts\AIGCServiceInterface;
use Donk\AigcCollectibles\Service\Contracts\IPFSServiceInterface;
use Donk\AigcCollectibles\Service\Contracts\NftMintingServiceInterface;
use Donk\AigcCollectibles\Tests\Fake\FakeAIGCService;
use Donk\AigcCollectibles\Tests\Fake\FakeIPFSService;
use Donk\AigcCollectibles\Tests\Fake\FakeNftMintingService;
use Flarum\Extend;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;

class BlindBoxLifecycleTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    private const SEED = 'a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2';
    private const BOX_TYPE = 'test_reward';
    private const SUBJECT_PHRASES = [
        'dragon',
        'forest',
        'phoenix',
        'clockwork fox',
        'library automaton',
    ];
    private const STYLE_PHRASES = [
        'oil painting',
        'pixel art',
        'ink wash painting',
        'art nouveau poster',
        'isometric diorama',
        'noir comic panel',
        'bioluminescent concept art',
    ];
    private const OPTIONAL_FLAVOR_PHRASES = [
        'ethereal glow',
        'dark',
        'cosmic awe',
        'midnight bazaar',
        'solemn grandeur',
        'desert caravan',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('donk-aigc-collectibles');
        $this->extend(
            (new Extend\ServiceProvider())
                ->register(BlindBoxLifecycleTestServiceOverrides::class)
        );

        $this->prepareDatabase([
            'users' => [
                array_merge($this->normalUser(), ['blind_box_count' => 1]),
            ],
            'phrase_pools' => [
                ['id' => 1, 'category' => 'subject', 'phrase' => 'dragon',       'cost' => 5, 'is_active' => 1, 'created_at' => '2025-01-01 00:00:00', 'updated_at' => '2025-01-01 00:00:00'],
                ['id' => 2, 'category' => 'subject', 'phrase' => 'forest',       'cost' => 3, 'is_active' => 1, 'created_at' => '2025-01-01 00:00:00', 'updated_at' => '2025-01-01 00:00:00'],
                ['id' => 7, 'category' => 'subject', 'phrase' => 'phoenix',      'cost' => 5, 'is_active' => 1, 'created_at' => '2025-01-01 00:00:00', 'updated_at' => '2025-01-01 00:00:00'],
                ['id' => 8, 'category' => 'subject', 'phrase' => 'clockwork fox', 'cost' => 3, 'is_active' => 1, 'created_at' => '2025-01-01 00:00:00', 'updated_at' => '2025-01-01 00:00:00'],
                ['id' => 3, 'category' => 'style',   'phrase' => 'oil painting', 'cost' => 5, 'is_active' => 1, 'created_at' => '2025-01-01 00:00:00', 'updated_at' => '2025-01-01 00:00:00'],
                ['id' => 4, 'category' => 'style',   'phrase' => 'pixel art',    'cost' => 3, 'is_active' => 1, 'created_at' => '2025-01-01 00:00:00', 'updated_at' => '2025-01-01 00:00:00'],
                ['id' => 9, 'category' => 'style',   'phrase' => 'ink wash painting', 'cost' => 3, 'is_active' => 1, 'created_at' => '2025-01-01 00:00:00', 'updated_at' => '2025-01-01 00:00:00'],
                ['id' => 10, 'category' => 'style',  'phrase' => 'art nouveau poster', 'cost' => 4, 'is_active' => 1, 'created_at' => '2025-01-01 00:00:00', 'updated_at' => '2025-01-01 00:00:00'],
                ['id' => 5, 'category' => 'mood',    'phrase' => 'ethereal glow', 'cost' => 3, 'is_active' => 1, 'created_at' => '2025-01-01 00:00:00', 'updated_at' => '2025-01-01 00:00:00'],
                ['id' => 6, 'category' => 'mood',    'phrase' => 'dark',          'cost' => 1, 'is_active' => 1, 'created_at' => '2025-01-01 00:00:00', 'updated_at' => '2025-01-01 00:00:00'],
                ['id' => 11, 'category' => 'mood',   'phrase' => 'cosmic awe',    'cost' => 3, 'is_active' => 1, 'created_at' => '2025-01-01 00:00:00', 'updated_at' => '2025-01-01 00:00:00'],
                ['id' => 12, 'category' => 'theme',  'phrase' => 'midnight bazaar', 'cost' => 2, 'is_active' => 1, 'created_at' => '2025-01-01 00:00:00', 'updated_at' => '2025-01-01 00:00:00'],
            ],
            'blindbox_draw_rules' => [
                ['id' => 1, 'blindbox_type' => self::BOX_TYPE, 'pool_category' => 'subject', 'required' => 1, 'created_at' => '2025-01-01 00:00:00', 'updated_at' => '2025-01-01 00:00:00'],
                ['id' => 2, 'blindbox_type' => self::BOX_TYPE, 'pool_category' => 'style',   'required' => 1, 'created_at' => '2025-01-01 00:00:00', 'updated_at' => '2025-01-01 00:00:00'],
                ['id' => 3, 'blindbox_type' => self::BOX_TYPE, 'pool_category' => 'mood',    'required' => 0, 'created_at' => '2025-01-01 00:00:00', 'updated_at' => '2025-01-01 00:00:00'],
                ['id' => 4, 'blindbox_type' => self::BOX_TYPE, 'pool_category' => 'theme',   'required' => 0, 'created_at' => '2025-01-01 00:00:00', 'updated_at' => '2025-01-01 00:00:00'],
            ],
            'blindboxes' => [
                ['id' => 1, 'user_id' => 2, 'type' => self::BOX_TYPE, 'seed' => self::SEED, 'status' => 'unappraised', 'budget' => null, 'collectible_id' => null, 'created_at' => '2025-01-01 00:00:00', 'updated_at' => '2025-01-01 00:00:00'],
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
        $this->assertEquals(200, $response->getStatusCode(), (string) $response->getBody());

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

        $this->assertEquals(422, $response->getStatusCode(), (string) $response->getBody());

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

        $this->assertEquals(422, $response->getStatusCode(), (string) $response->getBody());
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

        $this->assertEquals(404, $response->getStatusCode(), (string) $response->getBody());
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

        $this->assertEquals(200, $response->getStatusCode(), (string) $response->getBody());

        $box = $this->database()->table('blindboxes')->where('id', 1)->first();
        $this->assertEquals('opened', $box->status);
        $this->assertNotNull($box->collectible_id);

        $user = $this->database()->table('users')->where('id', 2)->first();
        $this->assertSame(0, (int) $user->blind_box_count);

        $collectible = $this->database()->table('collectibles')
            ->where('id', $box->collectible_id)->first();
        $this->assertNotNull($collectible);
        $this->assertEquals(2, $collectible->owner_id);
        $this->assertEquals('common', $collectible->rarity);
        $this->assertEquals('completed', $collectible->status);
        $this->assertNotEmpty($collectible->aigc_prompt);
        $this->assertEquals(0, $collectible->times_traded);
    }

    /** @test */
    public function open_with_epic_budget_yields_epic_rarity(): void
    {
        $this->database()->table('blindboxes')
            ->where('id', 1)
            ->update(['status' => 'appraised', 'budget' => 80]);

        $response = $this->send(
            $this->request('POST', '/api/blindboxes/1/open', [
                'authenticatedAs' => 2,
            ])
        );

        $this->assertEquals(200, $response->getStatusCode(), (string) $response->getBody());

        $box = $this->database()->table('blindboxes')->where('id', 1)->first();
        $collectible = $this->database()->table('collectibles')
            ->where('id', $box->collectible_id)->first();
        $this->assertEquals('epic', $collectible->rarity);
    }

    /** @test */
    public function open_with_legendary_budget_yields_legendary_rarity(): void
    {
        $this->database()->table('blindboxes')
            ->where('id', 1)
            ->update(['status' => 'appraised', 'budget' => 160]);

        $response = $this->send(
            $this->request('POST', '/api/blindboxes/1/open', [
                'authenticatedAs' => 2,
            ])
        );

        $this->assertEquals(200, $response->getStatusCode(), (string) $response->getBody());

        $box = $this->database()->table('blindboxes')->where('id', 1)->first();
        $collectible = $this->database()->table('collectibles')
            ->where('id', $box->collectible_id)->first();
        $this->assertEquals('legendary', $collectible->rarity);
    }

    /** @test */
    public function appraise_pow_thresholds_map_to_expected_budgets_and_rarities(): void
    {
        $cases = [
            [
                'nonce' => '677e',
                'hash' => '0000c9a81bd8e958112838b1dea8f1a4492acf7bbe12672c5ccf32e9b05b2c10',
                'expectedBudget' => 20,
                'expectedRarity' => 'common',
            ],
            [
                'nonce' => 'a4d29',
                'hash' => '000006f455c40268e2f45f5c2e580ab7480eb778ffcc16befb5d4ee05a0cfd64',
                'expectedBudget' => 40,
                'expectedRarity' => 'rare',
            ],
            [
                'nonce' => '1741c3',
                'hash' => '000000e520056c62f64c09fe94950a8ef81bf0f782bac7f2f9f8dfedfb373eaa',
                'expectedBudget' => 80,
                'expectedRarity' => 'epic',
            ],
            [
                'nonce' => '54d04e',
                'hash' => '000000096bc7073227fda8663b84276aa85e9eaabbb9dc536a7d87218aec1139',
                'expectedBudget' => 160,
                'expectedRarity' => 'legendary',
            ],
        ];

        foreach ($cases as $index => $case) {
            $this->database()->table('blindboxes')
                ->where('id', 1)
                ->update([
                    'status' => 'unappraised',
                    'budget' => null,
                    'collectible_id' => null,
                ]);

            $response = $this->send(
                $this->request('POST', '/api/blindboxes/1/appraise', [
                    'authenticatedAs' => 2,
                    'json' => [
                        'nonce' => $case['nonce'],
                        'hash'  => $case['hash'],
                    ],
                ])
            );

            $this->assertEquals(200, $response->getStatusCode(), 'appraise case '.$index.' failed: '.(string) $response->getBody());

            $box = $this->database()->table('blindboxes')->where('id', 1)->first();
            $this->assertSame($case['expectedBudget'], (int) $box->budget);

            $this->database()->table('blindboxes')
                ->where('id', 1)
                ->update(['status' => 'appraised']);

            $openResponse = $this->send(
                $this->request('POST', '/api/blindboxes/1/open', [
                    'authenticatedAs' => 2,
                ])
            );

            $this->assertEquals(200, $openResponse->getStatusCode(), 'open case '.$index.' failed: '.(string) $openResponse->getBody());

            $openedBox = $this->database()->table('blindboxes')->where('id', 1)->first();
            $collectible = $this->database()->table('collectibles')
                ->where('id', $openedBox->collectible_id)
                ->first();

            $this->assertSame($case['expectedRarity'], $collectible->rarity);

            $this->database()->table('collectibles')->delete();
            $this->database()->table('blindboxes')
                ->where('id', 1)
                ->update([
                    'status' => 'unappraised',
                    'budget' => null,
                    'collectible_id' => null,
                ]);
            $this->database()->table('users')->where('id', 2)->update(['blind_box_count' => 1]);
        }
    }

    /** @test */
    public function trade_reward_boxes_fallback_to_default_reward_rules_when_specific_rules_are_missing(): void
    {
        $this->database()->table('blindboxes')
            ->where('id', 1)
            ->update(['type' => 'trade_reward', 'status' => 'appraised', 'budget' => 20]);

        $this->database()->table('blindbox_draw_rules')->delete();
        $this->database()->table('blindbox_draw_rules')->insert([
            ['id' => 11, 'blindbox_type' => 'checkin_reward', 'pool_category' => 'subject', 'required' => 1, 'created_at' => '2025-01-01 00:00:00', 'updated_at' => '2025-01-01 00:00:00'],
            ['id' => 12, 'blindbox_type' => 'checkin_reward', 'pool_category' => 'style', 'required' => 1, 'created_at' => '2025-01-01 00:00:00', 'updated_at' => '2025-01-01 00:00:00'],
            ['id' => 13, 'blindbox_type' => 'checkin_reward', 'pool_category' => 'mood', 'required' => 0, 'created_at' => '2025-01-01 00:00:00', 'updated_at' => '2025-01-01 00:00:00'],
        ]);

        $response = $this->send(
            $this->request('POST', '/api/blindboxes/1/open', [
                'authenticatedAs' => 2,
            ])
        );

        $this->assertEquals(200, $response->getStatusCode(), (string) $response->getBody());

        $box = $this->database()->table('blindboxes')->where('id', 1)->first();
        $this->assertEquals('opened', $box->status);
        $this->assertNotNull($box->collectible_id);
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

        $this->assertEquals(422, $response->getStatusCode(), (string) $response->getBody());

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

        $this->assertEquals(422, $response->getStatusCode(), (string) $response->getBody());
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
        $this->assertEquals(200, $response->getStatusCode(), (string) $response->getBody());

        $box = $this->database()->table('blindboxes')->where('id', 1)->first();
        $this->assertEquals('appraised', $box->status);
        $this->assertGreaterThan(0, $box->budget);

        // 2. Open
        $response = $this->send(
            $this->request('POST', '/api/blindboxes/1/open', [
                'authenticatedAs' => 2,
            ])
        );
        $this->assertEquals(200, $response->getStatusCode(), (string) $response->getBody());

        $box = $this->database()->table('blindboxes')->where('id', 1)->first();
        $this->assertEquals('opened', $box->status);

        $collectible = $this->database()->table('collectibles')
            ->where('id', $box->collectible_id)->first();
        $this->assertEquals(2, $collectible->owner_id);
        $this->assertNotEmpty($collectible->aigc_prompt);

        $user = $this->database()->table('users')->where('id', 2)->first();
        $this->assertSame(0, (int) $user->blind_box_count);

        // Prompt should contain at least one subject and one style (both required)
        $prompt = $collectible->aigc_prompt;
        $hasSubject = $this->promptContainsAny($prompt, self::SUBJECT_PHRASES);
        $hasStyle = $this->promptContainsAny($prompt, self::STYLE_PHRASES);
        $this->assertTrue($hasSubject, "Prompt should contain a subject phrase: {$prompt}");
        $this->assertTrue($hasStyle, "Prompt should contain a style phrase: {$prompt}");

        $hasOptionalFlavor = $this->promptContainsAny($prompt, self::OPTIONAL_FLAVOR_PHRASES);
        $this->assertTrue($hasOptionalFlavor, "Prompt should contain mood or theme flavor: {$prompt}");
    }

    /* ═══════════════════════ Helpers ═══════════════════════ */

    /**
     * Brute-force a PoW nonce for the given seed.
     * Finds the nonce that produces the most leading zeros in SHA-256(seed + nonce_hex).
     * Stops early once ≥3 leading zeros are found so tests do not brute-force longer than needed.
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

                if ($zeros >= 3) {
                    break;
                }
            }
        }

        return ['nonce' => $nonce, 'hash' => $hash, 'zeros' => $bestZeros];
    }

    /**
     * @param string[] $phrases
     */
    private function promptContainsAny(string $prompt, array $phrases): bool
    {
        foreach ($phrases as $phrase) {
            if (str_contains($prompt, $phrase)) {
                return true;
            }
        }

        return false;
    }
}

class BlindBoxLifecycleTestServiceOverrides extends \Flarum\Foundation\AbstractServiceProvider
{
    public function register(): void
    {
        $this->container->singleton(AIGCServiceInterface::class, FakeAIGCService::class);
        $this->container->singleton(IPFSServiceInterface::class, FakeIPFSService::class);
        $this->container->singleton(NftMintingServiceInterface::class, FakeNftMintingService::class);
    }
}
