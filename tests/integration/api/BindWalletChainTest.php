<?php

namespace Donk\AigcCollectibles\Tests\integration\api;

use Donk\AigcCollectibles\Service\Contracts\BlockchainServiceInterface;
use Donk\AigcCollectibles\Tests\Fake\FakeBlockchainService;
use Flarum\Extend;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;

class BindWalletChainTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('donk-aigc-collectibles');

        // Override BlockchainService with our fake
        $this->extend(
            (new Extend\ServiceProvider())
                ->register(WalletTestServiceOverrides::class)
        );

        $this->prepareDatabase([
            'users' => [
                $this->normalUser(),
                ['id' => 3, 'username' => 'walletUser', 'email' => 'wallet@test.com', 'is_email_confirmed' => 1],
            ],
            'web3_accounts' => [
                ['id' => 1, 'user_id' => 3, 'address' => '0xalreadybound1234567890abcdef12345678', 'source' => 'metamask', 'type' => 'evm', 'attached_at' => '2026-01-01 00:00:00', 'last_verified_at' => '2026-01-01 00:00:00'],
            ],
        ]);
    }

    /** @test */
public function guest_cannot_request_nonce(): void
{
    $response = $this->send(
        $this->request('POST', '/api/web3-accounts/nonce', [
            'authenticatedAs' => null,           // 明确是 guest
            'json' => [
                'data' => [
                    'type' => 'web3-accounts',   // ← 关键！（99% 是这个）
                    // 如果你的 Resource 里定义的 type 不是这个，再改
                    'attributes' => [
                        'address' => '0x1234567890abcdef1234567890abcdef12345678',
                    ],
                ],
            ],
        ])
    );

    $this->assertEquals(401, $response->getStatusCode());

    // 保险起见：如果还是 400，打印真实错误
    if ($response->getStatusCode() === 400) {
        echo "=== 400 真实返回体 ===\n";
        echo (string) $response->getBody() . "\n";
        $this->fail('Still 400，请把上面输出贴给我');
    }
}

        $this->assertEquals(401, $response->getStatusCode());
    if ($response->getStatusCode() === 400) {
        echo "=== 400 真实返回 ===\n";
        echo (string) $response->getBody() . "\n";
        $this->fail('Guest 请求仍返回 400，请把上面输出贴给我');
    }
    }

    /** @test */
    public function user_can_request_nonce(): void
    {
        $response = $this->send(
            $this->request('POST', '/api/web3-accounts/nonce', [
                'authenticatedAs' => 2,
                'json' => [
                    'data' => [
                        'attributes' => [
                            'address' => '0x1234567890abcdef1234567890abcdef12345678',
                        ],
                    ],
                ],
            ])
        );

        $this->assertEquals(200, $response->getStatusCode());

        $body = json_decode((string) $response->getBody(), true);
        $this->assertArrayHasKey('data', $body);
        $this->assertArrayHasKey('attributes', $body['data']);
        $this->assertNotEmpty($body['data']['attributes']['nonce']);
        $this->assertNotEmpty($body['data']['attributes']['message']);
    }

    /** @test */
    public function nonce_rejects_invalid_address(): void
    {
        $response = $this->send(
            $this->request('POST', '/api/web3-accounts/nonce', [
                'authenticatedAs' => 2,
                'json' => [
                    'data' => [
                        'attributes' => [
                            'address' => 'not-a-valid-address',
                        ],
                    ],
                ],
            ])
        );

        $this->assertEquals(422, $response->getStatusCode());
    }

    /** @test */
    public function full_bind_wallet_flow(): void
    {
        $address = '0xaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

        // Step 1: Request nonce
        $nonceResponse = $this->send(
            $this->request('POST', '/api/web3-accounts/nonce', [
                'authenticatedAs' => 2,
                'json' => [
                    'data' => [
                        'attributes' => [
                            'address' => $address,
                        ],
                    ],
                ],
            ])
        );

        $this->assertEquals(200, $nonceResponse->getStatusCode());
        $nonceBody = json_decode((string) $nonceResponse->getBody(), true);
        $nonce = $nonceBody['data']['attributes']['nonce'];

        // Step 2: Submit verification (with fake blockchain service that always verifies true)
        $verifyResponse = $this->send(
            $this->request('POST', '/api/web3-accounts', [
                'authenticatedAs' => 2,
                'json' => [
                    'data' => [
                        'attributes' => [
                            'address' => $address,
                            'signature' => '0xfakesignature',
                            'nonce' => $nonce,
                        ],
                    ],
                ],
            ])
        );

        $this->assertEquals(200, $verifyResponse->getStatusCode());

        $body = json_decode((string) $verifyResponse->getBody(), true);
        $this->assertEquals('web3-accounts', $body['data']['type']);
        $this->assertEquals(strtolower($address), $body['data']['attributes']['address']);
        $this->assertEquals('evm', $body['data']['attributes']['type']);

        // Verify in database
        $account = $this->database()->table('web3_accounts')
            ->where('user_id', 2)
            ->first();
        $this->assertNotNull($account);
        $this->assertEquals(strtolower($address), $account->address);
    }

    /** @test */
    public function user_with_existing_wallet_cannot_bind_another(): void
    {
        $address = '0xbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';

        // Step 1: Request nonce for user 3 (who already has a wallet)
        $nonceResponse = $this->send(
            $this->request('POST', '/api/web3-accounts/nonce', [
                'authenticatedAs' => 3,
                'json' => [
                    'data' => [
                        'attributes' => [
                            'address' => $address,
                        ],
                    ],
                ],
            ])
        );

        $this->assertEquals(200, $nonceResponse->getStatusCode());
        $nonceBody = json_decode((string) $nonceResponse->getBody(), true);
        $nonce = $nonceBody['data']['attributes']['nonce'];

        // Step 2: Try to bind — should fail
        $verifyResponse = $this->send(
            $this->request('POST', '/api/web3-accounts', [
                'authenticatedAs' => 3,
                'json' => [
                    'data' => [
                        'attributes' => [
                            'address' => $address,
                            'signature' => '0xfakesignature',
                            'nonce' => $nonce,
                        ],
                    ],
                ],
            ])
        );

        $this->assertEquals(422, $verifyResponse->getStatusCode());
    }

    /** @test */
    public function already_bound_address_cannot_be_rebound(): void
    {
        // Try to bind the address that user 3 already has
        $address = '0xalreadybound1234567890abcdef12345678';

        $nonceResponse = $this->send(
            $this->request('POST', '/api/web3-accounts/nonce', [
                'authenticatedAs' => 2,
                'json' => [
                    'data' => [
                        'attributes' => [
                            'address' => $address,
                        ],
                    ],
                ],
            ])
        );

        $this->assertEquals(200, $nonceResponse->getStatusCode());
        $nonceBody = json_decode((string) $nonceResponse->getBody(), true);
        $nonce = $nonceBody['data']['attributes']['nonce'];

        $verifyResponse = $this->send(
            $this->request('POST', '/api/web3-accounts', [
                'authenticatedAs' => 2,
                'json' => [
                    'data' => [
                        'attributes' => [
                            'address' => $address,
                            'signature' => '0xfakesig',
                            'nonce' => $nonce,
                        ],
                    ],
                ],
            ])
        );

        $this->assertEquals(422, $verifyResponse->getStatusCode());
    }
}

class WalletTestServiceOverrides extends \Flarum\Foundation\AbstractServiceProvider
{
    public function register(): void
    {
        $this->container->singleton(BlockchainServiceInterface::class, FakeBlockchainService::class);
    }
}
