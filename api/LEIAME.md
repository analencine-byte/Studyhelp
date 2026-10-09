# Backend StudyHelp (PHP + MySQL)

## Como rodar no XAMPP
1. Copie a pasta do projeto para `C:\xampp\htdocs\Studyhelp` e ligue **Apache** e **MySQL** no XAMPP.
2. Crie o banco e os dados iniciais: no terminal, dentro da pasta do projeto, `php api\install.php`
   (ou importe `api/sql/schema.sql` no phpMyAdmin).
3. Abra `http://localhost/Studyhelp/login.html` (precisa ser por `localhost`, não clicando no arquivo).

Contas de teste (senha `senha123`): lucas.oliveira@email.com (tutor aprovado), mariana.souza@email.com (tutor pendente), ana.fernanda@email.com (aluno).
Admin: admin@studyhelp.com / `studyhelp2026` (troque com a variável `SH_ADMIN_PASS` ao rodar o install).

## Endpoints
- `auth.php?acao=cadastro|login|logout|eu`
- `usuarios.php` (admin: listar, `acao=status`, `acao=excluir`; qualquer logado: `acao=perfil`)
- `materias.php` (listar público; criar/editar/excluir/alunos para tutor; inscrever/favoritar para aluno)
- `atividades.php` (admin)

Senhas com `password_hash`, sessão PHP, papel/status conferidos no servidor a cada requisição.
