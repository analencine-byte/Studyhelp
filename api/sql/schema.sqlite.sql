-- StudyHelp — esquema MySQL. Importe no phpMyAdmin ou rode: php api/install.php

CREATE TABLE IF NOT EXISTS usuarios (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  nome TEXT NOT NULL,
  email TEXT NOT NULL UNIQUE,
  senha_hash TEXT NOT NULL,
  papel TEXT NOT NULL DEFAULT 'Aluno',
  status TEXT NOT NULL DEFAULT 'Aprovado',
  avatar TEXT NULL,
  bio TEXT NULL,
  disponivel INTEGER NOT NULL DEFAULT 1,
  config TEXT NULL,
  notif_visto_em TEXT NULL,
  criado_em TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS materias (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  nome TEXT NOT NULL,
  descricao TEXT NULL,
  tutor_id INT NULL,
  criado_em TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (tutor_id) REFERENCES usuarios(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS inscricoes (
  aluno_id INT NOT NULL,
  materia_id INT NOT NULL,
  criado_em TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (aluno_id, materia_id),
  FOREIGN KEY (aluno_id) REFERENCES usuarios(id) ON DELETE CASCADE,
  FOREIGN KEY (materia_id) REFERENCES materias(id) ON DELETE CASCADE
);

-- favoritas guarda o NOME da matéria (a lista de favoritas é por nome: Matemática, História...)
CREATE TABLE IF NOT EXISTS favoritas (
  aluno_id INT NOT NULL,
  materia_nome TEXT NOT NULL,
  PRIMARY KEY (aluno_id, materia_nome),
  FOREIGN KEY (aluno_id) REFERENCES usuarios(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS atividades (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  texto TEXT NOT NULL,
  criado_em TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS materiais (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  materia_id INT NOT NULL,
  tutor_id INT NULL,
  titulo TEXT NOT NULL,
  descricao TEXT NULL,
  tipo TEXT NOT NULL DEFAULT 'PDF',
  arquivo_nome TEXT NULL,
  arquivo_path TEXT NULL,
  criado_em TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (materia_id) REFERENCES materias(id) ON DELETE CASCADE,
  FOREIGN KEY (tutor_id) REFERENCES usuarios(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS acessos_material (
  aluno_id INT NOT NULL,
  material_id INT NOT NULL,
  criado_em TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (aluno_id, material_id),
  FOREIGN KEY (aluno_id) REFERENCES usuarios(id) ON DELETE CASCADE,
  FOREIGN KEY (material_id) REFERENCES materiais(id) ON DELETE CASCADE
);

-- tarefas = "atividades" do aluno (dúvidas enviadas, exercícios, redações...)
CREATE TABLE IF NOT EXISTS tarefas (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  aluno_id INT NOT NULL,
  titulo TEXT NOT NULL,
  materia TEXT NULL,
  tipo TEXT NOT NULL DEFAULT 'duvida',
  status TEXT NOT NULL DEFAULT 'Em andamento',
  tutor_id INT NULL,
  nota TEXT NULL,
  criado_em TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (aluno_id) REFERENCES usuarios(id) ON DELETE CASCADE,
  FOREIGN KEY (tutor_id) REFERENCES usuarios(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS cadernos (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  aluno_id INT NOT NULL,
  titulo TEXT NOT NULL,
  descricao TEXT NULL,
  conteudo TEXT NULL,
  criado_em TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (aluno_id) REFERENCES usuarios(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS mensagens (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  de_id INT NOT NULL,
  para_id INT NOT NULL,
  texto TEXT NULL,
  arquivo_nome TEXT NULL,
  arquivo_path TEXT NULL,
  arquivo_tamanho INT NULL,
  lida INTEGER NOT NULL DEFAULT 0,
  criado_em TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (de_id) REFERENCES usuarios(id) ON DELETE CASCADE,
  FOREIGN KEY (para_id) REFERENCES usuarios(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS configuracoes (
  chave TEXT PRIMARY KEY,
  valor TEXT NOT NULL
);
