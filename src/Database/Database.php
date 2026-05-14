<?php

declare(strict_types=1);

namespace Tegro\Purchase\Database;

/**
 * Thin factory around PDO with the safety knobs every PHP backend should
 * set but most forget: error-mode = exception, fetch-mode = assoc,
 * emulated prepares OFF (real server-side prepared statements).
 */
final class Database
{
    public static function connect(string $dsn, string $user, string $password): \PDO
    {
        return new \PDO(
            $dsn,
            $user,
            $password,
            [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES => false,
                \PDO::ATTR_STRINGIFY_FETCHES => false,
            ],
        );
    }
}
