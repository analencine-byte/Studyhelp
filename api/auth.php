<?php
// POST auth.php?acao=cadastro|login|logout   GET auth.php?acao=eu
require __DIR__ . '/bootstrap.php';
$acao = $_GET['acao'] ?? '';
$d = entrada();

switch ($acao) {
case 'cadastro':
    $nome = trim((string)($d['nome'] ?? ''));
    $email = strtolower(trim((string)($d['email'] ?? '')));
    $senha = (string)($d['senha'] ?? '');
    $papel = $d['papel'] ?? 'Aluno';
    if ($nome === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) erro('Nome ou email inválido.');
    if (strlen($senha) < 6) erro('A senha precisa ter no mínimo 6 caracteres.');
    if (!in_array($papel, ['Aluno', 'Tutor'], true)) erro('Papel inválido.'); // admin nunca se cadastra pelo site
    $avatar = $d['avatar'] ?? null;
    if ($avatar !== null && !avatarValido($avatar)) erro('Foto inválida ou grande demais.');
    $st = db()->prepare('SELECT 1 FROM usuarios WHERE email = ?'); $st->execute([$email]);
    if ($st->fetch()) erro('Já existe uma conta com esse email.', 409);
    $status = ($papel === 'Tutor' && configuracao('aprovar_tutores_manualmente', '1') === '1') ? 'Pendente' : 'Aprovado';
    db()->prepare('INSERT INTO usuarios (nome,email,senha_hash,papel,status,avatar) VALUES (?,?,?,?,?,?)')
        ->execute([$nome, $email, password_hash($senha, PASSWORD_DEFAULT), $papel, $status, $avatar]);
    $id = (int)db()->lastInsertId();
    session_regenerate_id(true);
    $_SESSION['uid'] = $id;
    registrarAtividade($papel === 'Tutor' ? ($status === 'Pendente' ? "$nome solicitou cadastro como tutor(a)" : "$nome se cadastrou como tutor(a)") : "$nome se cadastrou como aluno(a)");
    responder(['usuario' => usuarioPublico(usuarioAtual())], 201);

case 'login':
    $email = strtolower(trim((string)($d['email'] ?? '')));
    $st = db()->prepare('SELECT * FROM usuarios WHERE email = ?'); $st->execute([$email]);
    $u = $st->fetch();
    // mesma mensagem para email inexistente e senha errada (não revela quais contas existem)
    if (!$u || !password_verify((string)($d['senha'] ?? ''), $u['senha_hash'])) erro('Email ou senha incorretos.', 401);
    session_regenerate_id(true);
    $_SESSION['uid'] = (int)$u['id'];
    responder(['usuario' => usuarioPublico($u)]);

case 'logout':
    $_SESSION = [];
    session_destroy();
    responder(['ok' => true]);

case 'eu':
    $u = usuarioAtual();
    responder(['usuario' => $u ? usuarioPublico($u) : null]); // null = visitante

default:
    erro('Ação desconhecida.', 404);
}
