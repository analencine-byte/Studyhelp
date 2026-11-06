<?php
// GET  materias.php                       público: matérias (tutor, nº de alunos e de conteúdos)
// GET  materias.php?acao=do_tutor         (tutor) só as minhas matérias
// GET  materias.php?acao=admin            (admin) tudo + ids de alunos inscritos
// GET  materias.php?acao=alunos           (tutor/admin) alunos inscritos nas matérias do tutor
// GET  materias.php?acao=minhas           (aluno) inscrições + favoritas
// POST materias.php?acao=criar            (tutor aprovado/admin) {nome,descricao?}
// POST materias.php?acao=editar&id=N      (dono/admin) {nome,descricao?}
// POST materias.php?acao=excluir&id=N     (dono/admin)
// POST materias.php?acao=atribuir         (admin) {tutorId, materiaIds:[]}
// POST materias.php?acao=inscrever|desinscrever&id=N   (aluno)
// POST materias.php?acao=favoritas        (aluno) {favoritas:["Matemática",...]}
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
const SQL_LISTA = 'SELECT m.id, m.nome, m.descricao, m.tutor_id AS tutorId, u.nome AS tutorNome,
        (SELECT COUNT(*) FROM inscricoes i WHERE i.materia_id = m.id) AS alunos,
        (SELECT COUNT(*) FROM materiais x WHERE x.materia_id = m.id) AS conteudos
        FROM materias m LEFT JOIN usuarios u ON u.id = m.tutor_id';
function normalizar(array $r): array {
    foreach ($r as &$m) { $m['id'] = (int)$m['id']; $m['tutorId'] = $m['tutorId'] === null ? null : (int)$m['tutorId']; $m['alunos'] = (int)$m['alunos']; $m['conteudos'] = (int)$m['conteudos']; }
    return $r;
}

switch ($acao) {
case 'listar':
    responder(['materias' => normalizar(db()->query(SQL_LISTA . ' ORDER BY m.nome')->fetchAll())]);

case 'do_tutor':
    $u = exigirTutor();
    $st = db()->prepare(SQL_LISTA . ' WHERE m.tutor_id = ? ORDER BY m.nome'); $st->execute([$u['id']]);
    responder(['materias' => normalizar($st->fetchAll())]);

case 'admin':
    exigir(['Administrador']);
    $r = normalizar(db()->query(SQL_LISTA . ' ORDER BY m.nome')->fetchAll());
    $ins = [];
    foreach (db()->query('SELECT aluno_id, materia_id FROM inscricoes')->fetchAll() as $i) $ins[$i['materia_id']][] = (string)$i['aluno_id'];
    foreach ($r as &$m) $m['alunosIds'] = $ins[$m['id']] ?? [];
    responder(['materias' => $r]);

case 'criar':
    $u = exigirTutor();
    $d = entrada(); $nome = trim((string)($d['nome'] ?? ''));
    if ($nome === '' || mb_strlen($nome) > 80) erro('Informe o nome da matéria (até 80 caracteres).');
    db()->prepare('INSERT INTO materias (nome,descricao,tutor_id) VALUES (?,?,?)')->execute([$nome, $d['descricao'] ?? null, $u['papel'] === 'Tutor' ? $u['id'] : null]);
    $novoId = (int)db()->lastInsertId();
    registrarAtividade($u['papel'] === 'Tutor' ? "{$u['nome']} criou a matéria $nome" : "Matéria \"$nome\" foi criada");
    responder(['id' => $novoId], 201);

case 'editar':
    $u = exigirTutor(); materiaDoUsuario($id, $u);
    $d = entrada(); $nome = trim((string)($d['nome'] ?? ''));
    if ($nome === '' || mb_strlen($nome) > 80) erro('Informe o nome da matéria (até 80 caracteres).');
    db()->prepare('UPDATE materias SET nome=?, descricao=? WHERE id=?')->execute([$nome, $d['descricao'] ?? null, $id]);
    responder(['ok' => true]);

case 'excluir':
    $u = exigirTutor(); $m = materiaDoUsuario($id, $u);
    db()->prepare('DELETE FROM materias WHERE id = ?')->execute([$id]);
    registrarAtividade("Matéria \"{$m['nome']}\" foi removida");
    responder(['ok' => true]);

case 'atribuir':
    exigir(['Administrador']);
    $d = entrada(); $tutorId = (int)($d['tutorId'] ?? 0);
    $st = db()->prepare("SELECT nome FROM usuarios WHERE id = ? AND papel = 'Tutor'"); $st->execute([$tutorId]);
    $tutor = $st->fetch();
    if (!$tutor) erro('Tutor não encontrado.', 404);
    $ids = array_values(array_unique(array_map('intval', (array)($d['materiaIds'] ?? []))));
    db()->beginTransaction();
    db()->prepare('UPDATE materias SET tutor_id = NULL WHERE tutor_id = ?')->execute([$tutorId]);
    $up = db()->prepare('UPDATE materias SET tutor_id = ? WHERE id = ?');
    foreach ($ids as $mid) $up->execute([$tutorId, $mid]);
    db()->commit();
    registrarAtividade("Matérias do tutor {$tutor['nome']} foram atualizadas");
    responder(['ok' => true]);

case 'alunos':
    $u = exigirTutor();
    $sql = 'SELECT a.id, a.nome, a.email, (a.avatar IS NOT NULL) AS temAvatar, m.id AS materiaId, m.nome AS materia
            FROM inscricoes i JOIN usuarios a ON a.id = i.aluno_id JOIN materias m ON m.id = i.materia_id';
    $args = [];
    if ($u['papel'] === 'Tutor') { $sql .= ' WHERE m.tutor_id = ?'; $args[] = $u['id']; }
    $st = db()->prepare($sql . ' ORDER BY a.nome, m.nome'); $st->execute($args);
    $r = $st->fetchAll();
    foreach ($r as &$x) { $x['id'] = (int)$x['id']; $x['materiaId'] = (int)$x['materiaId']; $x['temAvatar'] = (bool)$x['temAvatar']; }
    responder(['alunos' => $r]);

case 'minhas':
    $u = exigir(['Aluno']);
    $i = db()->prepare('SELECT materia_id FROM inscricoes WHERE aluno_id = ?'); $i->execute([$u['id']]);
    $f = db()->prepare('SELECT materia_nome FROM favoritas WHERE aluno_id = ?'); $f->execute([$u['id']]);
    responder(['inscritas' => array_map('intval', $i->fetchAll(PDO::FETCH_COLUMN)), 'favoritas' => $f->fetchAll(PDO::FETCH_COLUMN)]);

case 'inscrever': case 'desinscrever':
    $u = exigir(['Aluno']);
    $st = db()->prepare('SELECT nome, tutor_id FROM materias WHERE id = ?'); $st->execute([$id]);
    $m = $st->fetch();
    if (!$m) erro('Matéria não encontrada.', 404);
    if ($acao === 'inscrever') {
        $ja = db()->prepare('SELECT 1 FROM inscricoes WHERE aluno_id=? AND materia_id=?'); $ja->execute([$u['id'], $id]);
        if (!$ja->fetch()) {
            db()->prepare('INSERT INTO inscricoes (aluno_id,materia_id) VALUES (?,?)')->execute([$u['id'], $id]);
            registrarAtividade("{$u['nome']} se inscreveu em {$m['nome']}");
        }
    } else {
        db()->prepare('DELETE FROM inscricoes WHERE aluno_id=? AND materia_id=?')->execute([$u['id'], $id]);
    }
    responder(['ok' => true]);

case 'favoritas':
    $u = exigir(['Aluno']);
    $lista = array_values(array_unique(array_filter(array_map(fn($x) => mb_substr(trim((string)$x), 0, 80), (array)(entrada()['favoritas'] ?? [])))));
    db()->beginTransaction();
    db()->prepare('DELETE FROM favoritas WHERE aluno_id = ?')->execute([$u['id']]);
    $ins = db()->prepare('INSERT INTO favoritas (aluno_id,materia_nome) VALUES (?,?)');
    foreach (array_slice($lista, 0, 50) as $n) $ins->execute([$u['id'], $n]);
    db()->commit();
    responder(['favoritas' => $lista]);

default: erro('Ação desconhecida.', 404);
}
