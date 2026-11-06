<?php
// GET  usuarios.php                        (admin) lista usuários
// GET  usuarios.php?acao=tutores           (público) tutores aprovados, com matérias
// GET  usuarios.php?acao=avatar&id=N       (público) imagem de perfil
// GET  usuarios.php?acao=estatisticas      (logado) números do painel do aluno/tutor
// POST usuarios.php?acao=status&id=N       (admin) {status}
// POST usuarios.php?acao=excluir&id=N      (admin)
// POST usuarios.php?acao=perfil            (logado) {nome,email?,avatar?,bio?,disponivel?,config?,senhaAtual?,novaSenha?}
require __DIR__ . '/bootstrap.php';
$acao = $_GET['acao'] ?? 'listar';

if ($acao === 'avatar') {
    $st = db()->prepare('SELECT avatar FROM usuarios WHERE id = ?'); $st->execute([(int)($_GET['id'] ?? 0)]);
    $av = $st->fetchColumn();
    if (!$av || !preg_match('#^data:(image/[a-z]+);base64,(.+)$#', $av, $m)) { http_response_code(404); exit; }
    header_remove('Content-Type'); header('Content-Type: ' . $m[1]); header('Cache-Control: public, max-age=86400');
    echo base64_decode($m[2]); exit;
}

if ($acao === 'listar') {
    exigir(['Administrador']);
    $r = db()->query('SELECT id,nome,email,papel,status,avatar,criado_em FROM usuarios ORDER BY criado_em DESC, id DESC')->fetchAll();
    responder(['usuarios' => array_map('usuarioPublico', $r)]);
}

if ($acao === 'tutores') {
    $tutores = db()->query("SELECT id,nome,email,papel,status,avatar,bio,disponivel,criado_em FROM usuarios
        WHERE papel = 'Tutor' AND status IN ('Aprovado','Ativo') ORDER BY nome")->fetchAll();
    $mat = db()->query('SELECT id, nome, tutor_id FROM materias WHERE tutor_id IS NOT NULL ORDER BY nome')->fetchAll();
    $porTutor = [];
    foreach ($mat as $m) $porTutor[$m['tutor_id']][] = ['id' => (int)$m['id'], 'nome' => $m['nome']];
    $saida = [];
    foreach ($tutores as $t) {
        $p = usuarioPublico($t);
        unset($p['email'], $p['config']); // email/config não são públicos
        $p['materias'] = $porTutor[$t['id']] ?? [];
        $saida[] = $p;
    }
    responder(['tutores' => $saida]);
}

if ($acao === 'estatisticas') {
    $u = exigir();
    $q = function (string $sql, array $a = []) { $s = db()->prepare($sql); $s->execute($a); return (int)$s->fetchColumn(); };
    if ($u['papel'] === 'Aluno') {
        responder(['duvidas' => $q("SELECT COUNT(*) FROM tarefas WHERE aluno_id=? AND tipo='duvida'", [$u['id']]),
                   'materiaisAcessados' => $q('SELECT COUNT(*) FROM acessos_material WHERE aluno_id=?', [$u['id']]),
                   'cadernos' => $q('SELECT COUNT(*) FROM cadernos WHERE aluno_id=?', [$u['id']]),
                   'favoritas' => $q('SELECT COUNT(*) FROM favoritas WHERE aluno_id=?', [$u['id']])]);
    }
    if ($u['papel'] === 'Tutor') {
        responder(['materias' => $q('SELECT COUNT(*) FROM materias WHERE tutor_id=?', [$u['id']]),
                   'alunos' => $q('SELECT COUNT(DISTINCT i.aluno_id) FROM inscricoes i JOIN materias m ON m.id=i.materia_id WHERE m.tutor_id=?', [$u['id']]),
                   'materiais' => $q('SELECT COUNT(*) FROM materiais WHERE tutor_id=?', [$u['id']]),
                   'mensagens' => $q('SELECT COUNT(*) FROM mensagens WHERE para_id=?', [$u['id']])]);
    }
    responder([]);
}

if ($acao === 'status') {
    $adm = exigir(['Administrador']);
    $status = entrada()['status'] ?? '';
    if (!in_array($status, ['Aprovado', 'Pendente', 'Ativo', 'Recusado'], true)) erro('Status inválido.');
    $id = (int)($_GET['id'] ?? 0);
    $st = db()->prepare("SELECT nome,papel FROM usuarios WHERE id = ? AND papel <> 'Administrador'"); $st->execute([$id]);
    $alvo = $st->fetch();
    if (!$alvo) erro('Usuário não encontrado.', 404);
    db()->prepare('UPDATE usuarios SET status = ? WHERE id = ?')->execute([$status, $id]);
    registrarAtividade($status === 'Recusado' ? "Cadastro de {$alvo['nome']} foi recusado"
        : ($status === 'Aprovado' ? "{$alvo['nome']} foi aprovado(a) como " . strtolower($alvo['papel']) : "{$alvo['nome']} mudou para $status"));
    responder(['ok' => true]);
}

if ($acao === 'excluir') {
    $adm = exigir(['Administrador']);
    $id = (int)($_GET['id'] ?? 0);
    if ($id === (int)$adm['id']) erro('Você não pode excluir a si mesmo.');
    $st = db()->prepare("SELECT nome,papel FROM usuarios WHERE id = ?"); $st->execute([$id]);
    $alvo = $st->fetch();
    if (!$alvo) erro('Usuário não encontrado.', 404);
    if ($alvo['papel'] === 'Administrador') erro('Não é possível excluir um administrador.', 403);
    db()->prepare('DELETE FROM usuarios WHERE id = ?')->execute([$id]);
    registrarAtividade(($alvo['papel'] === 'Tutor' ? 'Tutor ' : 'Usuário ') . $alvo['nome'] . ' foi removido da plataforma');
    responder(['ok' => true]);
}

if ($acao === 'perfil') {
    $u = exigir();
    $d = entrada();
    $nome = trim((string)($d['nome'] ?? $u['nome']));
    if ($nome === '') erro('Nome inválido.');
    $avatar = array_key_exists('avatar', $d) ? $d['avatar'] : $u['avatar'];
    if ($avatar !== null && $avatar !== $u['avatar'] && !avatarValido($avatar)) erro('Foto inválida ou grande demais.');

    $email = strtolower(trim((string)($d['email'] ?? $u['email'])));
    $hash = $u['senha_hash'];
    $trocaSenha = !empty($d['novaSenha']);
    $trocaEmail = $email !== $u['email'];
    if ($trocaSenha || $trocaEmail) {
        // trocar email ou senha exige a senha atual (mesma regra de qualquer site sério)
        if (!password_verify((string)($d['senhaAtual'] ?? ''), $u['senha_hash'])) erro('Para trocar email ou senha, informe sua senha atual corretamente.', 403);
    }
    if ($trocaEmail) {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) erro('Email inválido.');
        $st = db()->prepare('SELECT 1 FROM usuarios WHERE email = ? AND id <> ?'); $st->execute([$email, $u['id']]);
        if ($st->fetch()) erro('Já existe uma conta com esse email.', 409);
    }
    if ($trocaSenha) {
        if (strlen((string)$d['novaSenha']) < 6) erro('A nova senha precisa ter no mínimo 6 caracteres.');
        $hash = password_hash((string)$d['novaSenha'], PASSWORD_DEFAULT);
    }
    $bio = array_key_exists('bio', $d) ? mb_substr(trim((string)$d['bio']), 0, 500) : $u['bio'];
    $disp = array_key_exists('disponivel', $d) ? (int)(bool)$d['disponivel'] : (int)$u['disponivel'];
    $config = $u['config'];
    if (array_key_exists('config', $d) && is_array($d['config'])) {
        $limpo = [];
        foreach ($d['config'] as $k => $v) if (preg_match('/^[a-z_]{1,40}$/', (string)$k) && (is_bool($v) || is_string($v))) $limpo[$k] = is_string($v) ? mb_substr($v, 0, 500) : $v;
        $config = json_encode($limpo, JSON_UNESCAPED_UNICODE);
    }
    db()->prepare('UPDATE usuarios SET nome=?, email=?, avatar=?, senha_hash=?, bio=?, disponivel=?, config=? WHERE id=?')
        ->execute([$nome, $email, $avatar, $hash, $bio ?: null, $disp, $config, $u['id']]);
    responder(['usuario' => usuarioPublico(usuarioAtual())]);
}
erro('Ação desconhecida.', 404);
