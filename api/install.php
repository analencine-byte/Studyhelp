<?php
// Cria as tabelas e (na primeira vez) os dados de demonstração.
// Uso (terminal):  php api/install.php   — ou, em hospedagem, pelo navegador: install.php?chave=SUA_CHAVE
// DEPOIS DE INSTALAR EM HOSPEDAGEM, APAGUE ESTE ARQUIVO do servidor.
//  - SH_ADMIN_PASS=suaSenha   define a senha do admin (padrão só pra demonstração — TROQUE!)
//  - SH_SEED_DEMO=0           cria só o admin, sem tutores/alunos/materiais de exemplo
declare(strict_types=1);
$c = require __DIR__ . '/config.php';
$web = PHP_SAPI !== 'cli';
if ($web) {
    // Pelo navegador só roda com a chave secreta (chave_instalacao no config.local.php): install.php?chave=...
    header('Content-Type: text/plain; charset=utf-8');
    if ($c['chave_instalacao'] === '' || !hash_equals($c['chave_instalacao'], (string)($_GET['chave'] ?? ''))) {
        http_response_code(403);
        exit("Acesso negado. Defina 'chave_instalacao' no api/config.local.php e abra install.php?chave=SUA_CHAVE\n");
    }
}
try {
    if ($c['driver'] === 'sqlite') {
        $pdo = new PDO('sqlite:' . $c['sqlite']);
        $sql = file_get_contents(__DIR__ . '/sql/schema.sqlite.sql');
    } else {
        $sql = file_get_contents(__DIR__ . '/sql/schema.sql');
        try {
            // hospedagem: o banco já existe (criado no painel) — conecta direto nele
            $pdo = new PDO("mysql:host={$c['host']};port={$c['porta']};dbname={$c['banco']};charset=utf8mb4", $c['user'], $c['senha']);
            $sql = preg_replace('/^\s*(CREATE DATABASE|USE)\b[^;]*;/mi', '', $sql);
        } catch (PDOException $e) {
            // XAMPP: banco ainda não existe — cria
            $pdo = new PDO("mysql:host={$c['host']};port={$c['porta']};charset=utf8mb4", $c['user'], $c['senha']);
        }
    }
} catch (PDOException $e) {
    http_response_code(500);
    exit('Não foi possível conectar ao banco: ' . $e->getMessage() . "\nConfira host, banco, usuário e senha em api/config.php (ou config.local.php).\n");
}
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$sql = preg_replace('/^\s*--[^\n]*$/m', '', $sql); // tira comentários antes de separar os comandos
foreach (array_filter(array_map('trim', explode(';', $sql))) as $cmd) $pdo->exec($cmd);
if ($c['driver'] !== 'sqlite') {
    if (stripos($sql, 'USE ') !== false) $pdo->exec('USE ' . $c['banco']);
    try { $pdo->exec("SET time_zone = '+00:00'"); } catch (Throwable $e) {}
}

$primeiraVez = !$pdo->query('SELECT 1 FROM usuarios LIMIT 1')->fetch();
$adminPass = getenv('SH_ADMIN_PASS') ?: ($c['senha_admin'] ?? 'studyhelp2026');
$seedDemo = getenv('SH_SEED_DEMO') !== '0';

$user = $pdo->prepare('INSERT INTO usuarios (nome,email,senha_hash,papel,status,bio,criado_em) VALUES (?,?,?,?,?,?,?)');
$achar = $pdo->prepare('SELECT id FROM usuarios WHERE email = ?');
function uid(PDOStatement $achar, string $email): int { $achar->execute([$email]); return (int)$achar->fetchColumn(); }

$achar->execute(['admin@studyhelp.com']);
if (!$achar->fetch()) $user->execute(['Administrador', 'admin@studyhelp.com', password_hash($adminPass, PASSWORD_DEFAULT), 'Administrador', 'Aprovado', null, '2025-01-10 12:00:00']);
foreach (['aprovar_tutores_manualmente' => '1', 'notificar_cadastro' => '1', 'nome_plataforma' => 'StudyHelp'] as $k => $v) {
    $e = $pdo->prepare('SELECT 1 FROM configuracoes WHERE chave = ?'); $e->execute([$k]);
    if (!$e->fetch()) $pdo->prepare('INSERT INTO configuracoes (chave,valor) VALUES (?,?)')->execute([$k, $v]);
}

if ($primeiraVez && $seedDemo) {
    $h = fn($s) => password_hash($s, PASSWORD_DEFAULT);
    $pessoas = [
      ['Lucas Oliveira', 'lucas.oliveira@email.com', 'Tutor', 'Aprovado', 'Professor de matemática com 8 anos de experiência. Ajudo alunos do fundamental ao ensino superior.', '2025-02-20 12:00:00'],
      ['Juliana Santos', 'juliana.santos@email.com', 'Tutor', 'Aprovado', 'Especialista em redação e gramática. Vamos melhorar sua escrita e interpretação!', '2025-05-02 12:00:00'],
      ['Carla Menezes', 'carla.menezes@email.com', 'Tutor', 'Aprovado', 'Bióloga e professora apaixonada por ensinar ciências de forma prática e divertida.', '2025-04-18 12:00:00'],
      ['Rodrigo Alves', 'rodrigo.alves@email.com', 'Tutor', 'Ativo', 'Historiador com foco em Brasil Colônia e Era das Revoluções.', '2025-06-30 12:00:00'],
      ['Fernanda Lopes', 'fernanda.lopes@email.com', 'Tutor', 'Aprovado', 'Aulas de conversação e gramática para todos os níveis, do básico ao avançado.', '2025-07-08 12:00:00'],
      ['Mariana Souza', 'mariana.souza@email.com', 'Tutor', 'Pendente', null, '2026-09-20 12:00:00'],
      ['Ana Fernanda', 'ana.fernanda@email.com', 'Aluno', 'Aprovado', null, '2025-03-12 12:00:00'],
      ['Pedro Henrique', 'pedro.henrique@email.com', 'Aluno', 'Aprovado', null, '2026-01-15 12:00:00'],
    ];
    foreach ($pessoas as [$n, $e, $p, $s, $bio, $dt]) $user->execute([$n, $e, $h('senha123'), $p, $s, $bio, $dt]);
    $id = fn($email) => uid($achar, $email);
    $L = $id('lucas.oliveira@email.com'); $J = $id('juliana.santos@email.com'); $C = $id('carla.menezes@email.com');
    $R = $id('rodrigo.alves@email.com'); $F = $id('fernanda.lopes@email.com'); $A = $id('ana.fernanda@email.com'); $P = $id('pedro.henrique@email.com');

    $mat = $pdo->prepare('INSERT INTO materias (nome,tutor_id) VALUES (?,?)');
    foreach ([['Matemática', $L], ['Português', $J], ['Ciências', $C], ['História', $R], ['Inglês', $F], ['Informática', null], ['Física', null], ['Química', null], ['Biologia', null]] as $m) $mat->execute($m);
    $mid = fn($n) => (int)$pdo->query("SELECT id FROM materias WHERE nome = '$n'")->fetchColumn();
    $ins = $pdo->prepare('INSERT INTO inscricoes (aluno_id,materia_id) VALUES (?,?)');
    foreach ([[$A, $mid('Matemática')], [$A, $mid('Português')], [$P, $mid('Matemática')]] as $i) $ins->execute($i);

    $mt = $pdo->prepare('INSERT INTO materiais (materia_id,tutor_id,titulo,descricao,tipo,criado_em) VALUES (?,?,?,?,?,?)');
    foreach ([
      ['Matemática', $L, 'Funções do 1º grau', 'Explico como identificar e resolver funções do 1º grau passo a passo.', 'Vídeo', '2026-05-22 12:00:00'],
      ['Ciências', $C, 'Leis de Newton', 'Resumo completo com exemplos práticos das três leis de Newton.', 'PDF', '2026-05-20 12:00:00'],
      ['Ciências', $C, 'Reações químicas', 'Lista com 10 exercícios sobre balanceamento de equações.', 'Exercício', '2026-05-19 12:00:00'],
      ['Português', $J, 'Figuras de linguagem', 'Metáfora, metonímia, hipérbole e outras figuras explicadas com exemplos.', 'Resumo', '2026-05-18 12:00:00'],
      ['História', $R, 'Revolução Francesa', 'Contexto, causas e consequências da Era das Revoluções.', 'PDF', '2026-05-17 12:00:00'],
      ['Inglês', $F, 'Verbos irregulares em inglês', 'Videoaula com os verbos irregulares mais cobrados em provas.', 'Vídeo', '2026-05-15 12:00:00'],
    ] as [$mn, $t, $ti, $d, $tp, $dt]) $mt->execute([$mid($mn), $t, $ti, $d, $tp, $dt]);

    $tf = $pdo->prepare('INSERT INTO tarefas (aluno_id,titulo,materia,tipo,status,tutor_id,nota,criado_em) VALUES (?,?,?,?,?,?,?,?)');
    foreach ([
      ['Dúvida sobre Funções do 1º grau', 'Matemática', 'duvida', 'Respondida', $L, null, '2026-05-22 12:00:00'],
      ['Redação: Dissertação argumentativa', 'Português', 'caderno', 'Em andamento', null, null, '2026-05-20 12:00:00'],
      ['Exercícios de Frações', 'Matemática', 'material', 'Concluída', null, '9,0', '2026-05-18 12:00:00'],
      ['Dúvida sobre Revolução Francesa', 'História', 'duvida', 'Em andamento', $R, null, '2026-05-15 12:00:00'],
    ] as $t) $tf->execute(array_merge([$A], $t));

    $msg = $pdo->prepare('INSERT INTO mensagens (de_id,para_id,texto,lida,criado_em) VALUES (?,?,?,1,?)');
    foreach ([
      [$L, $A, 'Oi Ana! Sobre sua dúvida de Funções do 1º grau, você já tentou identificar o coeficiente angular?', '2026-05-22 12:00:00'],
      [$A, $L, 'Ainda não, não entendi muito bem essa parte 😅', '2026-05-22 12:05:00'],
      [$L, $A, 'Tranquilo! O coeficiente angular é o "a" da função f(x) = ax + b. Ele indica a inclinação da reta.', '2026-05-22 12:07:00'],
    ] as $m) $msg->execute($m);

    $cad = $pdo->prepare('INSERT INTO cadernos (aluno_id,titulo,descricao,criado_em,atualizado_em) VALUES (?,?,?,?,?)');
    foreach ([['Matemática', 'Fórmulas e exercícios', '2026-05-12 12:00:00'], ['Português', 'Figuras de linguagem', '2026-05-10 12:00:00']] as $c2) $cad->execute([$A, $c2[0], $c2[1], $c2[2], $c2[2]]);
    $fav = $pdo->prepare('INSERT INTO favoritas (aluno_id,materia_nome) VALUES (?,?)');
    foreach (['Matemática', 'Português', 'História'] as $f) $fav->execute([$A, $f]);

    $at = $pdo->prepare('INSERT INTO atividades (texto,criado_em) VALUES (?,?)');
    foreach ([['Mariana Souza solicitou cadastro como tutor(a)', '2026-09-20 12:00:00'], ['Pedro Henrique se cadastrou como aluno(a)', '2026-01-15 12:00:00']] as $a) $at->execute($a);
    echo "Dados de demonstração criados (senha dos usuários de exemplo: senha123).\n";
}
echo "Banco pronto.\n";
