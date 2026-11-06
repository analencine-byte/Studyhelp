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
        try { $pdo->exec("SET time_zone = '+00:00'"); } catch (Throwable $e) {} // datas sempre em UTC (o navegador converte)
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

function avatarUrl(array $u): ?string {
    return !empty($u['avatar']) ? 'api/usuarios.php?acao=avatar&id=' . (int)$u['id'] . '&v=' . substr(md5($u['avatar']), 0, 8) : null;
}

function usuarioPublico(array $u): array {
    $cfg = [];
    if (!empty($u['config'])) { $j = json_decode($u['config'], true); if (is_array($j)) $cfg = $j; }
    return [
        'id' => (int)$u['id'], 'nome' => $u['nome'], 'email' => $u['email'],
        'papel' => $u['papel'], 'status' => $u['status'],
        'avatar' => avatarUrl($u), 'criadoEm' => $u['criado_em'],
        'bio' => $u['bio'] ?? null, 'disponivel' => (bool)($u['disponivel'] ?? 1), 'config' => (object)$cfg,
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

function configuracao(string $chave, string $padrao): string {
    $st = db()->prepare('SELECT valor FROM configuracoes WHERE chave = ?');
    $st->execute([$chave]);
    $v = $st->fetchColumn();
    return $v === false ? $padrao : (string)$v;
}

// Salva um arquivo enviado (campo $campo) em api/uploads/<pasta>/ com nome aleatório.
// Retorna [nomeOriginal, caminhoRelativo, tamanho] ou null se nenhum arquivo foi enviado.
function salvarUpload(string $campo, string $pasta, array $extPermitidas, int $maxBytes = 20_000_000): ?array {
    if (empty($_FILES[$campo]) || $_FILES[$campo]['error'] === UPLOAD_ERR_NO_FILE) return null;
    $f = $_FILES[$campo];
    if ($f['error'] === UPLOAD_ERR_INI_SIZE || $f['error'] === UPLOAD_ERR_FORM_SIZE) erro('Arquivo grande demais para o servidor (aumente upload_max_filesize no php.ini).', 413);
    if ($f['error'] !== UPLOAD_ERR_OK) erro('Falha ao receber o arquivo.');
    if ($f['size'] > $maxBytes) erro('Arquivo grande demais (limite de ' . intdiv($maxBytes, 1_000_000) . ' MB).', 413);
    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $extPermitidas, true)) erro('Tipo de arquivo não permitido (' . implode(', ', $extPermitidas) . ').');
    $dir = __DIR__ . '/uploads/' . $pasta;
    if (!is_dir($dir) && !mkdir($dir, 0775, true)) erro('Não foi possível criar a pasta de uploads.', 500);
    $nome = bin2hex(random_bytes(16)) . '.' . $ext;
    if (!move_uploaded_file($f['tmp_name'], "$dir/$nome")) erro('Não foi possível salvar o arquivo.', 500);
    return [basename($f['name']), "$pasta/$nome", (int)$f['size']];
}

// Envia um arquivo de api/uploads/ para o navegador (só depois de a rota ter checado a permissão).
function enviarUpload(string $caminho, string $nomeDownload, bool $inline = false): void {
    $real = realpath(__DIR__ . '/uploads/' . $caminho);
    $base = realpath(__DIR__ . '/uploads');
    if (!$real || !$base || strpos($real, $base) !== 0 || !is_file($real)) erro('Arquivo não encontrado.', 404);
    $mimes = ['pdf' => 'application/pdf', 'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif', 'mp4' => 'video/mp4', 'webm' => 'video/webm', 'txt' => 'text/plain'];
    $ext = strtolower(pathinfo($real, PATHINFO_EXTENSION));
    header_remove('Content-Type');
    header('Content-Type: ' . ($mimes[$ext] ?? 'application/octet-stream'));
    header('Content-Length: ' . filesize($real));
    header('X-Content-Type-Options: nosniff');
    header('Content-Disposition: ' . ($inline && isset($mimes[$ext]) && $ext !== 'svg' ? 'inline' : 'attachment') . '; filename="' . rawurlencode($nomeDownload) . '"');
    readfile($real);
    exit;
}

// Foto de perfil: aceita só data URI de imagem (png/jpeg/gif/webp) de até ~2 MB.
function avatarValido($avatar): bool {
    return is_string($avatar) && strlen($avatar) <= 2_000_000 && preg_match('#^data:image/(png|jpeg|gif|webp);base64,[A-Za-z0-9+/=]+$#', $avatar) === 1;
}
