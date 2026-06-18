<?php
/**
 * Popula um quadro de horários completo para validar o painel de Relatórios BI
 * do coordenador (KPIs, gráficos e tabelas).
 *
 * Idempotente: recria do zero o quadro "Grade Demonstração BI" a cada execução.
 * Uso: php backend/database/seed_bi.php   (depois de setup.php + seed.php)
 */
require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/config/database.php';

/* -------------------------------------------------------------------------
 * 1) Garante cadastros de apoio (professores, cursos, disciplinas, labs).
 * ---------------------------------------------------------------------- */
$senhaHash = password_hash('12345678', PASSWORD_DEFAULT);

$professores = [
    ['Professor Demo', 'professor@uniceplac.edu.br'],
    ['Ana Silva',      'ana.silva@uniceplac.edu.br'],
    ['Carlos Mendes',  'carlos.mendes@uniceplac.edu.br'],
    ['Fernanda Lima',  'fernanda.lima@uniceplac.edu.br'],
    ['Roberto Souza',  'roberto.souza@uniceplac.edu.br'],
];
$stmtProf = $pdo->prepare(
    "INSERT INTO usuarios (nome, email, senha, perfil, email_verificado)
     VALUES (?, ?, ?, 'professor', 1)
     ON DUPLICATE KEY UPDATE nome = VALUES(nome), perfil = 'professor'"
);
foreach ($professores as [$nome, $email]) {
    $stmtProf->execute([$nome, $email, $senhaHash]);
}

$cursos = ['Análise e Des. de Sistemas', 'Ciência da Computação', 'Engenharia de Software', 'Sistemas de Informação'];
foreach ($cursos as $c) {
    $pdo->prepare("INSERT IGNORE INTO cursos (nome) VALUES (?)")->execute([$c]);
}

$disciplinas = ['Algoritmos', 'Banco de Dados', 'Redes de Computadores', 'Engenharia de Software', 'Estrutura de Dados', 'Sistemas Operacionais'];
foreach ($disciplinas as $d) {
    $pdo->prepare("INSERT IGNORE INTO disciplinas (nome) VALUES (?)")->execute([$d]);
}

foreach (['1º Semestre', '2º Semestre', '3º Semestre', '4º Semestre'] as $s) {
    $pdo->prepare("INSERT IGNORE INTO semestres (nome) VALUES (?)")->execute([$s]);
}

$labsDemo = [
    ['Lab Informática 01', 40, 'Bloco A', '1º Andar'],
    ['Lab Informática 02', 30, 'Bloco A', '2º Andar'],
    ['Lab Redes',          25, 'Bloco B', 'Térreo'],
    ['Lab Multimídia',     35, 'Bloco B', '1º Andar'],
];
// laboratorios.nome não tem UNIQUE, então INSERT IGNORE duplicaria. Checa por nome.
$checkLab = $pdo->prepare("SELECT 1 FROM laboratorios WHERE nome = ? LIMIT 1");
$insLab   = $pdo->prepare("INSERT INTO laboratorios (nome, capacidade, localizacao, andar) VALUES (?, ?, ?, ?)");
foreach ($labsDemo as $l) {
    $checkLab->execute([$l[0]]);
    if (!$checkLab->fetchColumn()) {
        $insLab->execute($l);
    }
}
echo "✅ Cadastros de apoio garantidos (professores, cursos, disciplinas, semestres, labs).\n";

/* -------------------------------------------------------------------------
 * 2) Mapeia nome -> id para resolver as referências das aulas.
 * ---------------------------------------------------------------------- */
$mapa = function (string $tabela, string $coluna = 'nome') use ($pdo): array {
    $out = [];
    foreach ($pdo->query("SELECT id, $coluna AS chave FROM $tabela")->fetchAll() as $r) {
        $out[$r['chave']] = (int) $r['id'];
    }
    return $out;
};
$profPorEmail = [];
foreach ($pdo->query("SELECT id, email FROM usuarios WHERE perfil='professor'")->fetchAll() as $r) {
    $profPorEmail[$r['email']] = (int) $r['id'];
}
$cursoId = $mapa('cursos');
$discId  = $mapa('disciplinas');
$labId   = $mapa('laboratorios');
$semId   = $mapa('semestres');
$semList = array_values($semId);

/* -------------------------------------------------------------------------
 * 3) Recria o quadro de demonstração (idempotente).
 * ---------------------------------------------------------------------- */
$nomeQuadro = 'Grade Demonstração BI';
$pdo->prepare("DELETE FROM quadros_horarios WHERE nome = ?")->execute([$nomeQuadro]); // aulas caem por FK ON DELETE CASCADE
$pdo->prepare("INSERT INTO quadros_horarios (nome, periodo_letivo) VALUES (?, '2026.1')")->execute([$nomeQuadro]);
$idQuadro = (int) $pdo->lastInsertId();
echo "✅ Quadro recriado: \"$nomeQuadro\" (id $idQuadro).\n";

/* -------------------------------------------------------------------------
 * 4) Aulas curadas para gerar distribuições visíveis no BI:
 *    - Lab Informática 01 ~45h (mais usado)  |  Lab Multimídia 0h (mais ocioso)
 *    - 5 professores e 4 cursos com cargas distintas
 *    Colunas: [profEmail, disciplina, curso, lab, dia, turno, carga, horasLab]
 * ---------------------------------------------------------------------- */
$P = fn($e) => 'professor' === $e ? 'professor@uniceplac.edu.br' : "$e@uniceplac.edu.br";
$aulas = [
    // ---- Lab Informática 01 (soma horasLab = 45) ----
    ['ana.silva',      'Algoritmos',            'Análise e Des. de Sistemas', 'Lab Informática 01', 'Segunda', 'Matutino',   4, 4],
    ['carlos.mendes',  'Estrutura de Dados',    'Ciência da Computação',      'Lab Informática 01', 'Segunda', 'Noturno',    4, 3],
    ['fernanda.lima',  'Banco de Dados',        'Análise e Des. de Sistemas', 'Lab Informática 01', 'Terça',   'Vespertino', 4, 4],
    ['professor',      'Sistemas Operacionais', 'Ciência da Computação',      'Lab Informática 01', 'Quarta',  'Noturno',    2, 2],
    ['roberto.souza',  'Redes de Computadores', 'Engenharia de Software',     'Lab Informática 01', 'Quinta',  'Matutino',   4, 3],
    ['ana.silva',      'Engenharia de Software','Sistemas de Informação',     'Lab Informática 01', 'Sexta',   'Noturno',    4, 4],
    ['carlos.mendes',  'Algoritmos',            'Ciência da Computação',      'Lab Informática 01', 'Sábado',  'Matutino',   4, 4],
    ['fernanda.lima',  'Banco de Dados',        'Análise e Des. de Sistemas', 'Lab Informática 01', 'Quarta',  'Matutino',   4, 4],
    ['professor',      'Estrutura de Dados',    'Análise e Des. de Sistemas', 'Lab Informática 01', 'Terça',   'Noturno',    4, 3],
    ['roberto.souza',  'Sistemas Operacionais', 'Engenharia de Software',     'Lab Informática 01', 'Sexta',   'Vespertino', 4, 4],
    ['ana.silva',      'Redes de Computadores', 'Sistemas de Informação',     'Lab Informática 01', 'Quinta',  'Noturno',    4, 4],
    ['carlos.mendes',  'Banco de Dados',        'Ciência da Computação',      'Lab Informática 01', 'Segunda', 'Vespertino', 3, 3],
    ['fernanda.lima',  'Algoritmos',            'Análise e Des. de Sistemas', 'Lab Informática 01', 'Sábado',  'Vespertino', 3, 3],

    // ---- Lab Informática 02 (soma horasLab = 30) ----
    ['ana.silva',      'Banco de Dados',        'Análise e Des. de Sistemas', 'Lab Informática 02', 'Segunda', 'Noturno',    4, 4],
    ['carlos.mendes',  'Redes de Computadores', 'Ciência da Computação',      'Lab Informática 02', 'Terça',   'Matutino',   4, 3],
    ['fernanda.lima',  'Estrutura de Dados',    'Sistemas de Informação',     'Lab Informática 02', 'Quarta',  'Vespertino', 4, 4],
    ['professor',      'Algoritmos',            'Análise e Des. de Sistemas', 'Lab Informática 02', 'Quinta',  'Noturno',    3, 3],
    ['roberto.souza',  'Engenharia de Software','Engenharia de Software',     'Lab Informática 02', 'Sexta',   'Matutino',   4, 4],
    ['ana.silva',      'Sistemas Operacionais', 'Ciência da Computação',      'Lab Informática 02', 'Terça',   'Noturno',    4, 4],
    ['carlos.mendes',  'Banco de Dados',        'Sistemas de Informação',     'Lab Informática 02', 'Quarta',  'Noturno',    4, 4],
    ['fernanda.lima',  'Redes de Computadores', 'Análise e Des. de Sistemas', 'Lab Informática 02', 'Sexta',   'Vespertino', 4, 4],

    // ---- Lab Redes (soma horasLab = 15) ----
    ['roberto.souza',  'Redes de Computadores', 'Engenharia de Software',     'Lab Redes', 'Segunda', 'Matutino',   4, 4],
    ['professor',      'Redes de Computadores', 'Ciência da Computação',      'Lab Redes', 'Quarta',  'Vespertino', 4, 3],
    ['ana.silva',      'Sistemas Operacionais', 'Sistemas de Informação',     'Lab Redes', 'Quinta',  'Noturno',    4, 4],
    ['carlos.mendes',  'Redes de Computadores', 'Análise e Des. de Sistemas', 'Lab Redes', 'Sexta',   'Noturno',    4, 4],

    // ---- Lab Multimídia: sem aulas de propósito (será o "mais ocioso") ----
];

$insert = $pdo->prepare(
    "INSERT INTO quadro_aulas
        (id_quadro, id_disciplina, id_professor, id_laboratorio, id_curso, id_semestre,
         turno, dia_semana, modalidade, numero_alunos, horario, carga_horaria_total, horas_laboratorio)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Presencial', ?, '1º e 2º Horários', ?, ?)"
);

$inseridas = 0;
$ignoradas = 0;
foreach ($aulas as $i => [$profEmail, $disc, $curso, $lab, $dia, $turno, $carga, $horasLab]) {
    $idProf  = $profPorEmail[$P($profEmail)] ?? null;
    $idDisc  = $discId[$disc]  ?? null;
    $idCurso = $cursoId[$curso] ?? null;
    $idLab   = $labId[$lab]    ?? null;
    $idSem   = $semList[$i % count($semList)] ?? null; // distribui entre os semestres

    if (!$idProf || !$idDisc || !$idCurso || !$idLab || !$idSem) {
        $ignoradas++;
        continue;
    }
    $insert->execute([
        $idQuadro, $idDisc, $idProf, $idLab, $idCurso, $idSem,
        $turno, $dia, 25 + ($i % 16), $carga, $horasLab,
    ]);
    $inseridas++;
}

echo "✅ Aulas inseridas: $inseridas" . ($ignoradas ? " (ignoradas por referência ausente: $ignoradas)" : "") . "\n";
echo "\n🏁 Seed de BI concluído. Abra o coordenador → Relatórios BI (quadro \"$nomeQuadro\").\n";
