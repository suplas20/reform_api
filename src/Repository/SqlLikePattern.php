<?php

declare(strict_types=1);

namespace App\Repository;

final class SqlLikePattern
{
    public static function contains(string $value): string
    {
        return '%' . str_replace(
            ['!', '%', '_'],
            ['!!', '!%', '!_'],
            $value,
        ) . '%';
    }
}
