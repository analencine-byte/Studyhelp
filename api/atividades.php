<?php
// GET atividades.php  (admin) últimas atividades da plataforma
require __DIR__ . '/bootstrap.php';
exigir(['Administrador']);
responder(['atividades' => db()->query('SELECT id,texto,criado_em AS criadoEm FROM atividades ORDER BY id DESC LIMIT 30')->fetchAll()]);
