<?php

use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
        $connection = $schema->getConnection();
        $column = $connection->selectOne("SHOW COLUMNS FROM `trades` LIKE 'status'");

        if (!$column || !isset($column->Type) || !str_starts_with($column->Type, 'enum(')) {
            return;
        }

        $connection->statement(
            "ALTER TABLE `trades` MODIFY `status` ENUM('pending', 'accepted', 'settling', 'completed', 'rejected', 'cancelled', 'failed') NOT NULL DEFAULT 'pending'"
        );
    },
    'down' => function (Builder $schema) {
        $connection = $schema->getConnection();
        $column = $connection->selectOne("SHOW COLUMNS FROM `trades` LIKE 'status'");

        if (!$column || !isset($column->Type) || !str_starts_with($column->Type, 'enum(')) {
            return;
        }

        $connection->statement(
            "ALTER TABLE `trades` MODIFY `status` ENUM('pending', 'accepted', 'rejected', 'cancelled') NOT NULL DEFAULT 'pending'"
        );
    },
];
