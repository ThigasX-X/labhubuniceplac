<?php

interface AgendamentoDAO
{
    public function buscarLaboratorios(): array;
    public function buscarProfessores(): array;
    public function buscarDisciplinas(): array;
    public function criarReserva(int $idLab, int $idProf, int $idDisc, string $turno, string $periodo, string $data): bool;
    public function solicitarReserva(int $idLab, int $idProf, int $idDisc, string $turno, string $periodo, string $data): bool;
    public function listarAlocacoesDoDia(string $data): array;
    public function listarAlocacoesProfessor(int $idProfessor): array;
    public function listarSolicitacoesPendentes(): array;
    public function listarReservasConfirmadas(): array;
    public function listarHistoricoCompleto(): array;
    public function buscarPorId(int $id): ?array;
    public function atualizarStatus(int $id, string $status): bool;
    public function atualizar(int $id, int $idLab, int $idProf, int $idDisc, string $turno, string $periodo, string $data): bool;
    public function excluir(int $id): bool;
}