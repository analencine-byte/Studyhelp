<?php
// Cria as tabelas e os dados iniciais. Uso (terminal):  php api/install.php
// Defina a senha do admin com SH_ADMIN_PASS (padrão abaixo é SÓ pra demonstração — troque!).
declare(strict_types=1);
$c = require __DIR__ . '/config.php';
if ($c['driver'] === 'sqlite') {
    $pdo = new PDO('sqlite:' . $c['sqlite']);
    $sql = file_get_contents(__DIR__ . '/sql/schema.sqlite.sql');
} else {
    $pdo = new PDO("mysql:host={$c['host']};port={$c['porta']};charset=utf8mb4", $c['user'], $c['senha']);
    $sql = file_get_contents(__DIR__ . '/sql/schema.sql');
}
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
foreach (array_filter(array_map('trim', explode(';', $sql))) as $cmd) $pdo->exec($cmd);
if ($c['driver'] !== 'sqlite') $pdo->exec('USE ' . $c['banco']);

$adminPass = getenv('SH_ADMIN_PASS') ?: 'studyhelp2026';
$ins = $pdo->prepare('INSERT INTO usuarios (nome,email,senha_hash,papel,status) VALUES (?,?,?,?,?)');
$existe = $pdo->prepare('SELECT 1 FROM usuarios WHERE email = ?');
$seed = [
  ['Administrador', 'admin@studyhelp.com', $adminPass, 'Administrador', 'Aprovado'],
  ['Lucas Oliveira', 'lucas.oliveira@email.com', 'senha123', 'Tutor', 'Aprovado'],
  ['Mariana Souza', 'mariana.souza@email.com', 'senha123', 'Tutor', 'Pendente'],
  ['Ana Fernanda', 'ana.fernanda@email.com', 'senha123', 'Aluno', 'Aprovado'],
];
foreach ($seed as [$n, $e, $p, $pa, $s]) {
    $existe->execute([$e]);
    if (!$existe->fetch()) $ins->execute([$n, $e, password_hash($p, PASSWORD_DEFAULT), $pa, $s]);
}
if (!$pdo->query('SELECT 1 FROM materias LIMIT 1')->fetch()) {
    $m = $pdo->prepare('INSERT INTO materias (nome) VALUES (?)');
    foreach (['Matemática','Física','Química','História','Biologia','Informática'] as $n) $m->execute([$n]);
}
echo "Banco pronto.\n";
