<?php

use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
        $db = $schema->getConnection();
        $now = date('Y-m-d H:i:s');

        $sourceRules = $db->table('blindbox_draw_rules')
            ->where('blindbox_type', 'checkin_reward')
            ->orderBy('id')
            ->get(['pool_category', 'required']);

        if ($sourceRules->isEmpty()) {
            $sourceRules = collect([
                (object) ['pool_category' => 'subject', 'required' => true],
                (object) ['pool_category' => 'style', 'required' => true],
                (object) ['pool_category' => 'mood', 'required' => false],
            ]);
        }

        foreach ($sourceRules as $rule) {
            $exists = $db->table('blindbox_draw_rules')
                ->where('blindbox_type', 'trade_reward')
                ->where('pool_category', $rule->pool_category)
                ->exists();

            if (! $exists) {
                $db->table('blindbox_draw_rules')->insert([
                    'blindbox_type' => 'trade_reward',
                    'pool_category' => $rule->pool_category,
                    'required' => (bool) $rule->required,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    },
    'down' => function (Builder $schema) {
        $schema->getConnection()
            ->table('blindbox_draw_rules')
            ->where('blindbox_type', 'trade_reward')
            ->whereIn('pool_category', ['subject', 'style', 'mood'])
            ->delete();
    },
];
