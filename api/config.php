<?php
// Configuração do banco. Padrão: XAMPP (MySQL local, usuário root sem senha).
// Em hospedagem (ex.: InfinityFree), crie o arquivo api/config.local.php com os dados do
// banco do painel — ele sobrescreve os valores abaixo e não vai pro GitHub. Exemplo:
//   <?php return ['host'=>'sql123.infinityfree.com','banco'=>'if0_123_studyhelp','user'=>'if0_123','senha'=>'SENHA','chave_instalacao'=>'uma-frase-secreta'];
$padrao = [
    'driver' => getenv('SH_DB_DRIVER') ?: 'mysql',      // 'mysql' ou 'sqlite' (só pra testes)
    'host'   => getenv('SH_DB_HOST')   ?: '127.0.0.1',
    'porta'  => getenv('SH_DB_PORT')   ?: '3306',
    'banco'  => getenv('SH_DB_NAME')   ?: 'studyhelp',
    'user'   => getenv('SH_DB_USER')   ?: 'root',
    'senha'  => getenv('SH_DB_PASS')   ?: '',
    'sqlite' => getenv('SH_DB_FILE')   ?: __DIR__ . '/studyhelp.sqlite',
    'chave_instalacao' => '',                           // só usada pra rodar install.php pelo navegador
];
$local = __DIR__ . '/config.local.php';
return is_file($local) ? array_merge($padrao, (array)require $local) : $padrao;
