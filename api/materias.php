<?php
// GET  materias.php                    público: lista matérias (com tutor e nº de alunos)
// POST materias.php?acao=criar         (tutor aprovado/admin) body {nome,descricao?}
// POST materias.php?acao=editar&id=N   (dono ou admin)
// POST materias.php?acao=excluir&id=N  (dono ou admin)
// POST materias.php?acao=inscrever&id=N | desinscrever&id=N | favoritar&id=N | desfavoritar&id=N   (aluno)
// GET  materias.php?acao=alunos        (tutor) alunos inscritos nas suas matérias
// GET  materias.php?acao=minhas        (aluno) inscrições e favoritas
require __DIR__ . '/bootstrap.php';
$acao = $_GET['acao'] ?? 'listar';
$id = (int)($_GET['id'] ?? 0);

function exigirTutor(): array {
    $u = exigir(['Tutor', 'Administrador']);
    if ($u['papel'] === 'Tutor' && !in_array($u['status'], ['Aprovado', 'Ativo'], true)) erro('Seu cadastro de tutor ainda não foi aprovado.', 403);
    return $u;
}
function materiaDoUsuario(int $id, array $u): array {
    $st = db()->prepare('SELECT * FROM materias WHERE id = ?'); $st->execute([$id]);
    $m = $st->fetch();
    if (!$m) erro('Matéria não encontrada.', 404);
    if ($u['papel'] !== 'Administrador' && (int)$m['tutor_id'] !== (int)$u['id']) erro('Essa matéria não é sua.', 403);
    return $m;
}

switch ($acao) {
case 'listar':
    $r = db()->query('SELECT m.id, m.nome, m.descricao, m.tutor_id AS tutorId, u.nome AS tutorNome,
        (SELECT COUNT(*) FROM inscricoes i WHERE i.materia_id = m.id) AS alunos
        FROM materias m LEFT JOIN usuarios u ON u.id = m.tutor_id ORDER BY m.nome')->fetchAll();
    responder(['materias' => $r]);

case 'criar':
    $u = exigirTutor();
    $d = entrada(); $nome = trim((string)($d['nome'] ?? ''));
    if ($nome === '') erro('Informe o nome da matéria.');
    db()->prepare('INSERT INTO materias (nome,descricao,tutor_id) VALUES (?,?,?)')->execute([$nome, $d['descricao'] ?? null, $u['id']]);
    registrarAtividade("{$u['nome']} criou a matéria $nome");
    responder(['id' => (int)db()->lastInsertId()], 201);

case 'editar':
    $u = exigirTutor(); materiaDoUsuario($id, $u);
    $d = entrada(); $nome = trim((string)($d['nome'] ?? ''));
    if ($nome === '') erro('Informe o nome da matéria.');
    db()->prepare('UPDATE materias SET nome=?, descricao=? WHERE id=?')->execute([$nome, $d['descricao'] ?? null, $id]);
    responder(['ok' => true]);

case 'excluir':
    $u = exigirTutor(); materiaDoUsuario($id, $u);
    db()->prepare('DELETE FROM materias WHERE id = ?')->execute([$id]);
    responder(['ok' => true]);

case 'alunos':
    $u = exigirTutor();
    $sql = 'SELECT a.id, a.nome, a.email, m.id AS materiaId, m.nome AS materia
            FROM inscricoes i JOIN usuarios a ON a.id = i.aluno_id JOIN materias m ON m.id = i.materia_id';
    $args = [];
    if ($u['papel'] === 'Tutor') { $sql .= ' WHERE m.tutor_id = ?'; $args[] = $u['id']; }
    $st = db()->prepare($sql . ' ORDER BY a.nome'); $st->execute($args);
    responder(['alunos' => $st->fetchAll()]);

case 'minhas':
    $u = exigir(['Aluno']);
    $i = db()->prepare('SELECT materia_id FROM inscricoes WHERE aluno_id = ?'); $i->execute([$u['id']]);
    $f = db()->prepare('SELECT materia_id FROM favoritas WHERE aluno_id = ?'); $f->execute([$u['id']]);
    responder(['inscritas' => array_map('intval', $i->fetchAll(PDO::FETCH_COLUMN)), 'favoritas' => array_map('intval', $f->fetchAll(PDO::FETCH_COLUMN))]);

case 'inscrever': case 'desinscrever': case 'favoritar': case 'desfavoritar':
    $u = exigir(['Aluno']);
    $st = db()->prepare('SELECT 1 FROM materias WHERE id = ?'); $st->execute([$id]);
    if (!$st->fetch()) erro('Matéria não encontrada.', 404);
    $tabela = str_contains($acao, 'inscrever') ? 'inscricoes' : 'favoritas';
    if ($acao === 'inscrever' || $acao === 'favoritar') {
        $ja = db()->prepare("SELECT 1 FROM $tabela WHERE aluno_id=? AND materia_id=?"); $ja->execute([$u['id'], $id]);
        if (!$ja->fetch()) db()->prepare("INSERT INTO $tabela (aluno_id,materia_id) VALUES (?,?)")->execute([$u['id'], $id]);
    } else {
        db()->prepare("DELETE FROM $tabela WHERE aluno_id=? AND materia_id=?")->execute([$u['id'], $id]);
    }
    responder(['ok' => true]);

default: erro('Ação desconhecida.', 404);
}
