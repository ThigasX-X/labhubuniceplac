<?php
require_once BACKEND_PATH . '/models/ChamadoSuporte.php';

class ApiController extends BaseController
{
    public function checkSos(): void
    {
        $this->guardPerfil(['suporte', 'coordenador']);
        $model = new ChamadoSuporte($this->pdo);
        $this->json(['qtd' => $model->countPendentes()]);
    }

    public function checkSosStatus(): void
    {
        if (!isset($_SESSION['usuario_id']) ||
            !in_array($_SESSION['perfil'], ['suporte', 'coordenador'])) {
            $this->json(['qtd_suporte' => 0, 'html_suporte' => '']);
        }

        $model   = new ChamadoSuporte($this->pdo);
        $chamados = $model->pendentes();
        $qtd     = count($chamados);
        $html    = '';

        foreach ($chamados as $c) {
            $dataF = date('d/m H:i', strtotime($c['data_hora']));
            $lab   = htmlspecialchars($c['laboratorio']);
            $prof  = htmlspecialchars($c['professor_nome']);
            $msg   = htmlspecialchars($c['mensagem']);
            $id    = $c['id'];

            $html .= "
            <div class='alert shadow-sm mb-4' style='border-radius: 20px; border: 1px solid rgba(220,53,69,.3); background: rgba(220,53,69,.08);'>
                <div class='d-flex flex-column flex-md-row justify-content-between align-items-md-center'>
                    <div class='mb-3 mb-md-0'>
                        <h5 class='fw-bold text-danger mb-1'><i class='bi bi-exclamation-triangle-fill me-2'></i> CHAMADO SOS: {$lab}</h5>
                        <p class='mb-1 fw-bold text-dark' style='font-size:.95rem;'>
                            <i class='bi bi-person-badge text-secondary me-1'></i> Prof(a). {$prof}
                            <span class='text-muted fw-normal ms-2 small'><i class='bi bi-clock me-1'></i>{$dataF}</span>
                        </p>
                        <div class='bg-white p-3 mt-2 rounded-4 border shadow-sm'>
                            <p class='mb-0 text-dark small'><strong><i class='bi bi-chat-left-text me-2 text-danger'></i>Relato:</strong> {$msg}</p>
                        </div>
                    </div>
                    <form method='POST' action='/index.php?page=suporte' class='text-end ms-md-3'>
                        <input type='hidden' name='id_chamado' value='{$id}'>
                        <button type='submit' name='resolver_chamado' class='btn btn-danger fw-bold rounded-pill shadow px-4 py-2'>
                            <i class='bi bi-check2-circle me-2'></i> Resolver SOS
                        </button>
                    </form>
                </div>
            </div>";
        }

        $this->json(['qtd_suporte' => $qtd, 'html_suporte' => $html]);
    }

    public function andaresPorBloco(): void
    {
        $this->guardCoordenador();
        $idBloco = (int) ($_GET['id_bloco'] ?? 0);
        if ($idBloco <= 0) { $this->json([]); }
        $stmt = $this->pdo->prepare("SELECT id, nome FROM andares WHERE id_bloco = ? ORDER BY nome");
        $stmt->execute([$idBloco]);
        $this->json($stmt->fetchAll());
    }

    public function salasPorAndar(): void
    {
        $this->guardCoordenador();
        $idAndar = (int) ($_GET['id_andar'] ?? 0);
        if ($idAndar <= 0) { $this->json([]); }
        $stmt = $this->pdo->prepare("SELECT id, nome FROM salas WHERE id_andar = ? ORDER BY nome");
        $stmt->execute([$idAndar]);
        $this->json($stmt->fetchAll());
    }

    private function guardCoordenador(): void
    {
        $this->guardPerfil(['coordenador']);
    }

    private function guardPerfil(array $perfis): void
    {
        if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['perfil'], $perfis, true)) {
            http_response_code(403);
            $this->json([]);
        }
    }
}
