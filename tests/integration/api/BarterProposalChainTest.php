<?php

namespace Donk\AigcCollectibles\Tests\integration\api;

use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use PHPUnit\Framework\Attributes\Test;

class BarterProposalChainTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    private const ALICE_ID = 31;
    private const BOB_ID = 32;
    private const ALICE_COLLECTIBLE_ID = 111;
    private const BOB_COLLECTIBLE_ID = 112;
    private const ALICE_BOX_UNAPPRAISED_ID = 1201;
    private const ALICE_BOX_APPRAISED_ID = 1202;
    private const BOB_BOX_UNAPPRAISED_ID = 1301;
    private const BOB_BOX_APPRAISED_ID = 1302;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('donk-aigc-collectibles');

        $this->prepareDatabase([
            'users' => [
                $this->normalUser(),
                [
                    'id' => self::ALICE_ID,
                    'username' => 'barterAlice',
                    'email' => 'barter-alice@test.com',
                    'is_email_confirmed' => 1,
                    'blind_box_count' => 2,
                    'showcase_collectible_id' => self::ALICE_COLLECTIBLE_ID,
                ],
                [
                    'id' => self::BOB_ID,
                    'username' => 'barterBob',
                    'email' => 'barter-bob@test.com',
                    'is_email_confirmed' => 1,
                    'blind_box_count' => 2,
                ],
            ],
            'collectibles' => [
                [
                    'id' => self::ALICE_COLLECTIBLE_ID,
                    'owner_id' => self::ALICE_ID,
                    'rarity' => 'epic',
                    'status' => 'completed',
                    'ipfs_cid' => 'QmBarterAliceGem',
                    'metadata_cid' => 'QmBarterAliceGemMeta',
                    'token_id' => 312,
                    'times_traded' => 0,
                    'created_at' => '2026-01-01 00:00:00',
                    'updated_at' => '2026-01-01 00:00:00',
                ],
                [
                    'id' => self::BOB_COLLECTIBLE_ID,
                    'owner_id' => self::BOB_ID,
                    'rarity' => 'rare',
                    'status' => 'completed',
                    'ipfs_cid' => 'QmBarterBobGem',
                    'metadata_cid' => 'QmBarterBobGemMeta',
                    'token_id' => null,
                    'times_traded' => 0,
                    'created_at' => '2026-01-01 00:00:00',
                    'updated_at' => '2026-01-01 00:00:00',
                ],
            ],
            'blindboxes' => [
                [
                    'id' => self::ALICE_BOX_UNAPPRAISED_ID,
                    'user_id' => self::ALICE_ID,
                    'type' => 'checkin_reward',
                    'seed' => str_repeat('a', 64),
                    'status' => 'unappraised',
                    'budget' => null,
                    'collectible_id' => null,
                    'created_at' => '2026-01-10 00:00:00',
                    'updated_at' => '2026-01-10 00:00:00',
                ],
                [
                    'id' => self::ALICE_BOX_APPRAISED_ID,
                    'user_id' => self::ALICE_ID,
                    'type' => 'checkin_reward',
                    'seed' => str_repeat('b', 64),
                    'status' => 'appraised',
                    'budget' => 20,
                    'collectible_id' => null,
                    'created_at' => '2026-01-10 00:00:00',
                    'updated_at' => '2026-01-10 00:00:00',
                ],
                [
                    'id' => self::BOB_BOX_UNAPPRAISED_ID,
                    'user_id' => self::BOB_ID,
                    'type' => 'checkin_reward',
                    'seed' => str_repeat('c', 64),
                    'status' => 'unappraised',
                    'budget' => null,
                    'collectible_id' => null,
                    'created_at' => '2026-01-10 00:00:00',
                    'updated_at' => '2026-01-10 00:00:00',
                ],
                [
                    'id' => self::BOB_BOX_APPRAISED_ID,
                    'user_id' => self::BOB_ID,
                    'type' => 'checkin_reward',
                    'seed' => str_repeat('d', 64),
                    'status' => 'appraised',
                    'budget' => 40,
                    'collectible_id' => null,
                    'created_at' => '2026-01-10 00:00:00',
                    'updated_at' => '2026-01-10 00:00:00',
                ],
            ],
        ]);
    }

    #[Test]
    public function proposer_can_create_a_thread_bound_mixed_asset_barter_proposal(): void
    {
        $threadId = 9077;
        $this->createDialog($threadId, [self::ALICE_ID, self::BOB_ID]);

        $response = $this->send(
            $this->request('POST', '/api/barter-proposals', [
                'authenticatedAs' => self::ALICE_ID,
                'json' => [
                    'data' => [
                        'attributes' => [
                            'threadType' => 'dialog',
                            'threadId' => $threadId,
                            'counterpartyUserId' => self::BOB_ID,
                            'message' => 'My collectible plus one box for your collectible.',
                            'items' => [
                                [
                                    'ownerUserId' => self::ALICE_ID,
                                    'assetType' => 'collectible',
                                    'assetId' => self::ALICE_COLLECTIBLE_ID,
                                ],
                                [
                                    'ownerUserId' => self::ALICE_ID,
                                    'assetType' => 'blind_box',
                                    'assetId' => self::ALICE_BOX_UNAPPRAISED_ID,
                                ],
                                [
                                    'ownerUserId' => self::BOB_ID,
                                    'assetType' => 'collectible',
                                    'assetId' => self::BOB_COLLECTIBLE_ID,
                                ],
                            ],
                        ],
                    ],
                ],
            ])
        );

        $this->assertContains($response->getStatusCode(), [200, 201]);

        $body = json_decode((string) $response->getBody(), true);
        $this->assertSame('barter-proposals', $body['data']['type']);
        $this->assertSame('proposed', $body['data']['attributes']['status']);
        $this->assertSame('dialog', $body['data']['attributes']['threadType']);
        $this->assertSame($threadId, $body['data']['attributes']['threadId']);

        $proposal = $this->database()->table('barter_proposals')->where('thread_id', $threadId)->first();
        $this->assertNotNull($proposal);
        $this->assertSame(self::ALICE_ID, (int) $proposal->proposer_user_id);
        $this->assertSame(self::BOB_ID, (int) $proposal->counterparty_user_id);
        $this->assertSame(1, (int) $proposal->revision_number);

        $items = $this->database()->table('barter_proposal_items')
            ->where('proposal_id', $proposal->id)
            ->orderBy('position')
            ->get();

        $this->assertCount(3, $items);
        $this->assertSame('collectible', $items[0]->asset_type);
        $this->assertSame('blind_box', $items[1]->asset_type);
        $this->assertSame('collectible', $items[2]->asset_type);
    }

    #[Test]
    public function counterparty_can_accept_and_settle_a_mixed_asset_barter_proposal(): void
    {
        $threadId = 9078;
        $this->createDialog($threadId, [self::ALICE_ID, self::BOB_ID]);
        $proposalId = $this->seedProposal($threadId);

        $response = $this->send(
            $this->request('POST', '/api/barter-proposals/' . $proposalId . '/accept', [
                'authenticatedAs' => self::BOB_ID,
            ])
        );

        $this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());

        $proposal = $this->database()->table('barter_proposals')->where('id', $proposalId)->first();
        $this->assertSame('completed', $proposal->status);
        $this->assertSame(self::BOB_ID, (int) $proposal->accepted_by_user_id);
        $this->assertNotNull($proposal->completed_at);

        $aliceCollectible = $this->database()->table('collectibles')->where('id', self::ALICE_COLLECTIBLE_ID)->first();
        $bobCollectible = $this->database()->table('collectibles')->where('id', self::BOB_COLLECTIBLE_ID)->first();
        $this->assertSame(self::BOB_ID, (int) $aliceCollectible->owner_id);
        $this->assertSame(1, (int) $aliceCollectible->times_traded);
        $this->assertSame(self::ALICE_ID, (int) $bobCollectible->owner_id);
        $this->assertSame(1, (int) $bobCollectible->times_traded);

        $alice = $this->database()->table('users')->where('id', self::ALICE_ID)->first();
        $bob = $this->database()->table('users')->where('id', self::BOB_ID)->first();
        $this->assertNull($alice->showcase_collectible_id);
        $this->assertSame(2, (int) $alice->blind_box_count);
        $this->assertSame(2, (int) $bob->blind_box_count);

        $this->assertSame(self::BOB_ID, (int) $this->database()->table('blindboxes')->where('id', self::ALICE_BOX_UNAPPRAISED_ID)->value('user_id'));
        $this->assertSame(self::ALICE_ID, (int) $this->database()->table('blindboxes')->where('id', self::BOB_BOX_UNAPPRAISED_ID)->value('user_id'));

        $events = $this->database()->table('collectible_events')
            ->where('event_type', 'traded')
            ->orderBy('collectible_id')
            ->get();

        $this->assertCount(2, $events);
        $this->assertSame(self::ALICE_ID, (int) $events[0]->from_user_id);
        $this->assertSame(self::BOB_ID, (int) $events[0]->to_user_id);
    }

    #[Test]
    public function counterparty_can_reject_and_proposer_can_cancel_a_barter_proposal(): void
    {
        $rejectThreadId = 9079;
        $this->createDialog($rejectThreadId, [self::ALICE_ID, self::BOB_ID]);
        $rejectProposalId = $this->seedProposal($rejectThreadId);

        $rejectResponse = $this->send(
            $this->request('POST', '/api/barter-proposals/' . $rejectProposalId . '/reject', [
                'authenticatedAs' => self::BOB_ID,
            ])
        );

        $this->assertSame(200, $rejectResponse->getStatusCode());
        $this->assertSame(
            'rejected',
            $this->database()->table('barter_proposals')->where('id', $rejectProposalId)->value('status')
        );

        $cancelThreadId = 9080;
        $this->createDialog($cancelThreadId, [self::ALICE_ID, self::BOB_ID]);
        $cancelProposalId = $this->seedProposal($cancelThreadId);

        $cancelResponse = $this->send(
            $this->request('POST', '/api/barter-proposals/' . $cancelProposalId . '/cancel', [
                'authenticatedAs' => self::ALICE_ID,
            ])
        );

        $this->assertSame(200, $cancelResponse->getStatusCode());
        $this->assertSame(
            'cancelled',
            $this->database()->table('barter_proposals')->where('id', $cancelProposalId)->value('status')
        );
    }

    #[Test]
    public function creating_a_counterproposal_supersedes_the_previous_revision(): void
    {
        $threadId = 9081;
        $this->createDialog($threadId, [self::ALICE_ID, self::BOB_ID]);
        $proposalId = $this->seedProposal($threadId);

        $response = $this->send(
            $this->request('POST', '/api/barter-proposals', [
                'authenticatedAs' => self::BOB_ID,
                'json' => [
                    'data' => [
                        'attributes' => [
                            'threadType' => 'dialog',
                            'threadId' => $threadId,
                            'counterpartyUserId' => self::ALICE_ID,
                            'replacesProposalId' => $proposalId,
                            'message' => 'Counterproposal.',
                            'items' => [
                                [
                                    'ownerUserId' => self::BOB_ID,
                                    'assetType' => 'collectible',
                                    'assetId' => self::BOB_COLLECTIBLE_ID,
                                ],
                                [
                                    'ownerUserId' => self::BOB_ID,
                                    'assetType' => 'blind_box',
                                    'assetId' => self::BOB_BOX_UNAPPRAISED_ID,
                                ],
                                [
                                    'ownerUserId' => self::ALICE_ID,
                                    'assetType' => 'collectible',
                                    'assetId' => self::ALICE_COLLECTIBLE_ID,
                                ],
                                [
                                    'ownerUserId' => self::ALICE_ID,
                                    'assetType' => 'blind_box',
                                    'assetId' => self::ALICE_BOX_UNAPPRAISED_ID,
                                ],
                            ],
                        ],
                    ],
                ],
            ])
        );

        $this->assertContains($response->getStatusCode(), [200, 201]);

        $body = json_decode((string) $response->getBody(), true);
        $this->assertSame((string) $proposalId, $body['data']['relationships']['replacesProposal']['data']['id']);

        $original = $this->database()->table('barter_proposals')->where('id', $proposalId)->first();
        $replacement = $this->database()->table('barter_proposals')->where('replaces_proposal_id', $proposalId)->first();

        $this->assertSame('superseded', $original->status);
        $this->assertNotNull($replacement);
        $this->assertSame(2, (int) $replacement->revision_number);
        $this->assertSame(self::BOB_ID, (int) $replacement->proposer_user_id);

        $listResponse = $this->send(
            $this->request('GET', '/api/barter-proposals?threadType=dialog&threadId=' . $threadId . '&include=replacesProposal', [
                'authenticatedAs' => self::ALICE_ID,
            ])
        );

        $this->assertSame(200, $listResponse->getStatusCode(), (string) $listResponse->getBody());

        $listBody = json_decode((string) $listResponse->getBody(), true);
        $replacementResource = collect($listBody['data'])
            ->first(fn (array $item): bool => (int) $item['id'] === (int) $replacement->id);

        $this->assertNotNull($replacementResource);
        $this->assertSame((string) $proposalId, $replacementResource['relationships']['replacesProposal']['data']['id']);
    }

    #[Test]
    public function blind_box_index_can_be_filtered_by_counterparty_in_barter_flow(): void
    {
        $threadId = 9082;
        $this->createDialog($threadId, [self::ALICE_ID, self::BOB_ID]);

        $response = $this->send(
            $this->request('GET', '/api/blindboxes?filter[user]=' . self::BOB_ID . '&filter[dialog]=' . $threadId, [
                'authenticatedAs' => self::ALICE_ID,
            ])
        );

        $this->assertSame(200, $response->getStatusCode());

        $body = json_decode((string) $response->getBody(), true);
        $ids = array_map(
            static fn (array $item): int => (int) $item['id'],
            $body['data']
        );

        sort($ids);

        $this->assertSame([self::BOB_BOX_UNAPPRAISED_ID, self::BOB_BOX_APPRAISED_ID], $ids);
    }

    #[Test]
    public function barter_assets_endpoint_returns_both_sides_assets_for_a_direct_dialog(): void
    {
        $threadId = 9083;
        $this->createDialog($threadId, [self::ALICE_ID, self::BOB_ID]);

        $response = $this->send(
            $this->request('GET', '/api/barter-assets?filter[threadType]=dialog&filter[threadId]=' . $threadId . '&filter[counterpartyUserId]=' . self::BOB_ID, [
                'authenticatedAs' => self::ALICE_ID,
            ])
        );

        $this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());

        $body = json_decode((string) $response->getBody(), true);
        $this->assertSame(self::ALICE_ID, $body['data']['actorUserId']);
        $this->assertSame(self::BOB_ID, $body['data']['counterpartyUserId']);

        $myCollectibleIds = array_map(
            static fn (array $item): int => (int) $item['id'],
            $body['data']['yours']['collectibles']
        );
        $theirCollectibleIds = array_map(
            static fn (array $item): int => (int) $item['id'],
            $body['data']['theirs']['collectibles']
        );
        $myBlindBoxIds = array_map(
            static fn (array $item): int => (int) $item['id'],
            $body['data']['yours']['blindBoxes']
        );
        $theirBlindBoxIds = array_map(
            static fn (array $item): int => (int) $item['id'],
            $body['data']['theirs']['blindBoxes']
        );

        sort($myCollectibleIds);
        sort($theirCollectibleIds);
        sort($myBlindBoxIds);
        sort($theirBlindBoxIds);

        $this->assertSame([self::ALICE_COLLECTIBLE_ID], $myCollectibleIds);
        $this->assertSame([self::BOB_COLLECTIBLE_ID], $theirCollectibleIds);
        $this->assertSame([self::ALICE_BOX_UNAPPRAISED_ID, self::ALICE_BOX_APPRAISED_ID], $myBlindBoxIds);
        $this->assertSame([self::BOB_BOX_UNAPPRAISED_ID, self::BOB_BOX_APPRAISED_ID], $theirBlindBoxIds);
    }

    private function createDialog(int $dialogId, array $userIds): void
    {
        $db = $this->database();
        $schema = $db->getSchemaBuilder();

        if (! $schema->hasTable('dialogs')) {
            $schema->create('dialogs', function ($table) {
                $table->increments('id');
                $table->unsignedInteger('first_message_id')->nullable();
                $table->unsignedInteger('last_message_id')->nullable();
                $table->dateTime('last_message_at')->nullable();
                $table->unsignedInteger('last_message_user_id')->nullable();
                $table->string('type');
                $table->timestamps();
            });
        }

        if (! $schema->hasTable('dialog_user')) {
            $schema->create('dialog_user', function ($table) {
                $table->increments('id');
                $table->unsignedInteger('dialog_id');
                $table->unsignedInteger('user_id');
                $table->dateTime('joined_at');
                $table->unsignedInteger('last_read_message_id')->default(0);
                $table->dateTime('last_read_at')->nullable();
            });
        }

        if (! $db->table('dialogs')->where('id', $dialogId)->exists()) {
            $db->table('dialogs')->insert([
                'id' => $dialogId,
                'first_message_id' => null,
                'last_message_id' => null,
                'last_message_at' => null,
                'last_message_user_id' => null,
                'type' => 'direct',
                'created_at' => '2026-01-01 00:00:00',
                'updated_at' => '2026-01-01 00:00:00',
            ]);
        }

        foreach ($userIds as $userId) {
            if (
                ! $db->table('dialog_user')
                    ->where('dialog_id', $dialogId)
                    ->where('user_id', $userId)
                    ->exists()
            ) {
                $db->table('dialog_user')->insert([
                    'dialog_id' => $dialogId,
                    'user_id' => $userId,
                    'joined_at' => '2026-01-01 00:00:00',
                    'last_read_message_id' => 0,
                    'last_read_at' => null,
                ]);
            }
        }
    }

    private function seedProposal(int $threadId): int
    {
        $proposalId = 20000 + $threadId;

        $this->database()->table('barter_proposals')->insert([
            'id' => $proposalId,
            'thread_type' => 'dialog',
            'thread_id' => $threadId,
            'proposer_user_id' => self::ALICE_ID,
            'counterparty_user_id' => self::BOB_ID,
            'accepted_by_user_id' => null,
            'replaces_proposal_id' => null,
            'revision_number' => 1,
            'status' => 'proposed',
            'message' => 'Initial barter.',
            'completed_at' => null,
            'created_at' => '2026-01-15 00:00:00',
            'updated_at' => '2026-01-15 00:00:00',
        ]);

        $this->database()->table('barter_proposal_items')->insert([
            [
                'proposal_id' => $proposalId,
                'owner_user_id' => self::ALICE_ID,
                'asset_type' => 'collectible',
                'asset_id' => self::ALICE_COLLECTIBLE_ID,
                'position' => 0,
                'snapshot' => json_encode(['kind' => 'collectible', 'name' => 'Collectible #111']),
                'created_at' => '2026-01-15 00:00:00',
                'updated_at' => '2026-01-15 00:00:00',
            ],
            [
                'proposal_id' => $proposalId,
                'owner_user_id' => self::ALICE_ID,
                'asset_type' => 'blind_box',
                'asset_id' => self::ALICE_BOX_UNAPPRAISED_ID,
                'position' => 1,
                'snapshot' => json_encode(['kind' => 'blind_box', 'type' => 'checkin_reward']),
                'created_at' => '2026-01-15 00:00:00',
                'updated_at' => '2026-01-15 00:00:00',
            ],
            [
                'proposal_id' => $proposalId,
                'owner_user_id' => self::BOB_ID,
                'asset_type' => 'collectible',
                'asset_id' => self::BOB_COLLECTIBLE_ID,
                'position' => 2,
                'snapshot' => json_encode(['kind' => 'collectible', 'name' => 'Collectible #112']),
                'created_at' => '2026-01-15 00:00:00',
                'updated_at' => '2026-01-15 00:00:00',
            ],
            [
                'proposal_id' => $proposalId,
                'owner_user_id' => self::BOB_ID,
                'asset_type' => 'blind_box',
                'asset_id' => self::BOB_BOX_UNAPPRAISED_ID,
                'position' => 3,
                'snapshot' => json_encode(['kind' => 'blind_box', 'type' => 'checkin_reward']),
                'created_at' => '2026-01-15 00:00:00',
                'updated_at' => '2026-01-15 00:00:00',
            ],
        ]);

        return $proposalId;
    }
}
