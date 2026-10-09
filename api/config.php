<?php
// Configuração do banco. Padrão: XAMPP (MySQL local, usuário root sem senha).
// Pode ser sobrescrita por variáveis de ambiente (SH_DB_DRIVER, SH_DB_HOST...).
return [
    'driver' => getenv('SH_DB_DRIVER') ?: 'mysql',      // 'mysql' ou 'sqlite' (só pra testes)
    'host'   => getenv('SH_DB_HOST')   ?: '127.0.0.1',
    'porta'  => getenv('SH_DB_PORT')   ?: '3306',
    'banco'  => getenv('SH_DB_NAME')   ?: 'studyhelp',
    'user'   => getenv('SH_DB_USER')   ?: 'root',
    'senha'  => getenv('SH_DB_PASS')   ?: '',
    'sqlite' => getenv('SH_DB_FILE')   ?: __DIR__ . '/studyhelp.sqlite',
];
