<?php
/**
 * Popula o banco com dados de demonstração.
 * Uso: php backend/database/seed.php (depois do setup.php)
 */
require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/config/database.php';

$senhaHash = password_hash('12345678', PASSWORD_DEFAULT);

$usuarios = [
    ['Coordenador Demo', 'coordenador@uniceplac.edu.br', 'coordenador'],
    ['Suporte Demo',     'suporte@uniceplac.edu.br',     'suporte'],
    ['Professor Demo',   'professor@uniceplac.edu.br',   'professor'],
];

$stmt = $pdo->prepare(
    "INSERT INTO usuarios (nome, email, senha, perfil, email_verificado)
     VALUES (?, ?, ?, ?, 1)
     ON DUPLICATE KEY UPDATE nome = VALUES(nome), perfil = VALUES(perfil)"
);
foreach ($usuarios as [$nome, $email, $perfil]) {
    $stmt->execute([$nome, $email, $senhaHash, $perfil]);
    echo "✅ Usuário: $email (perfil: $perfil, senha: 12345678)\n";
}

$labs = [
    ['Lab Informática 01', 40, 'Bloco A', '1º Andar'],
    ['Lab Informática 02', 30, 'Bloco A', '2º Andar'],
    ['Lab Redes',          25, 'Bloco B', 'Térreo'],
];
$stmt = $pdo->prepare("INSERT IGNORE INTO laboratorios (nome, capacidade, localizacao, andar) VALUES (?, ?, ?, ?)");
foreach ($labs as $l) { $stmt->execute($l); }
echo "✅ Laboratórios: " . count($labs) . "\n";

foreach (['Algoritmos', 'Banco de Dados', 'Redes de Computadores'] as $d) {
    $pdo->prepare("INSERT IGNORE INTO disciplinas (nome) VALUES (?)")->execute([$d]);
}
foreach (['Análise e Des. de Sistemas', 'Ciência da Computação'] as $c) {
    $pdo->prepare("INSERT IGNORE INTO cursos (nome) VALUES (?)")->execute([$c]);
}
foreach (['1º Semestre', '2º Semestre', '3º Semestre'] as $s) {
    $pdo->prepare("INSERT IGNORE INTO semestres (nome) VALUES (?)")->execute([$s]);
}
foreach (['Bloco A', 'Bloco B'] as $b) {
    $pdo->prepare("INSERT IGNORE INTO blocos (nome) VALUES (?)")->execute([$b]);
}
echo "✅ Disciplinas, cursos, semestres e blocos criados.\n";

$blocos = $pdo->query("SELECT id, nome FROM blocos")->fetchAll();
foreach ($blocos as $b) {
    foreach (['Térreo', '1º Andar'] as $a) {
        $pdo->prepare("INSERT IGNORE INTO andares (id_bloco, nome) VALUES (?, ?)")->execute([$b['id'], $a]);
    }
}
$andares = $pdo->query("SELECT id FROM andares")->fetchAll();
foreach ($andares as $a) {
    foreach (['Sala 101', 'Sala 102'] as $s) {
        $pdo->prepare("INSERT IGNORE INTO salas (id_andar, nome) VALUES (?, ?)")->execute([$a['id'], $s]);
    }
}
echo "✅ Andares e salas criados.\n";

// Ensalamento de demonstração
$idProf = (int) $pdo->query("SELECT id FROM usuarios WHERE email='professor@uniceplac.edu.br'")->fetchColumn();
$idDisc = (int) $pdo->query("SELECT id FROM disciplinas WHERE nome='Algoritmos' LIMIT 1")->fetchColumn();
$idCurso = (int) $pdo->query("SELECT id FROM cursos LIMIT 1")->fetchColumn();
$idSem = (int) $pdo->query("SELECT id FROM semestres LIMIT 1")->fetchColumn();
$idSala = (int) $pdo->query("SELECT s.id FROM salas s JOIN andares a ON s.id_andar=a.id JOIN blocos b ON a.id_bloco=b.id WHERE b.nome='Bloco A' LIMIT 1")->fetchColumn();

if ($idProf && $idDisc && $idCurso && $idSem && $idSala) {
    $pdo->prepare(
        "INSERT IGNORE INTO ensalamento (id_professor, id_disciplina, id_curso, id_semestre, id_sala, categoria, turno)
         VALUES (?, ?, ?, ?, ?, 'Presencial', 'Matutino')"
    )->execute([$idProf, $idDisc, $idCurso, $idSem, $idSala]);
    echo "✅ Ensalamento demo criado.\n";
}

echo "\n🏁 Seed concluído.\n";
