<?php

interface ControleChaveDAO
{
    public function registrarRetirada(
        int    $idAgendamento,
        string $professorNome,
        string $laboratorio,
        string $celular,
        string $horaDevolucaoPrevista,
        string $funcionarioEntrega
    ): void;

    public function darBaixa(int $idChave, string $funcionarioRecebimento, string $horaDevolucaoReal): void;

    public function chavesPorProfessor(string $professorNome): array;

    public function emUso(): array;

    public function historico(): array;
}