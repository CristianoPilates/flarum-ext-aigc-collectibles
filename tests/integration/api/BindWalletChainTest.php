<?php

namespace Donk\AigcCollectibles\Tests\integration\api;

use Donk\AigcCollectibles\Service\Contracts\WalletVerificationServiceInterface;
use Donk\AigcCollectibles\Tests\Fake\FakeWalletVerificationService;
use Flarum\Extend;
use Flarum\Foundation\AbstractServiceProvider;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use PHPUnit\Framework\Attributes\Test;

class BindWalletChainTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('donk-aigc-collectibles');

        // Override WalletVerificationService with our fake
        $this->extend(
            (new Extend\ServiceProvider)
                ->register(WalletTestServiceOverrides::class)
        );

        $this->prepareDatabase([
            'users' => [
                $this->normalUser(),
                ['id' => 3, 'username' => 'walletUser', 'email' => 'wallet@test.com', 'is_email_confirmed' => 1],
            ],
            'web3_accounts' => [
                ['id' => 1, 'user_id' => 3, 'address' => '0x1111111111111111111111111111111111111111', 'source' => 'metamask', 'type' => 'evm', 'attached_at' => '2026-01-01 00:00:00', 'last_verified_at' => '2026-01-01 00:00:00'],
            ],
        ]);
    }

    #[Test]
    public function guest_cannot_request_nonce(): void
    {
        $request = $this->request('POST', '/api/web3-accounts/nonce', [
            'authenticatedAs' => null,
            'json' => [
                'data' => [
                    'type' => 'web3-accounts',
                    'attributes' => [
                        'address' => '0x1234567890abcdef1234567890abcdef12345678',
                    ],
                ],
            ],
        ])->withAttribute('bypassCsrfToken', true);

        $response = $this->send($request);
        $body = (string) $response->getBody();

        if ($response->getStatusCode() !== 401) {
            fwrite(STDERR, sprintf(
                "[guest_cannot_request_nonce] unexpected response. status=%d body=%s\n",
                $response->getStatusCode(),
                $body
            ));
        }

        $this->assertSame(401, $response->getStatusCode(), $body);
    }

    #[Test]
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
        $this->assertNotEmpty($body['data']['attributes']['nonce'] ?? null, json_encode($body));
        $this->assertNotEmpty($body['data']['attributes']['message'] ?? null, json_encode($body));
    }

    #[Test]
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

    #[Test]
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
                            'signature' => '0x' . str_repeat('a', 130),
                            'nonce' => $nonce,
                        ],
                    ],
                ],
            ])
        );

        $this->assertEquals(200, $verifyResponse->getStatusCode(), (string) $verifyResponse->getBody());

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

    #[Test]
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
                            'signature' => '0x' . str_repeat('b', 130),
                            'nonce' => $nonce,
                        ],
                    ],
                ],
            ])
        );

        $this->assertEquals(422, $verifyResponse->getStatusCode(), (string) $verifyResponse->getBody());
    }

    #[Test]
    public function already_bound_address_cannot_be_rebound(): void
    {
        // Try to bind the address that user 3 already has
        $address = '0x1111111111111111111111111111111111111111';

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
                            'signature' => '0x' . str_repeat('c', 130),
                            'nonce' => $nonce,
                        ],
                    ],
                ],
            ])
        );

        $this->assertEquals(422, $verifyResponse->getStatusCode(), (string) $verifyResponse->getBody());
    }
}

class WalletTestServiceOverrides extends AbstractServiceProvider
{
    public function register(): void
    {
        $this->container->singleton(WalletVerificationServiceInterface::class, FakeWalletVerificationService::class);
    }
}
