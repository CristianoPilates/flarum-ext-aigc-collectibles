<?php

namespace Donk\AigcCollectibles\Support;

use Flarum\Foundation\ValidationException;
use Illuminate\Database\Connection;
use Illuminate\Database\ConnectionInterface;

class DirectDialogParticipants
{
    public function __construct(
        private readonly ConnectionInterface $db,
    ) {}

    /**
     * @return int[]
     */
    public function resolve(int $dialogId, string $errorKey = 'thread'): array
    {
        if (! $this->db instanceof Connection) {
            return [];
        }

        $schema = $this->db->getSchemaBuilder();

        if (! $schema->hasTable('dialogs') || ! $schema->hasTable('dialog_user')) {
            return [];
        }

        $dialog = $this->db->table('dialogs')->where('id', $dialogId)->first();

        if (! $dialog) {
            throw new ValidationException([$errorKey => 'Private message dialog not found.']);
        }

        if (($dialog->type ?? null) !== 'direct') {
            throw new ValidationException([$errorKey => 'Only direct private message dialogs are supported.']);
        }

        $userIds = $this->db->table('dialog_user')
            ->where('dialog_id', $dialogId)
            ->pluck('user_id')
            ->map(static fn ($userId) => (int) $userId)
            ->all();

        $userIds = array_values(array_unique($userIds));

        if (count($userIds) !== 2) {
            throw new ValidationException([$errorKey => 'Only two-user private message dialogs are supported.']);
        }

        return $userIds;
    }

    public function assertContainsUsers(int $dialogId, int $firstUserId, int $secondUserId, string $errorKey = 'thread'): void
    {
        $participants = $this->resolve($dialogId, $errorKey);

        if (
            ! in_array($firstUserId, $participants, true)
            || ! in_array($secondUserId, $participants, true)
        ) {
            throw new ValidationException([$errorKey => 'Both users must belong to the same direct private message dialog.']);
        }
    }
}
