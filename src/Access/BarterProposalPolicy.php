<?php

namespace Donk\AigcCollectibles\Access;

use Donk\AigcCollectibles\Model\BarterProposal;
use Flarum\User\Access\AbstractPolicy;
use Flarum\User\User;

class BarterProposalPolicy extends AbstractPolicy
{
    public function view(User $actor, BarterProposal $proposal): ?string
    {
        if ($proposal->involvesUser($actor->id)) {
            return $this->allow();
        }

        return $this->deny();
    }

    public function accept(User $actor, BarterProposal $proposal): ?string
    {
        if ($actor->id === $proposal->counterparty_user_id && $proposal->status === BarterProposal::STATUS_PROPOSED) {
            return $this->allow();
        }

        return $this->deny();
    }

    public function reject(User $actor, BarterProposal $proposal): ?string
    {
        if ($actor->id === $proposal->counterparty_user_id && $proposal->status === BarterProposal::STATUS_PROPOSED) {
            return $this->allow();
        }

        return $this->deny();
    }

    public function cancel(User $actor, BarterProposal $proposal): ?string
    {
        if ($actor->id === $proposal->proposer_user_id && $proposal->status === BarterProposal::STATUS_PROPOSED) {
            return $this->allow();
        }

        return $this->deny();
    }
}
