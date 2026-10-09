<?php
// Funções comuns a todos os endpoints: conexão, sessão, JSON e checagem de papel.
declare(strict_types=1);

session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
session_start();
header('Content-Type: application/json; charset=utf-8');

function db(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    $c = require __DIR__ . '/config.php';
    if ($c['driver'] === 'sqlite') {
        $pdo = new PDO('sqlite:' . $c['sqlite']);
        $pdo->exec('PRAGMA foreign_keys = ON');
    } else {
        $dsn = "mysql:host={$c['host']};port={$c['porta']};dbname={$c['banco']};charset=utf8mb4";
        $pdo = new PDO($dsn, $c['user'], $c['senha']);
    }
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    return $pdo;
}

function responder($dados, int $status = 200): void {
    http_response_code($status);
    echo json_encode($dados, JSON_UNESCAPED_UNICODE);
    exit;
}
function erro(string $msg, int $status = 400): void { responder(['erro' => $msg], $status); }

function entrada(): array {
    $json = json_decode(file_get_contents('php://input') ?: '', true);
    return is_array($json) ? $json : $_POST;
}

function usuarioPublico(array $u): array {
    return [
        'id' => (int)$u['id'], 'nome' => $u['nome'], 'email' => $u['email'],
        'papel' => $u['papel'], 'status' => $u['status'],
        'avatar' => $u['avatar'] ?? null, 'criadoEm' => $u['criado_em'],
    ];
}

// Usuário da sessão, sempre relido do banco (papel/status podem mudar).
function usuarioAtual(): ?array {
    if (empty($_SESSION['uid'])) return null;
    $st = db()->prepare('SELECT * FROM usuarios WHERE id = ?');
    $st->execute([$_SESSION['uid']]);
    $u = $st->fetch();
    if (!$u) { unset($_SESSION['uid']); return null; }
    return $u;
}

// Exige login; opcionalmente restringe a certos papéis.
function exigir(array $papeis = []): array {
    $u = usuarioAtual();
    if (!$u) erro('Faça login para continuar.', 401);
    if ($papeis && !in_array($u['papel'], $papeis, true)) erro('Sem permissão.', 403);
    if ($u['papel'] === 'Tutor' && $u['status'] === 'Recusado') erro('Cadastro recusado.', 403);
    return $u;
}

function registrarAtividade(string $texto): void {
    db()->prepare('INSERT INTO atividades (texto) VALUES (?)')->execute([$texto]);
}
