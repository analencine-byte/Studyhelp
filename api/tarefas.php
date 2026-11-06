<?php
// Atividades do aluno (dúvidas, exercícios, redações).
// GET  tarefas.php                     (aluno) minhas atividades
// POST tarefas.php?acao=duvida         (aluno) {materia, tipo?, texto}  → cria a atividade e manda a dúvida pro tutor no chat
// POST tarefas.php?acao=status&id=N    (aluno) {status:'Em andamento'|'Concluída'|'Respondida'}
require __DIR__ . '/bootstrap.php';
$u = exigir(['Aluno']);
$acao = $_GET['acao'] ?? 'listar';

if ($acao === 'listar') {
    $st = db()->prepare('SELECT t.id, t.titulo, t.materia, t.tipo, t.status, t.nota, t.criado_em AS criadoEm, t.tutor_id AS tutorId, u.nome AS tutorNome
        FROM tarefas t LEFT JOIN usuarios u ON u.id = t.tutor_id WHERE t.aluno_id = ? ORDER BY t.criado_em DESC, t.id DESC');
    $st->execute([$u['id']]);
    $r = $st->fetchAll();
    foreach ($r as &$t) { $t['id'] = (int)$t['id']; $t['tutorId'] = $t['tutorId'] === null ? null : (int)$t['tutorId']; }
    responder(['tarefas' => $r]);
}

if ($acao === 'duvida') {
    $d = entrada();
    $texto = trim((string)($d['texto'] ?? ''));
    $materia = trim((string)($d['materia'] ?? ''));
    $tipoDuvida = trim((string)($d['tipo'] ?? 'Dúvida rápida'));
    if ($texto === '' || mb_strlen($texto) > 2000) erro('Descreva sua dúvida (até 2000 caracteres).');
    // acha o tutor da matéria (o com mais alunos, se houver mais de um)
    $st = db()->prepare("SELECT m.tutor_id FROM materias m JOIN usuarios t ON t.id = m.tutor_id
        WHERE m.nome = ? AND t.status IN ('Aprovado','Ativo')
        ORDER BY (SELECT COUNT(*) FROM inscricoes i WHERE i.materia_id = m.id) DESC LIMIT 1");
    $st->execute([$materia]);
    $tutorId = $st->fetchColumn() ?: null;
    $titulo = mb_substr(($tipoDuvida !== '' ? "$tipoDuvida: " : '') . $texto, 0, 90);
    db()->prepare('INSERT INTO tarefas (aluno_id,titulo,materia,tipo,status,tutor_id) VALUES (?,?,?,?,?,?)')
        ->execute([$u['id'], $titulo, $materia ?: null, 'duvida', 'Em andamento', $tutorId]);
    $novoId = (int)db()->lastInsertId();
    if ($tutorId) {
        db()->prepare('INSERT INTO mensagens (de_id,para_id,texto) VALUES (?,?,?)')
            ->execute([$u['id'], $tutorId, "[Dúvida de " . ($materia ?: 'geral') . "] $texto"]);
    }
    responder(['id' => $novoId, 'tutorId' => $tutorId ? (int)$tutorId : null], 201);
}

if ($acao === 'status') {
    $status = entrada()['status'] ?? '';
    if (!in_array($status, ['Em andamento', 'Concluída', 'Respondida'], true)) erro('Status inválido.');
    db()->prepare('UPDATE tarefas SET status = ? WHERE id = ? AND aluno_id = ?')->execute([$status, (int)($_GET['id'] ?? 0), $u['id']]);
    responder(['ok' => true]);
}
erro('Ação desconhecida.', 404);
