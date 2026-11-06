<?php
// GET  notificacoes.php   → lista + quantas ainda não foram vistas
// POST notificacoes.php   → marca todas como vistas
require __DIR__ . '/bootstrap.php';
$u = exigir();
$uid = (int)$u['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    db()->prepare('UPDATE usuarios SET notif_visto_em = CURRENT_TIMESTAMP WHERE id = ?')->execute([$uid]);
    responder(['ok' => true]);
}

$itens = [];
$add = function (string $texto, string $quando) use (&$itens) { $itens[] = ['texto' => $texto, 'criadoEm' => $quando]; };

$st = db()->prepare('SELECT m.id, m.texto, m.arquivo_nome, m.criado_em, x.nome, x.papel FROM mensagens m JOIN usuarios x ON x.id = m.de_id
    WHERE m.para_id = ? AND m.lida = 0 ORDER BY m.id DESC LIMIT 10');
$st->execute([$uid]);
foreach ($st->fetchAll() as $m) $add(($m['papel'] === 'Administrador' ? 'Suporte StudyHelp' : $m['nome']) . ' enviou uma mensagem', $m['criado_em']);

if ($u['papel'] === 'Aluno') {
    $st = db()->prepare('SELECT x.titulo, m.nome AS materia, x.criado_em FROM materiais x JOIN materias m ON m.id = x.materia_id
        JOIN inscricoes i ON i.materia_id = m.id WHERE i.aluno_id = ? ORDER BY x.criado_em DESC LIMIT 5');
    $st->execute([$uid]);
    foreach ($st->fetchAll() as $x) $add("Novo material publicado em {$x['materia']}: {$x['titulo']}", $x['criado_em']);
} elseif ($u['papel'] === 'Tutor') {
    $st = db()->prepare('SELECT a.nome, m.nome AS materia, i.criado_em FROM inscricoes i JOIN usuarios a ON a.id = i.aluno_id
        JOIN materias m ON m.id = i.materia_id WHERE m.tutor_id = ? ORDER BY i.criado_em DESC LIMIT 5');
    $st->execute([$uid]);
    foreach ($st->fetchAll() as $x) $add("{$x['nome']} se inscreveu em {$x['materia']}", $x['criado_em']);
} else {
    $st = db()->query("SELECT nome, criado_em FROM usuarios WHERE status = 'Pendente' ORDER BY criado_em DESC LIMIT 5");
    foreach ($st->fetchAll() as $x) $add("{$x['nome']} aguarda aprovação", $x['criado_em']);
}
usort($itens, fn($a, $b) => strcmp($b['criadoEm'], $a['criadoEm']));
$itens = array_slice($itens, 0, 10);
$visto = $u['notif_visto_em'] ?? '';
$novas = count(array_filter($itens, fn($i) => $i['criadoEm'] > $visto));
responder(['notificacoes' => $itens, 'naoLidas' => $novas]);
