<?php

declare(strict_types=1);

// Conexion PDO como funcion global, no como clase: este starter no tiene
// composer.json ni autoload, asi que cada endpoint la trae con un require_once
// (via helpers.php). Mismo comportamiento que Database::connection() en los
// starters con router (singleton por request, getenv() nunca $_ENV porque
// variables_order no siempre incluye "E", ERRMODE_EXCEPTION, FETCH_ASSOC,
// EMULATE_PREPARES en false) — solo que aca es una funcion suelta, no un metodo
// estatico de una clase con namespace.
function db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $host = getenv('DB_HOST') ?: 'mysql';
        $port = getenv('DB_PORT') ?: '3306';
        $database = getenv('DB_DATABASE') ?: 'app';
        $username = getenv('DB_USERNAME') ?: 'app';
        $password = getenv('DB_PASSWORD') ?: 'secret';

        $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";

        $pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }

    return $pdo;
}
