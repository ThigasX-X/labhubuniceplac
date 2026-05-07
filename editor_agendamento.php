<?php
session_start();

if (!isset($_SESSION['usuario_id']) || $_SESSION['perfil'] !== 'coordenador') {
    header("Location: index.php");
    exit;
}

require 'conexao.php';
require 'Agendamento.php';

$agendamento = new Agendamento($pdo);
$mensagem = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['id_agendamento'])) {
    $id = $_POST['id_agendamento'];
    $id_lab = $_POST['id_laboratorio'];
    $id_prof = $_POST['id_professor'];
    $id_disc = $_POST['id_disciplina'];
    $turno = $_POST['turno'];
    $periodo = $_POST['periodo']; // Capta o horário editado
    $data = $_POST['data_reserva'];

    try {
        $agendamento->atualizarReserva($id, $id_lab, $id_prof, $id_disc, $turno, $periodo, $data);
        header("Location: painel_coordenador.php");
        exit;
    } catch (PDOException $e) {
        $mensagem = '<div class="alert alert-danger">Erro ao atualizar: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}

if (!isset($_GET['id'])) {
    header("Location: painel_coordenador.php");
    exit;
}

$id_editar = $_GET['id'];
$reserva_atual = $agendamento->buscarReservaPorId($id_editar);

$laboratorios = $agendamento->buscarLaboratorios();
$professores = $agendamento->buscarProfessores();
$disciplinas = $agendamento->buscarDisciplinas();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Editar Reserva - UNICEPLAC</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root { --azul-uniceplac: #0A2444; --amarelo-uniceplac: #F1A000; }
        body { background-color: #f0f2f5; }
        .bg-uniceplac { background-color: var(--azul-uniceplac) !important; }
        .text-uniceplac { color: var(--azul-uniceplac) !important; }
        .btn-uniceplac { background-color: var(--azul-uniceplac); color: white; border: none; }
        .btn-uniceplac:hover { background-color: #051326; color: var(--amarelo-uniceplac); }
        .card-uniceplac { border: none; border-top: 4px solid var(--amarelo-uniceplac); }
    </style>
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                
                <?= $mensagem ?>

                <div class="card card-uniceplac shadow-sm">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-bold text-uniceplac">✏️ Editar Agendamento #<?= $reserva_atual['id'] ?></h5>
                        <a href="painel_coordenador.php" class="btn btn-sm btn-outline-secondary">Cancelar e Voltar</a>
                    </div>
                    <div class="card-body">
                        <form action="editor_agendamento.php?id=<?= $id_editar ?>" method="POST">
                            <input type="hidden" name="id_agendamento" value="<?= $reserva_atual['id'] ?>">
                            
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label class="form-label fw-bold">Data da Reserva:</label>
                                    <input type="date" class="form-control" name="data_reserva" value="<?= $reserva_atual['data_reserva'] ?>" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-bold">Turno:</label>
                                    <select class="form-select" name="turno" required>
                                        <option value="Manhã" <?= $reserva_atual['turno'] == 'Manhã' ? 'selected' : '' ?>>Manhã</option>
                                        <option value="Tarde" <?= $reserva_atual['turno'] == 'Tarde' ? 'selected' : '' ?>>Tarde</option>
                                        <option value="Noite" <?= $reserva_atual['turno'] == 'Noite' ? 'selected' : '' ?>>Noite</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-bold">Horário:</label>
                                    <select class="form-select" name="periodo" required>
                                        <option value="1º e 2º Horários" <?= $reserva_atual['periodo'] == '1º e 2º Horários' ? 'selected' : '' ?>>1º e 2º Horários</option>
                                        <option value="1º Horário" <?= $reserva_atual['periodo'] == '1º Horário' ? 'selected' : '' ?>>Apenas 1º Horário</option>
                                        <option value="2º Horário" <?= $reserva_atual['periodo'] == '2º Horário' ? 'selected' : '' ?>>Apenas 2º Horário</option>
                                    </select>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold">Laboratório:</label>
                                <select class="form-select" name="id_laboratorio" required>
                                    <?php foreach($laboratorios as $lab): ?>
                                        <option value="<?= $lab['id'] ?>" <?= $reserva_atual['id_laboratorio'] == $lab['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($lab['nome']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Professor:</label>
                                    <select class="form-select" name="id_professor" required>
                                        <?php foreach($professores as $prof): ?>
                                            <option value="<?= $prof['id'] ?>" <?= $reserva_atual['id_professor'] == $prof['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($prof['nome']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Disciplina:</label>
                                    <select class="form-select" name="id_disciplina" required>
                                        <?php foreach($disciplinas as $disc): ?>
                                            <option value="<?= $disc['id'] ?>" <?= $reserva_atual['id_disciplina'] == $disc['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($disc['nome']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-uniceplac w-100 py-2">Salvar Alterações</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>