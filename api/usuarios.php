<?php
// GET usuarios.php            (admin) lista usuários
// POST usuarios.php?acao=status&id=N   body {status}   (admin) aprova/recusa/ativa
// POST usuarios.php?acao=perfil  body {nome,avatar?,senhaAtual?,novaSenha?}  (próprio usuário)
// POST usuarios.php?acao=excluir&id=N  (admin)
require __DIR__ . '/bootstrap.php';
$acao = $_GET['acao'] ?? 'listar';

if ($acao === 'listar') {
    exigir(['Administrador']);
    $r = db()->query('SELECT id,nome,email,papel,status,avatar,criado_em FROM usuarios ORDER BY criado_em DESC, id DESC')->fetchAll();
    responder(['usuarios' => array_map('usuarioPublico', $r)]);
}
if ($acao === 'status') {
    exigir(['Administrador']);
    $status = entrada()['status'] ?? '';
    if (!in_array($status, ['Aprovado', 'Pendente', 'Ativo', 'Recusado'], true)) erro('Status inválido.');
    db()->prepare("UPDATE usuarios SET status = ? WHERE id = ? AND papel <> 'Administrador'")->execute([$status, (int)($_GET['id'] ?? 0)]);
    responder(['ok' => true]);
}
if ($acao === 'excluir') {
    $adm = exigir(['Administrador']);
    $id = (int)($_GET['id'] ?? 0);
    if ($id === (int)$adm['id']) erro('Você não pode excluir a si mesmo.');
    db()->prepare('DELETE FROM usuarios WHERE id = ?')->execute([$id]);
    responder(['ok' => true]);
}
if ($acao === 'perfil') {
    $u = exigir();
    $d = entrada();
    $nome = trim((string)($d['nome'] ?? $u['nome']));
    if ($nome === '') erro('Nome inválido.');
    $avatar = array_key_exists('avatar', $d) ? $d['avatar'] : $u['avatar'];
    if ($avatar !== null && (!is_string($avatar) || strlen($avatar) > 2_000_000)) erro('Foto inválida ou grande demais.');
    $hash = $u['senha_hash'];
    if (!empty($d['novaSenha'])) {
        if (!password_verify((string)($d['senhaAtual'] ?? ''), $hash)) erro('Senha atual incorreta.', 403);
        if (strlen($d['novaSenha']) < 6) erro('A nova senha precisa ter no mínimo 6 caracteres.');
        $hash = password_hash($d['novaSenha'], PASSWORD_DEFAULT);
    }
    db()->prepare('UPDATE usuarios SET nome=?, avatar=?, senha_hash=? WHERE id=?')->execute([$nome, $avatar, $hash, $u['id']]);
    responder(['usuario' => usuarioPublico(usuarioAtual())]);
}
erro('Ação desconhecida.', 404);
