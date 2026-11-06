# Backend StudyHelp (PHP + MySQL)

Todo o site agora conversa com este backend: cadastro/login, painel do aluno, do professor e do admin,
materiais (com upload de PDF/vídeo), chat, cadernos, favoritas, atividades, notificações e configurações.

## Como rodar no XAMPP
1. Copie a pasta do projeto para `C:\xampp\htdocs\Studyhelp` e ligue **Apache** e **MySQL** no XAMPP.
2. Crie o banco e os dados de exemplo — no terminal, dentro da pasta do projeto:
   `C:\xampp\php\php.exe api\install.php`
   (ou importe `api/sql/schema.sql` no phpMyAdmin e depois rode o comando acima).
3. Abra `http://localhost/Studyhelp/index.html` (precisa ser por `localhost`, não clicando no arquivo).

Se o MySQL tiver senha, ajuste `api/config.php` (ou use as variáveis `SH_DB_USER`, `SH_DB_PASS`...).

## Contas de exemplo
- Admin: `admin@studyhelp.com` / `studyhelp2026` (entra por "Acesso administrativo" no rodapé do login).
  Para trocar a senha na instalação: `set SH_ADMIN_PASS=suaSenha` antes de rodar o install.
- Tutor aprovado: `lucas.oliveira@email.com` / `senha123` (também juliana.santos, carla.menezes, rodrigo.alves, fernanda.lopes)
- Tutor pendente: `mariana.souza@email.com` / `senha123`
- Aluno: `ana.fernanda@email.com` / `senha123`
- Instalar sem dados de exemplo (só o admin): `set SH_SEED_DEMO=0`

## Rotas (pasta api/)
| Arquivo | O que faz |
|---|---|
| `auth.php` | cadastro, login, logout, `eu` (quem está logado) |
| `usuarios.php` | admin: listar/aprovar/recusar/excluir · público: tutores, avatar · logado: perfil, estatísticas |
| `materias.php` | listar (público), criar/editar/excluir (tutor/admin), atribuir a tutor (admin), inscrever, favoritas |
| `materiais.php` | listar (público), ver/baixar (logado), publicar com arquivo e excluir (tutor) |
| `tarefas.php` | atividades do aluno e envio de dúvidas (a dúvida vai pro chat do tutor da matéria) |
| `cadernos.php` | cadernos do aluno (cada um só vê os seus) |
| `chat.php` | conversas aluno↔tutor e suporte; anexos; só quem participa lê |
| `notificacoes.php` | notificações (mensagens novas, materiais novos, inscrições, solicitações) |
| `configuracoes.php` | configurações da plataforma (admin) |
| `atividades.php` | histórico de atividades da plataforma (admin) |

## Segurança (resumo)
- Senhas com `password_hash`; sessão PHP; papel e status conferidos no servidor a cada requisição.
- Ninguém vira administrador pelo cadastro; tutor novo fica "Pendente" até o admin aprovar (configurável).
- Trocar email/senha exige a senha atual. Mensagens de login não revelam se o email existe.
- Uploads: só extensões permitidas, nome aleatório, pasta `api/uploads` bloqueada por `.htaccess`;
  os arquivos só saem por `materiais.php`/`chat.php`, depois de checar login e permissão.
- Todo texto vindo do banco é escapado antes de ir pra tela (contra injeção de HTML).
- Limite de upload vem do `php.ini` (`upload_max_filesize` / `post_max_size`; no XAMPP o padrão é 40 MB).

## Em hospedagem (InfinityFree)
1. Crie o banco MySQL no painel e anote host, nome, usuário e senha.
2. Envie todos os arquivos para a pasta `htdocs` do site.
3. Copie `api/config.local.php.exemplo` para `api/config.local.php` e preencha.
4. Abra `https://SEUSITE/api/install.php?chave=SUA_CHAVE` → deve aparecer "Banco pronto".
5. **Apague `api/install.php` do servidor** e entre pelo site.
