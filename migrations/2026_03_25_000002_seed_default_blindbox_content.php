<?php

use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
        $db = $schema->getConnection();
        $now = date('Y-m-d H:i:s');

        $phrases = [
            ['category' => 'subject', 'phrase' => 'dragon', 'cost' => 5, 'is_active' => true],
            ['category' => 'subject', 'phrase' => 'forest', 'cost' => 3, 'is_active' => true],
            ['category' => 'style', 'phrase' => 'oil painting', 'cost' => 5, 'is_active' => true],
            ['category' => 'style', 'phrase' => 'pixel art', 'cost' => 3, 'is_active' => true],
            ['category' => 'mood', 'phrase' => 'ethereal glow', 'cost' => 3, 'is_active' => true],
            ['category' => 'mood', 'phrase' => 'dark', 'cost' => 1, 'is_active' => true],
        ];

        foreach ($phrases as $phrase) {
            $exists = $db->table('phrase_pools')
                ->where('category', $phrase['category'])
                ->where('phrase', $phrase['phrase'])
                ->exists();

            if (! $exists) {
                $db->table('phrase_pools')->insert([
                    ...$phrase,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        $rules = [
            ['blindbox_type' => 'checkin_reward', 'pool_category' => 'subject', 'required' => true],
            ['blindbox_type' => 'checkin_reward', 'pool_category' => 'style', 'required' => true],
            ['blindbox_type' => 'checkin_reward', 'pool_category' => 'mood', 'required' => false],
        ];

        foreach ($rules as $rule) {
            $exists = $db->table('blindbox_draw_rules')
                ->where('blindbox_type', $rule['blindbox_type'])
                ->where('pool_category', $rule['pool_category'])
                ->exists();

            if (! $exists) {
                $db->table('blindbox_draw_rules')->insert([
                    ...$rule,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    },
    'down' => function (Builder $schema) {
        $db = $schema->getConnection();

        $db->table('blindbox_draw_rules')
            ->where('blindbox_type', 'checkin_reward')
            ->whereIn('pool_category', ['subject', 'style', 'mood'])
            ->delete();

        $db->table('phrase_pools')
            ->where(function ($query) {
                $query
                    ->where(function ($subQuery) {
                        $subQuery->where('category', 'subject')->whereIn('phrase', ['dragon', 'forest']);
                    })
                    ->orWhere(function ($subQuery) {
                        $subQuery->where('category', 'style')->whereIn('phrase', ['oil painting', 'pixel art']);
                    })
                    ->orWhere(function ($subQuery) {
                        $subQuery->where('category', 'mood')->whereIn('phrase', ['ethereal glow', 'dark']);
                    });
            })
            ->delete();
    },
];
