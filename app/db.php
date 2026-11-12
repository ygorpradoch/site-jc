<?php
defined('JC') || exit;

/** Conexão PDO única. Sempre usar prepared statements (db_q). */
function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        cfg('db.host', 'localhost'),
        (int) cfg('db.port', 3306),
        cfg('db.name')
    );
    $pdo = new PDO($dsn, (string) cfg('db.user'), (string) cfg('db.pass'), [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $pdo->exec("SET time_zone = '-03:00'");
    return $pdo;
}

/** Executa uma consulta preparada e devolve o statement. */
function db_q(string $sql, array $params = []): PDOStatement
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st;
}

function db_all(string $sql, array $params = []): array
{
    return db_q($sql, $params)->fetchAll();
}

function db_one(string $sql, array $params = []): ?array
{
    $r = db_q($sql, $params)->fetch();
    return $r === false ? null : $r;
}

function db_val(string $sql, array $params = [])
{
    $r = db_q($sql, $params)->fetchColumn();
    return $r === false ? null : $r;
}
