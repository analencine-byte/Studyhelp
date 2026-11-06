<?php
// GET  materiais.php                    público: lista (sem o arquivo)
// GET  materiais.php?acao=ver&id=N      (logado) detalhes; conta como "material acessado" pro aluno
// GET  materiais.php?acao=baixar&id=N   (logado) baixa o arquivo (&inline=1 abre no navegador)
// POST materiais.php?acao=criar         (tutor aprovado) multipart: materiaId, tipo, titulo, descricao, arquivo
// POST materiais.php?acao=excluir&id=N  (dono/admin)
require __DIR__ . '/bootstrap.php';
$acao = $_GET['acao'] ?? 'listar';
$id = (int)($_GET['id'] ?? 0);
const EXT_OK = ['pdf', 'mp4', 'webm', 'png', 'jpg', 'jpeg', 'gif', 'txt', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx'];
const TIPOS = ['Vídeo', 'PDF', 'Exercício', 'Resumo'];

const SQL_MAT = 'SELECT x.id, x.titulo, x.descricao, x.tipo, x.criado_em AS criadoEm, x.arquivo_nome AS arquivoNome,
        (x.arquivo_path IS NOT NULL) AS temArquivo, m.id AS materiaId, m.nome AS materia, u.id AS tutorId, u.nome AS tutorNome
        FROM materiais x JOIN materias m ON m.id = x.materia_id LEFT JOIN usuarios u ON u.id = x.tutor_id';
function materialPorId(int $id): array {
    $st = db()->prepare(SQL_MAT . ' WHERE x.id = ?'); $st->execute([$id]);
    $r = $st->fetch();
    if (!$r) erro('Material não encontrado.', 404);
    return $r;
}
function normalizarMat(array $r): array {
    $r['id'] = (int)$r['id']; $r['materiaId'] = (int)$r['materiaId']; $r['tutorId'] = $r['tutorId'] === null ? null : (int)$r['tutorId'];
    $r['temArquivo'] = (bool)$r['temArquivo'];
    return $r;
}

switch ($acao) {
case 'listar':
    $r = db()->query(SQL_MAT . ' ORDER BY x.criado_em DESC, x.id DESC')->fetchAll();
    responder(['materiais' => array_map('normalizarMat', $r)]);

case 'ver':
    $u = exigir();
    $m = normalizarMat(materialPorId($id));
    if ($u['papel'] === 'Aluno') {
        $ja = db()->prepare('SELECT 1 FROM acessos_material WHERE aluno_id=? AND material_id=?'); $ja->execute([$u['id'], $id]);
        if (!$ja->fetch()) db()->prepare('INSERT INTO acessos_material (aluno_id,material_id) VALUES (?,?)')->execute([$u['id'], $id]);
    }
    $m['url'] = $m['temArquivo'] ? "api/materiais.php?acao=baixar&id=$id" : null;
    responder(['material' => $m]);

case 'baixar':
    exigir();
    $st = db()->prepare('SELECT arquivo_path, arquivo_nome FROM materiais WHERE id = ?'); $st->execute([$id]);
    $a = $st->fetch();
    if (!$a || !$a['arquivo_path']) erro('Esse material não tem arquivo anexado.', 404);
    enviarUpload($a['arquivo_path'], $a['arquivo_nome'], !empty($_GET['inline']));

case 'criar':
    $u = exigir(['Tutor', 'Administrador']);
    if ($u['papel'] === 'Tutor' && !in_array($u['status'], ['Aprovado', 'Ativo'], true)) erro('Seu cadastro de tutor ainda não foi aprovado.', 403);
    $d = entrada();
    $titulo = trim((string)($d['titulo'] ?? ''));
    $tipo = (string)($d['tipo'] ?? 'PDF');
    if ($titulo === '' || mb_strlen($titulo) > 160) erro('Informe um título (até 160 caracteres).');
    if (!in_array($tipo, TIPOS, true)) erro('Tipo de conteúdo inválido.');
    $st = db()->prepare('SELECT id, nome, tutor_id FROM materias WHERE id = ?'); $st->execute([(int)($d['materiaId'] ?? 0)]);
    $mat = $st->fetch();
    if (!$mat) erro('Escolha uma matéria válida.');
    if ($u['papel'] === 'Tutor' && (int)$mat['tutor_id'] !== (int)$u['id']) erro('Você só publica nas suas matérias.', 403);
    $up = salvarUpload('arquivo', 'materiais', EXT_OK, 50_000_000);
    db()->prepare('INSERT INTO materiais (materia_id,tutor_id,titulo,descricao,tipo,arquivo_nome,arquivo_path) VALUES (?,?,?,?,?,?,?)')
        ->execute([$mat['id'], $u['id'], $titulo, mb_substr(trim((string)($d['descricao'] ?? '')), 0, 2000) ?: null, $tipo, $up[0] ?? null, $up[1] ?? null]);
    $novoId = (int)db()->lastInsertId();
    registrarAtividade("{$u['nome']} publicou \"$titulo\" em {$mat['nome']}");
    responder(['id' => $novoId], 201);

case 'excluir':
    $u = exigir(['Tutor', 'Administrador']);
    $m = materialPorId($id);
    if ($u['papel'] === 'Tutor' && (int)$m['tutorId'] !== (int)$u['id']) erro('Esse material não é seu.', 403);
    $st = db()->prepare('SELECT arquivo_path FROM materiais WHERE id = ?'); $st->execute([$id]);
    $p = $st->fetchColumn();
    db()->prepare('DELETE FROM materiais WHERE id = ?')->execute([$id]);
    if ($p && is_file(__DIR__ . '/uploads/' . $p)) @unlink(__DIR__ . '/uploads/' . $p);
    responder(['ok' => true]);

default: erro('Ação desconhecida.', 404);
}
