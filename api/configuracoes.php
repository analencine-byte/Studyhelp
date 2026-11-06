<?php
// Configurações da plataforma (só o administrador lê e altera).
// GET  configuracoes.php
// POST configuracoes.php   {nome_plataforma, aprovar_tutores_manualmente, notificar_cadastro}
require __DIR__ . '/bootstrap.php';
exigir(['Administrador']);
const PADRAO = ['nome_plataforma' => 'StudyHelp', 'aprovar_tutores_manualmente' => '1', 'notificar_cadastro' => '1'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $d = entrada();
    $up = db()->prepare(db()->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite'
        ? 'INSERT OR REPLACE INTO configuracoes (chave,valor) VALUES (?,?)'
        : 'REPLACE INTO configuracoes (chave,valor) VALUES (?,?)');
    foreach (PADRAO as $k => $_) {
        if (!array_key_exists($k, $d)) continue;
        $v = $k === 'nome_plataforma' ? mb_substr(trim((string)$d[$k]), 0, 60) : ($d[$k] ? '1' : '0');
        if ($k === 'nome_plataforma' && $v === '') erro('Informe o nome da plataforma.');
        $up->execute([$k, $v]);
    }
    registrarAtividade('Configurações da plataforma foram atualizadas');
}
$r = [];
foreach (PADRAO as $k => $p) $r[$k] = configuracao($k, $p);
responder(['configuracoes' => $r]);
