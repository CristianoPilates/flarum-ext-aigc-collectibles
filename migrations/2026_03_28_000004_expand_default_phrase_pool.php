<?php

use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
        $db = $schema->getConnection();
        $now = date('Y-m-d H:i:s');

        $phrases = [
            ['category' => 'subject', 'phrase' => 'phoenix', 'cost' => 5, 'is_active' => true],
            ['category' => 'subject', 'phrase' => 'moon rabbit', 'cost' => 4, 'is_active' => true],
            ['category' => 'subject', 'phrase' => 'sea serpent', 'cost' => 4, 'is_active' => true],
            ['category' => 'subject', 'phrase' => 'storm griffin', 'cost' => 5, 'is_active' => true],
            ['category' => 'subject', 'phrase' => 'clockwork fox', 'cost' => 3, 'is_active' => true],
            ['category' => 'subject', 'phrase' => 'crystal golem', 'cost' => 4, 'is_active' => true],
            ['category' => 'subject', 'phrase' => 'celestial whale', 'cost' => 5, 'is_active' => true],
            ['category' => 'subject', 'phrase' => 'mushroom village', 'cost' => 2, 'is_active' => true],
            ['category' => 'subject', 'phrase' => 'sky pirate airship', 'cost' => 4, 'is_active' => true],
            ['category' => 'subject', 'phrase' => 'desert caravan', 'cost' => 2, 'is_active' => true],
            ['category' => 'subject', 'phrase' => 'dreaming astronaut', 'cost' => 3, 'is_active' => true],
            ['category' => 'subject', 'phrase' => 'tea spirit', 'cost' => 2, 'is_active' => true],

            ['category' => 'style', 'phrase' => 'ink wash painting', 'cost' => 3, 'is_active' => true],
            ['category' => 'style', 'phrase' => 'woodblock print', 'cost' => 3, 'is_active' => true],
            ['category' => 'style', 'phrase' => 'stained glass illustration', 'cost' => 4, 'is_active' => true],
            ['category' => 'style', 'phrase' => 'retro anime poster', 'cost' => 4, 'is_active' => true],
            ['category' => 'style', 'phrase' => 'isometric diorama', 'cost' => 3, 'is_active' => true],
            ['category' => 'style', 'phrase' => 'paper cut collage', 'cost' => 2, 'is_active' => true],
            ['category' => 'style', 'phrase' => 'art nouveau poster', 'cost' => 4, 'is_active' => true],
            ['category' => 'style', 'phrase' => 'gouache storybook', 'cost' => 3, 'is_active' => true],
            ['category' => 'style', 'phrase' => 'low poly render', 'cost' => 2, 'is_active' => true],
            ['category' => 'style', 'phrase' => 'screenprint graphic', 'cost' => 3, 'is_active' => true],
            ['category' => 'style', 'phrase' => 'surreal photomontage', 'cost' => 4, 'is_active' => true],
            ['category' => 'style', 'phrase' => 'miniature tilt-shift photography', 'cost' => 5, 'is_active' => true],

            ['category' => 'mood', 'phrase' => 'sunlit serenity', 'cost' => 1, 'is_active' => true],
            ['category' => 'mood', 'phrase' => 'storm-charged tension', 'cost' => 2, 'is_active' => true],
            ['category' => 'mood', 'phrase' => 'festival euphoria', 'cost' => 2, 'is_active' => true],
            ['category' => 'mood', 'phrase' => 'melancholic twilight', 'cost' => 2, 'is_active' => true],
            ['category' => 'mood', 'phrase' => 'arcane mystery', 'cost' => 2, 'is_active' => true],
            ['category' => 'mood', 'phrase' => 'playful chaos', 'cost' => 1, 'is_active' => true],
            ['category' => 'mood', 'phrase' => 'sacred stillness', 'cost' => 2, 'is_active' => true],
            ['category' => 'mood', 'phrase' => 'cosmic awe', 'cost' => 3, 'is_active' => true],
            ['category' => 'mood', 'phrase' => 'cozy wonder', 'cost' => 1, 'is_active' => true],
            ['category' => 'mood', 'phrase' => 'rebellious neon pulse', 'cost' => 2, 'is_active' => true],

            ['category' => 'theme', 'phrase' => 'forgotten temple rite', 'cost' => 2, 'is_active' => true],
            ['category' => 'theme', 'phrase' => 'sky harbor expedition', 'cost' => 2, 'is_active' => true],
            ['category' => 'theme', 'phrase' => 'underwater coronation', 'cost' => 3, 'is_active' => true],
            ['category' => 'theme', 'phrase' => 'midnight bazaar', 'cost' => 2, 'is_active' => true],
            ['category' => 'theme', 'phrase' => 'starforge pilgrimage', 'cost' => 3, 'is_active' => true],
            ['category' => 'theme', 'phrase' => 'festival of masks', 'cost' => 2, 'is_active' => true],
            ['category' => 'theme', 'phrase' => 'ruined moon colony', 'cost' => 3, 'is_active' => true],
            ['category' => 'theme', 'phrase' => 'clockwork garden', 'cost' => 2, 'is_active' => true],
            ['category' => 'theme', 'phrase' => 'volcanic throne room', 'cost' => 3, 'is_active' => true],
            ['category' => 'theme', 'phrase' => 'floating tea ceremony', 'cost' => 1, 'is_active' => true],
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
            ['blindbox_type' => 'checkin_reward', 'pool_category' => 'theme', 'required' => false],
            ['blindbox_type' => 'trade_reward', 'pool_category' => 'theme', 'required' => false],
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
            ->whereIn('blindbox_type', ['checkin_reward', 'trade_reward'])
            ->where('pool_category', 'theme')
            ->delete();

        $db->table('phrase_pools')
            ->where(function ($query) {
                $query
                    ->where(function ($subQuery) {
                        $subQuery->where('category', 'subject')->whereIn('phrase', [
                            'phoenix',
                            'moon rabbit',
                            'sea serpent',
                            'storm griffin',
                            'clockwork fox',
                            'crystal golem',
                            'celestial whale',
                            'mushroom village',
                            'sky pirate airship',
                            'desert caravan',
                            'dreaming astronaut',
                            'tea spirit',
                        ]);
                    })
                    ->orWhere(function ($subQuery) {
                        $subQuery->where('category', 'style')->whereIn('phrase', [
                            'ink wash painting',
                            'woodblock print',
                            'stained glass illustration',
                            'retro anime poster',
                            'isometric diorama',
                            'paper cut collage',
                            'art nouveau poster',
                            'gouache storybook',
                            'low poly render',
                            'screenprint graphic',
                            'surreal photomontage',
                            'miniature tilt-shift photography',
                        ]);
                    })
                    ->orWhere(function ($subQuery) {
                        $subQuery->where('category', 'mood')->whereIn('phrase', [
                            'sunlit serenity',
                            'storm-charged tension',
                            'festival euphoria',
                            'melancholic twilight',
                            'arcane mystery',
                            'playful chaos',
                            'sacred stillness',
                            'cosmic awe',
                            'cozy wonder',
                            'rebellious neon pulse',
                        ]);
                    })
                    ->orWhere(function ($subQuery) {
                        $subQuery->where('category', 'theme')->whereIn('phrase', [
                            'forgotten temple rite',
                            'sky harbor expedition',
                            'underwater coronation',
                            'midnight bazaar',
                            'starforge pilgrimage',
                            'festival of masks',
                            'ruined moon colony',
                            'clockwork garden',
                            'volcanic throne room',
                            'floating tea ceremony',
                        ]);
                    });
            })
            ->delete();
    },
];
