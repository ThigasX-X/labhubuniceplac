<?php
/**
 * Migrações incrementais (seguro rodar várias vezes).
 * Uso: php backend/database/migrate.php
 */
require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/config/database.php';

$migracoes = [
    "ALTER TABLE ensalamento ADD COLUMN id_semestre INT NULL AFTER id_curso",
    "ALTER TABLE ensalamento ADD CONSTRAINT fk_ensal_sem FOREIGN KEY (id_semestre) REFERENCES semestres(id) ON DELETE SET NULL",
];

foreach ($migracoes as $sql) {
    try {
        $pdo->exec($sql);
        echo "✅ " . substr($sql, 0, 60) . "...\n";
    } catch (PDOException $e) {
        if (str_contains($e->getMessage(), 'Duplicate column')
            || str_contains($e->getMessage(), 'Duplicate key name')
            || str_contains($e->getMessage(), 'already exists')) {
            echo "⏭  Já aplicado: " . substr($sql, 0, 50) . "...\n";
        } else {
            echo "⚠️  " . $e->getMessage() . "\n";
        }
    }
}

echo "🏁 Migrações concluídas.\n";
