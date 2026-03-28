<?php

use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
        $db = $schema->getConnection();
        $now = date('Y-m-d H:i:s');

        $phrases = [
            ['category' => 'subject', 'phrase' => 'library automaton', 'cost' => 3, 'is_active' => true],
            ['category' => 'subject', 'phrase' => 'reef cathedral', 'cost' => 4, 'is_active' => true],
            ['category' => 'subject', 'phrase' => 'thunder stag', 'cost' => 4, 'is_active' => true],
            ['category' => 'subject', 'phrase' => 'candlelit alchemist', 'cost' => 3, 'is_active' => true],
            ['category' => 'subject', 'phrase' => 'glacier oracle', 'cost' => 4, 'is_active' => true],
            ['category' => 'subject', 'phrase' => 'subway koi spirit', 'cost' => 3, 'is_active' => true],
            ['category' => 'subject', 'phrase' => 'volcanic drummer', 'cost' => 3, 'is_active' => true],
            ['category' => 'subject', 'phrase' => 'mirror witch', 'cost' => 4, 'is_active' => true],
            ['category' => 'subject', 'phrase' => 'mecha gardener', 'cost' => 3, 'is_active' => true],
            ['category' => 'subject', 'phrase' => 'cloud palace courier', 'cost' => 2, 'is_active' => true],
            ['category' => 'subject', 'phrase' => 'lantern fisherman', 'cost' => 2, 'is_active' => true],
            ['category' => 'subject', 'phrase' => 'jungle observatory', 'cost' => 3, 'is_active' => true],

            ['category' => 'style', 'phrase' => 'baroque etching', 'cost' => 4, 'is_active' => true],
            ['category' => 'style', 'phrase' => 'pastel chalk mural', 'cost' => 2, 'is_active' => true],
            ['category' => 'style', 'phrase' => 'bioluminescent concept art', 'cost' => 4, 'is_active' => true],
            ['category' => 'style', 'phrase' => 'noir comic panel', 'cost' => 3, 'is_active' => true],
            ['category' => 'style', 'phrase' => 'ceramic glaze illustration', 'cost' => 3, 'is_active' => true],
            ['category' => 'style', 'phrase' => 'patchwork textile collage', 'cost' => 2, 'is_active' => true],
            ['category' => 'style', 'phrase' => 'futurist magazine cover', 'cost' => 4, 'is_active' => true],
            ['category' => 'style', 'phrase' => 'charcoal story sketch', 'cost' => 2, 'is_active' => true],
            ['category' => 'style', 'phrase' => 'neon shrine render', 'cost' => 4, 'is_active' => true],
            ['category' => 'style', 'phrase' => 'stop-motion miniature set', 'cost' => 5, 'is_active' => true],

            ['category' => 'mood', 'phrase' => 'wind-up curiosity', 'cost' => 1, 'is_active' => true],
            ['category' => 'mood', 'phrase' => 'solemn grandeur', 'cost' => 2, 'is_active' => true],
            ['category' => 'mood', 'phrase' => 'after-rain clarity', 'cost' => 1, 'is_active' => true],
            ['category' => 'mood', 'phrase' => 'embers of defiance', 'cost' => 2, 'is_active' => true],
            ['category' => 'mood', 'phrase' => 'dreamlike mischief', 'cost' => 1, 'is_active' => true],
            ['category' => 'mood', 'phrase' => 'cathedral hush', 'cost' => 2, 'is_active' => true],
            ['category' => 'mood', 'phrase' => 'tidal melancholy', 'cost' => 2, 'is_active' => true],
            ['category' => 'mood', 'phrase' => 'electric triumph', 'cost' => 2, 'is_active' => true],

            ['category' => 'theme', 'phrase' => 'market before sunrise', 'cost' => 1, 'is_active' => true],
            ['category' => 'theme', 'phrase' => 'ceremony under red snow', 'cost' => 3, 'is_active' => true],
            ['category' => 'theme', 'phrase' => 'archive of lost constellations', 'cost' => 3, 'is_active' => true],
            ['category' => 'theme', 'phrase' => 'railway through the clouds', 'cost' => 2, 'is_active' => true],
            ['category' => 'theme', 'phrase' => 'orchard on a floating ruin', 'cost' => 2, 'is_active' => true],
            ['category' => 'theme', 'phrase' => 'observatory at the edge of the sea', 'cost' => 3, 'is_active' => true],
            ['category' => 'theme', 'phrase' => 'last night of the lantern fair', 'cost' => 2, 'is_active' => true],
            ['category' => 'theme', 'phrase' => 'winter expedition to the glass desert', 'cost' => 3, 'is_active' => true],
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
    },

    'down' => function (Builder $schema) {
        $db = $schema->getConnection();

        $db->table('phrase_pools')
            ->where(function ($query) {
                $query
                    ->where(function ($subQuery) {
                        $subQuery->where('category', 'subject')->whereIn('phrase', [
                            'library automaton',
                            'reef cathedral',
                            'thunder stag',
                            'candlelit alchemist',
                            'glacier oracle',
                            'subway koi spirit',
                            'volcanic drummer',
                            'mirror witch',
                            'mecha gardener',
                            'cloud palace courier',
                            'lantern fisherman',
                            'jungle observatory',
                        ]);
                    })
                    ->orWhere(function ($subQuery) {
                        $subQuery->where('category', 'style')->whereIn('phrase', [
                            'baroque etching',
                            'pastel chalk mural',
                            'bioluminescent concept art',
                            'noir comic panel',
                            'ceramic glaze illustration',
                            'patchwork textile collage',
                            'futurist magazine cover',
                            'charcoal story sketch',
                            'neon shrine render',
                            'stop-motion miniature set',
                        ]);
                    })
                    ->orWhere(function ($subQuery) {
                        $subQuery->where('category', 'mood')->whereIn('phrase', [
                            'wind-up curiosity',
                            'solemn grandeur',
                            'after-rain clarity',
                            'embers of defiance',
                            'dreamlike mischief',
                            'cathedral hush',
                            'tidal melancholy',
                            'electric triumph',
                        ]);
                    })
                    ->orWhere(function ($subQuery) {
                        $subQuery->where('category', 'theme')->whereIn('phrase', [
                            'market before sunrise',
                            'ceremony under red snow',
                            'archive of lost constellations',
                            'railway through the clouds',
                            'orchard on a floating ruin',
                            'observatory at the edge of the sea',
                            'last night of the lantern fair',
                            'winter expedition to the glass desert',
                        ]);
                    });
            })
            ->delete();
    },
];
