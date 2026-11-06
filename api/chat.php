<?php
// Chat entre aluno e tutor (e suporte = administrador). Só participantes enxergam a conversa.
// GET  chat.php?acao=conversas[&com=ID]         lista de conversas (com=ID garante que esse contato apareça)
// GET  chat.php?acao=mensagens&com=ID[&depois=N] mensagens (depois=N → só as novas, pra atualizar a tela)
// POST chat.php?acao=enviar                     {para, texto} ou multipart com arquivo
// GET  chat.php?acao=arquivo&id=N               baixa anexo (só quem participa da conversa)
require __DIR__ . '/bootstrap.php';
$u = exigir();
$uid = (int)$u['id'];
$acao = $_GET['acao'] ?? 'conversas';
const EXT_CHAT = ['pdf', 'png', 'jpg', 'jpeg', 'gif', 'txt', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'mp4'];

function podeConversar(array $eu, array $outro): bool {
    if ((int)$eu['id'] === (int)$outro['id']) return false;
    if ($outro['papel'] === 'Tutor' && !in_array($outro['status'], ['Aprovado', 'Ativo'], true)) return false;
    if ($eu['papel'] === 'Administrador' || $outro['papel'] === 'Administrador') return true; // suporte
    return $eu['papel'] !== $outro['papel']; // aluno <-> tutor
}
function usuarioPorId(int $id): ?array {
    $st = db()->prepare('SELECT * FROM usuarios WHERE id = ?'); $st->execute([$id]);
    return $st->fetch() ?: null;
}
function msgPublica(array $m, int $uid): array {
    return ['id' => (int)$m['id'], 'de' => (int)$m['de_id'] === $uid ? 'eu' : 'eles', 'texto' => $m['texto'],
        'arquivo' => $m['arquivo_path'] ? ['nome' => $m['arquivo_nome'], 'tamanho' => (int)$m['arquivo_tamanho'], 'url' => 'api/chat.php?acao=arquivo&id=' . (int)$m['id']] : null,
        'criadoEm' => $m['criado_em']];
}

switch ($acao) {
case 'conversas':
    $ids = [];
    $st = db()->prepare('SELECT DISTINCT CASE WHEN de_id = ? THEN para_id ELSE de_id END AS o FROM mensagens WHERE de_id = ? OR para_id = ?');
    $st->execute([$uid, $uid, $uid]);
    foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $o) $ids[(int)$o] = true;
    if ($u['papel'] === 'Aluno') {
        $st = db()->prepare('SELECT DISTINCT m.tutor_id FROM inscricoes i JOIN materias m ON m.id = i.materia_id WHERE i.aluno_id = ? AND m.tutor_id IS NOT NULL');
        $st->execute([$uid]);
        foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $o) $ids[(int)$o] = true;
    }
    if ($u['papel'] !== 'Administrador') {
        $adm = db()->query("SELECT id FROM usuarios WHERE papel = 'Administrador' ORDER BY id LIMIT 1")->fetchColumn();
        if ($adm) $ids[(int)$adm] = true;
    }
    if (!empty($_GET['com'])) $ids[(int)$_GET['com']] = true;

    $saida = [];
    foreach (array_keys($ids) as $oid) {
        $o = usuarioPorId($oid);
        if (!$o || !podeConversar($u, $o)) continue;
        $st = db()->prepare('SELECT * FROM mensagens WHERE (de_id=? AND para_id=?) OR (de_id=? AND para_id=?) ORDER BY id DESC LIMIT 1');
        $st->execute([$uid, $oid, $oid, $uid]);
        $ult = $st->fetch() ?: null;
        $nl = db()->prepare('SELECT COUNT(*) FROM mensagens WHERE de_id=? AND para_id=? AND lida=0'); $nl->execute([$oid, $uid]);
        $materias = [];
        if ($o['papel'] === 'Tutor') {
            $ms = db()->prepare('SELECT nome FROM materias WHERE tutor_id = ? ORDER BY nome'); $ms->execute([$oid]);
            $materias = $ms->fetchAll(PDO::FETCH_COLUMN);
        }
        $saida[] = ['id' => $oid, 'nome' => $o['papel'] === 'Administrador' ? 'Suporte StudyHelp' : $o['nome'], 'suporte' => $o['papel'] === 'Administrador',
            'papel' => $o['papel'], 'avatar' => $o['papel'] === 'Administrador' ? null : avatarUrl($o),
            'materia' => $materias ? implode(', ', $materias) : null, 'online' => $o['papel'] === 'Administrador' ? true : (bool)$o['disponivel'],
            'ultima' => $ult ? msgPublica($ult, $uid) : null, 'naoLidas' => (int)$nl->fetchColumn()];
    }
    usort($saida, fn($a, $b) => strcmp($b['ultima']['criadoEm'] ?? '', $a['ultima']['criadoEm'] ?? ''));
    responder(['conversas' => $saida]);

case 'mensagens':
    $com = (int)($_GET['com'] ?? 0);
    $o = usuarioPorId($com);
    if (!$o || !podeConversar($u, $o)) erro('Conversa não permitida.', 403);
    $depois = (int)($_GET['depois'] ?? 0);
    $st = db()->prepare('SELECT * FROM mensagens WHERE id > ? AND ((de_id=? AND para_id=?) OR (de_id=? AND para_id=?)) ORDER BY id LIMIT 500');
    $st->execute([$depois, $uid, $com, $com, $uid]);
    $r = $st->fetchAll();
    db()->prepare('UPDATE mensagens SET lida = 1 WHERE de_id = ? AND para_id = ? AND lida = 0')->execute([$com, $uid]);
    responder(['mensagens' => array_map(fn($m) => msgPublica($m, $uid), $r)]);

case 'enviar':
    $d = entrada();
    $para = (int)($d['para'] ?? 0);
    $o = usuarioPorId($para);
    if (!$o || !podeConversar($u, $o)) erro('Conversa não permitida.', 403);
    $texto = trim((string)($d['texto'] ?? ''));
    if (mb_strlen($texto) > 4000) erro('Mensagem grande demais.');
    $up = salvarUpload('arquivo', 'chat', EXT_CHAT, 20_000_000);
    if ($texto === '' && !$up) erro('Escreva uma mensagem ou anexe um arquivo.');
    db()->prepare('INSERT INTO mensagens (de_id,para_id,texto,arquivo_nome,arquivo_path,arquivo_tamanho) VALUES (?,?,?,?,?,?)')
        ->execute([$uid, $para, $texto ?: null, $up[0] ?? null, $up[1] ?? null, $up[2] ?? null]);
    $st = db()->prepare('SELECT * FROM mensagens WHERE id = ?'); $st->execute([(int)db()->lastInsertId()]);
    responder(['mensagem' => msgPublica($st->fetch(), $uid)], 201);

case 'arquivo':
    $st = db()->prepare('SELECT * FROM mensagens WHERE id = ?'); $st->execute([(int)($_GET['id'] ?? 0)]);
    $m = $st->fetch();
    if (!$m || !$m['arquivo_path'] || ((int)$m['de_id'] !== $uid && (int)$m['para_id'] !== $uid)) erro('Arquivo não encontrado.', 404);
    enviarUpload($m['arquivo_path'], $m['arquivo_nome'], !empty($_GET['inline']));

default: erro('Ação desconhecida.', 404);
}
