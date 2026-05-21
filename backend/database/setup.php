<?php
$host = '127.0.0.1';
$usuario = 'root';
$senha = '';

try {
    $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $usuario, $senha);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $pdo->exec("CREATE DATABASE IF NOT EXISTS sistema_labs CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE sistema_labs");

    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

    foreach ([
        'quadro_aulas', 'quadros_horarios',
        'controle_chaves', 'agendamentos',
        'ensalamento',
        'chamados_suporte',
        'salas', 'andares', 'blocos',
        'cursos', 'semestres',
        'disciplinas', 'laboratorios',
        'usuarios',
    ] as $t) {
        $pdo->exec("DROP TABLE IF EXISTS $t");
    }

    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

    $pdo->exec("CREATE TABLE usuarios (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nome VARCHAR(150) NOT NULL,
        email VARCHAR(150) NOT NULL UNIQUE,
        senha VARCHAR(255) NOT NULL,
        perfil VARCHAR(50) DEFAULT 'professor',
        email_verificado TINYINT(1) DEFAULT 0,
        foto_perfil VARCHAR(255) DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE laboratorios (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nome VARCHAR(150) NOT NULL,
        capacidade INT NOT NULL DEFAULT 0,
        localizacao VARCHAR(150) DEFAULT NULL,
        andar VARCHAR(50) DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE disciplinas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nome VARCHAR(150) NOT NULL UNIQUE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE cursos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nome VARCHAR(150) NOT NULL UNIQUE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE semestres (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nome VARCHAR(50) NOT NULL UNIQUE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE blocos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nome VARCHAR(50) NOT NULL UNIQUE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE andares (
        id INT AUTO_INCREMENT PRIMARY KEY,
        id_bloco INT NOT NULL,
        nome VARCHAR(50) NOT NULL,
        CONSTRAINT fk_andar_bloco FOREIGN KEY (id_bloco) REFERENCES blocos(id) ON DELETE RESTRICT,
        UNIQUE KEY uq_andar_bloco_nome (id_bloco, nome)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE salas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        id_andar INT NOT NULL,
        nome VARCHAR(50) NOT NULL,
        CONSTRAINT fk_sala_andar FOREIGN KEY (id_andar) REFERENCES andares(id) ON DELETE RESTRICT,
        UNIQUE KEY uq_sala_andar_nome (id_andar, nome)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE agendamentos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        id_laboratorio INT NOT NULL,
        id_professor INT NOT NULL,
        id_disciplina INT NOT NULL,
        turno ENUM('Matutino','Vespertino','Noturno') NOT NULL,
        periodo VARCHAR(50) NOT NULL,
        data_reserva DATE NOT NULL,
        status ENUM('pendente','aprovado','rejeitado','cancelado') NOT NULL DEFAULT 'pendente',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        CONSTRAINT fk_agend_lab  FOREIGN KEY (id_laboratorio) REFERENCES laboratorios(id) ON DELETE RESTRICT,
        CONSTRAINT fk_agend_prof FOREIGN KEY (id_professor)   REFERENCES usuarios(id)     ON DELETE RESTRICT,
        CONSTRAINT fk_agend_disc FOREIGN KEY (id_disciplina)  REFERENCES disciplinas(id)  ON DELETE RESTRICT,
        UNIQUE KEY uq_agend (id_laboratorio, data_reserva, turno, periodo),
        INDEX idx_agend_data (data_reserva),
        INDEX idx_agend_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE chamados_suporte (
        id INT AUTO_INCREMENT PRIMARY KEY,
        id_professor INT NOT NULL,
        professor_nome VARCHAR(150) NOT NULL,
        laboratorio VARCHAR(150) NOT NULL,
        mensagem TEXT NOT NULL,
        status ENUM('pendente','resolvido') NOT NULL DEFAULT 'pendente',
        data_hora DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT fk_chamado_prof FOREIGN KEY (id_professor) REFERENCES usuarios(id) ON DELETE CASCADE,
        INDEX idx_chamado_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE controle_chaves (
        id INT AUTO_INCREMENT PRIMARY KEY,
        id_agendamento INT NOT NULL,
        professor_nome VARCHAR(150) NOT NULL,
        laboratorio VARCHAR(150) NOT NULL,
        data_uso DATE NOT NULL,
        celular VARCHAR(20) DEFAULT NULL,
        hora_retirada TIME NOT NULL,
        hora_devolucao_prevista TIME DEFAULT NULL,
        funcionario_entrega VARCHAR(150) DEFAULT NULL,
        funcionario_recebimento VARCHAR(150) DEFAULT NULL,
        hora_devolucao_real TIME DEFAULT NULL,
        status ENUM('em_uso','devolvido') NOT NULL DEFAULT 'em_uso',
        CONSTRAINT fk_chave_agend FOREIGN KEY (id_agendamento) REFERENCES agendamentos(id) ON DELETE CASCADE,
        INDEX idx_chave_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE quadros_horarios (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nome VARCHAR(150) NOT NULL,
        periodo_letivo VARCHAR(50) NOT NULL,
        data_criacao DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE ensalamento (
        id INT AUTO_INCREMENT PRIMARY KEY,
        id_professor INT NOT NULL,
        id_disciplina INT NOT NULL,
        id_curso INT NOT NULL,
        id_sala INT NOT NULL,
        categoria VARCHAR(50) DEFAULT NULL,
        turno ENUM('Matutino','Vespertino','Noturno') NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        CONSTRAINT fk_ensal_prof  FOREIGN KEY (id_professor)  REFERENCES usuarios(id)    ON DELETE CASCADE,
        CONSTRAINT fk_ensal_disc  FOREIGN KEY (id_disciplina) REFERENCES disciplinas(id) ON DELETE RESTRICT,
        CONSTRAINT fk_ensal_curso FOREIGN KEY (id_curso)      REFERENCES cursos(id)      ON DELETE RESTRICT,
        CONSTRAINT fk_ensal_sala  FOREIGN KEY (id_sala)       REFERENCES salas(id)       ON DELETE RESTRICT,
        UNIQUE KEY uq_ensal_sala_turno (id_sala, turno),
        INDEX idx_ensal_prof (id_professor)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE quadro_aulas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        id_quadro INT NOT NULL,
        id_disciplina INT NOT NULL,
        id_professor INT DEFAULT NULL,
        id_laboratorio INT DEFAULT NULL,
        id_curso INT NOT NULL,
        id_semestre INT NOT NULL,
        id_sala INT DEFAULT NULL,
        turno ENUM('Matutino','Vespertino','Noturno') NOT NULL,
        dia_semana VARCHAR(20) NOT NULL,
        modalidade VARCHAR(50) DEFAULT NULL,
        numero_alunos INT DEFAULT 0,
        horario VARCHAR(50) DEFAULT NULL,
        carga_horaria_total INT DEFAULT 0,
        horas_laboratorio INT DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        CONSTRAINT fk_qaula_quadro FOREIGN KEY (id_quadro)      REFERENCES quadros_horarios(id) ON DELETE CASCADE,
        CONSTRAINT fk_qaula_disc   FOREIGN KEY (id_disciplina)  REFERENCES disciplinas(id)     ON DELETE RESTRICT,
        CONSTRAINT fk_qaula_prof   FOREIGN KEY (id_professor)   REFERENCES usuarios(id)        ON DELETE SET NULL,
        CONSTRAINT fk_qaula_lab    FOREIGN KEY (id_laboratorio) REFERENCES laboratorios(id)    ON DELETE SET NULL,
        CONSTRAINT fk_qaula_curso  FOREIGN KEY (id_curso)       REFERENCES cursos(id)          ON DELETE RESTRICT,
        CONSTRAINT fk_qaula_sem    FOREIGN KEY (id_semestre)    REFERENCES semestres(id)       ON DELETE RESTRICT,
        CONSTRAINT fk_qaula_sala   FOREIGN KEY (id_sala)        REFERENCES salas(id)           ON DELETE SET NULL,
        INDEX idx_qaula_quadro (id_quadro)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    echo "✅ SUCESSO! Banco 'sistema_labs' recriado.\n";
    foreach ($pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN) as $t) {
        echo "   • $t\n";
    }

} catch (PDOException $e) {
    echo "❌ ERRO: " . $e->getMessage() . "\n";
}
