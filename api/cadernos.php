<?php
// Cadernos de anotações do aluno (cada um só enxerga os seus).
// GET  cadernos.php                       lista (sem conteúdo)
// GET  cadernos.php?acao=abrir&id=N       caderno com conteúdo
// POST cadernos.php?acao=criar            {titulo, descricao?}
// POST cadernos.php?acao=salvar&id=N      {conteudo}
// POST cadernos.php?acao=excluir&id=N
require __DIR__ . '/bootstrap.php';
$u = exigir(['Aluno']);
$acao = $_GET['acao'] ?? 'listar';
$id = (int)($_GET['id'] ?? 0);

function meuCaderno(int $id, int $uid): array {
    $st = db()->prepare('SELECT * FROM cadernos WHERE id = ? AND aluno_id = ?'); $st->execute([$id, $uid]);
    $c = $st->fetch();
    if (!$c) erro('Caderno não encontrado.', 404);
    return $c;
}
function cadernoPublico(array $c, bool $conteudo = false): array {
    $r = ['id' => (int)$c['id'], 'titulo' => $c['titulo'], 'descricao' => $c['descricao'], 'criadoEm' => $c['criado_em'], 'atualizadoEm' => $c['atualizado_em']];
    if ($conteudo) $r['conteudo'] = $c['conteudo'] ?? '';
    return $r;
}

switch ($acao) {
case 'listar':
    $st = db()->prepare('SELECT id,titulo,descricao,criado_em,atualizado_em FROM cadernos WHERE aluno_id = ? ORDER BY atualizado_em DESC, id DESC'); $st->execute([$u['id']]);
    responder(['cadernos' => array_map('cadernoPublico', $st->fetchAll())]);
case 'abrir':
    responder(['caderno' => cadernoPublico(meuCaderno($id, (int)$u['id']), true)]);
case 'criar':
    $d = entrada(); $t = trim((string)($d['titulo'] ?? ''));
    if ($t === '' || mb_strlen($t) > 120) erro('Escreva o nome da matéria (até 120 caracteres).');
    db()->prepare('INSERT INTO cadernos (aluno_id,titulo,descricao) VALUES (?,?,?)')->execute([$u['id'], $t, mb_substr(trim((string)($d['descricao'] ?? '')), 0, 200) ?: 'Sem descrição']);
    responder(['caderno' => cadernoPublico(meuCaderno((int)db()->lastInsertId(), (int)$u['id']))], 201);
case 'salvar':
    meuCaderno($id, (int)$u['id']);
    $conteudo = (string)(entrada()['conteudo'] ?? '');
    if (strlen($conteudo) > 1_000_000) erro('Anotação grande demais.', 413);
    db()->prepare('UPDATE cadernos SET conteudo = ?, atualizado_em = CURRENT_TIMESTAMP WHERE id = ?')->execute([$conteudo, $id]);
    responder(['ok' => true]);
case 'excluir':
    meuCaderno($id, (int)$u['id']);
    db()->prepare('DELETE FROM cadernos WHERE id = ?')->execute([$id]);
    responder(['ok' => true]);
default: erro('Ação desconhecida.', 404);
}
