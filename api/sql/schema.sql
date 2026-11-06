-- StudyHelp — esquema MySQL. Importe no phpMyAdmin ou rode: php api/install.php
CREATE DATABASE IF NOT EXISTS studyhelp CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE studyhelp;

CREATE TABLE IF NOT EXISTS usuarios (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(120) NOT NULL,
  email VARCHAR(160) NOT NULL UNIQUE,
  senha_hash VARCHAR(255) NOT NULL,
  papel ENUM('Aluno','Tutor','Administrador') NOT NULL DEFAULT 'Aluno',
  status ENUM('Aprovado','Pendente','Ativo','Recusado') NOT NULL DEFAULT 'Aprovado',
  avatar MEDIUMTEXT NULL,
  bio VARCHAR(500) NULL,
  disponivel TINYINT NOT NULL DEFAULT 1,
  config TEXT NULL,
  notif_visto_em TIMESTAMP NULL,
  criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS materias (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(80) NOT NULL,
  descricao VARCHAR(255) NULL,
  tutor_id INT NULL,
  criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (tutor_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS inscricoes (
  aluno_id INT NOT NULL,
  materia_id INT NOT NULL,
  criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (aluno_id, materia_id),
  FOREIGN KEY (aluno_id) REFERENCES usuarios(id) ON DELETE CASCADE,
  FOREIGN KEY (materia_id) REFERENCES materias(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- favoritas guarda o NOME da matéria (a lista de favoritas é por nome: Matemática, História...)
CREATE TABLE IF NOT EXISTS favoritas (
  aluno_id INT NOT NULL,
  materia_nome VARCHAR(80) NOT NULL,
  PRIMARY KEY (aluno_id, materia_nome),
  FOREIGN KEY (aluno_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS atividades (
  id INT AUTO_INCREMENT PRIMARY KEY,
  texto VARCHAR(255) NOT NULL,
  criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS materiais (
  id INT AUTO_INCREMENT PRIMARY KEY,
  materia_id INT NOT NULL,
  tutor_id INT NULL,
  titulo VARCHAR(160) NOT NULL,
  descricao TEXT NULL,
  tipo VARCHAR(20) NOT NULL DEFAULT 'PDF',
  arquivo_nome VARCHAR(200) NULL,
  arquivo_path VARCHAR(120) NULL,
  criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (materia_id) REFERENCES materias(id) ON DELETE CASCADE,
  FOREIGN KEY (tutor_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS acessos_material (
  aluno_id INT NOT NULL,
  material_id INT NOT NULL,
  criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (aluno_id, material_id),
  FOREIGN KEY (aluno_id) REFERENCES usuarios(id) ON DELETE CASCADE,
  FOREIGN KEY (material_id) REFERENCES materiais(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- tarefas = "atividades" do aluno (dúvidas enviadas, exercícios, redações...)
CREATE TABLE IF NOT EXISTS tarefas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  aluno_id INT NOT NULL,
  titulo VARCHAR(200) NOT NULL,
  materia VARCHAR(80) NULL,
  tipo VARCHAR(20) NOT NULL DEFAULT 'duvida',
  status VARCHAR(20) NOT NULL DEFAULT 'Em andamento',
  tutor_id INT NULL,
  nota VARCHAR(10) NULL,
  criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (aluno_id) REFERENCES usuarios(id) ON DELETE CASCADE,
  FOREIGN KEY (tutor_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS cadernos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  aluno_id INT NOT NULL,
  titulo VARCHAR(120) NOT NULL,
  descricao VARCHAR(200) NULL,
  conteudo MEDIUMTEXT NULL,
  criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (aluno_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS mensagens (
  id INT AUTO_INCREMENT PRIMARY KEY,
  de_id INT NOT NULL,
  para_id INT NOT NULL,
  texto TEXT NULL,
  arquivo_nome VARCHAR(200) NULL,
  arquivo_path VARCHAR(120) NULL,
  arquivo_tamanho INT NULL,
  lida TINYINT NOT NULL DEFAULT 0,
  criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (de_id) REFERENCES usuarios(id) ON DELETE CASCADE,
  FOREIGN KEY (para_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS configuracoes (
  chave VARCHAR(60) PRIMARY KEY,
  valor VARCHAR(255) NOT NULL
) ENGINE=InnoDB;
