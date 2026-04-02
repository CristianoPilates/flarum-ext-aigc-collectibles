<?php

namespace Donk\AigcCollectibles\StateMachine;

use Donk\AigcCollectibles\Model\BlindBox;
use Donk\AigcCollectibles\Model\BarterProposal;
use Donk\AigcCollectibles\Model\Collectible;
use Donk\AigcCollectibles\Model\Trade;

class StateMachineConfig
{
    /**
     * @return array<string, mixed>
     */
    public static function collectible(): array
    {
        return [
            'class' => Collectible::class,
            'graph' => 'collectible',
            'property_path' => 'status',
            'states' => [
                Collectible::STATUS_DRAFT,
                Collectible::STATUS_GENERATING,
                Collectible::STATUS_COMPLETED,
                Collectible::STATUS_FAILED,
                Collectible::STATUS_BURNED,
            ],
            'transitions' => [
                'start_generating' => [
                    'from' => [Collectible::STATUS_DRAFT],
                    'to' => Collectible::STATUS_GENERATING,
                ],
                'complete' => [
                    'from' => [Collectible::STATUS_GENERATING],
                    'to' => Collectible::STATUS_COMPLETED,
                ],
                'fail' => [
                    'from' => [Collectible::STATUS_GENERATING],
                    'to' => Collectible::STATUS_FAILED,
                ],
                'retry' => [
                    'from' => [Collectible::STATUS_FAILED],
                    'to' => Collectible::STATUS_DRAFT,
                ],
                'burn' => [
                    'from' => [Collectible::STATUS_COMPLETED],
                    'to' => Collectible::STATUS_BURNED,
                ],
            ],
            'callbacks' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function blindBox(): array
    {
        return [
            'class' => BlindBox::class,
            'graph' => 'blindBox',
            'property_path' => 'status',
            'states' => [
                BlindBox::STATUS_UNAPPRAISED,
                BlindBox::STATUS_APPRAISED,
                BlindBox::STATUS_OPENED,
            ],
            'transitions' => [
                'appraise' => [
                    'from' => [BlindBox::STATUS_UNAPPRAISED],
                    'to' => BlindBox::STATUS_APPRAISED,
                ],
                'open' => [
                    'from' => [BlindBox::STATUS_APPRAISED],
                    'to' => BlindBox::STATUS_OPENED,
                ],
            ],
            'callbacks' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function trade(): array
    {
        return [
            'class' => Trade::class,
            'graph' => 'trade',
            'property_path' => 'status',
            'states' => [
                Trade::STATUS_PENDING,
                Trade::STATUS_ACCEPTED,
                Trade::STATUS_SETTLING,
                Trade::STATUS_COMPLETED,
                Trade::STATUS_REJECTED,
                Trade::STATUS_CANCELLED,
                Trade::STATUS_FAILED,
            ],
            'transitions' => [
                'accept' => [
                    'from' => [Trade::STATUS_PENDING],
                    'to' => Trade::STATUS_ACCEPTED,
                ],
                'settle' => [
                    'from' => [Trade::STATUS_ACCEPTED],
                    'to' => Trade::STATUS_SETTLING,
                ],
                'complete' => [
                    'from' => [Trade::STATUS_SETTLING],
                    'to' => Trade::STATUS_COMPLETED,
                ],
                'reject' => [
                    'from' => [Trade::STATUS_PENDING],
                    'to' => Trade::STATUS_REJECTED,
                ],
                'cancel' => [
                    'from' => [Trade::STATUS_PENDING],
                    'to' => Trade::STATUS_CANCELLED,
                ],
                'fail' => [
                    'from' => [Trade::STATUS_SETTLING],
                    'to' => Trade::STATUS_FAILED,
                ],
            ],
            'callbacks' => [
                'after' => [
                    'mark_terminal_completion' => [
                        'to' => [
                            Trade::STATUS_COMPLETED,
                            Trade::STATUS_REJECTED,
                            Trade::STATUS_CANCELLED,
                            Trade::STATUS_FAILED,
                        ],
                        'do' => ['object', 'markCompletedAt'],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function barterProposal(): array
    {
        return [
            'class' => BarterProposal::class,
            'graph' => 'barterProposal',
            'property_path' => 'status',
            'states' => [
                BarterProposal::STATUS_PROPOSED,
                BarterProposal::STATUS_ACCEPTED,
                BarterProposal::STATUS_SETTLING,
                BarterProposal::STATUS_COMPLETED,
                BarterProposal::STATUS_REJECTED,
                BarterProposal::STATUS_CANCELLED,
                BarterProposal::STATUS_SUPERSEDED,
                BarterProposal::STATUS_FAILED,
            ],
            'transitions' => [
                'accept' => [
                    'from' => [BarterProposal::STATUS_PROPOSED],
                    'to' => BarterProposal::STATUS_ACCEPTED,
                ],
                'settle' => [
                    'from' => [BarterProposal::STATUS_ACCEPTED],
                    'to' => BarterProposal::STATUS_SETTLING,
                ],
                'complete' => [
                    'from' => [BarterProposal::STATUS_SETTLING],
                    'to' => BarterProposal::STATUS_COMPLETED,
                ],
                'reject' => [
                    'from' => [BarterProposal::STATUS_PROPOSED],
                    'to' => BarterProposal::STATUS_REJECTED,
                ],
                'cancel' => [
                    'from' => [BarterProposal::STATUS_PROPOSED],
                    'to' => BarterProposal::STATUS_CANCELLED,
                ],
                'supersede' => [
                    'from' => [BarterProposal::STATUS_PROPOSED],
                    'to' => BarterProposal::STATUS_SUPERSEDED,
                ],
                'fail' => [
                    'from' => [BarterProposal::STATUS_SETTLING],
                    'to' => BarterProposal::STATUS_FAILED,
                ],
            ],
            'callbacks' => [
                'after' => [
                    'mark_terminal_completion' => [
                        'to' => [
                            BarterProposal::STATUS_COMPLETED,
                            BarterProposal::STATUS_REJECTED,
                            BarterProposal::STATUS_CANCELLED,
                            BarterProposal::STATUS_SUPERSEDED,
                            BarterProposal::STATUS_FAILED,
                        ],
                        'do' => ['object', 'markCompletedAt'],
                    ],
                ],
            ],
        ];
    }
}
