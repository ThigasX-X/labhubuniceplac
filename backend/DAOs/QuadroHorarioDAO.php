<?php

interface QuadroHorarioDAO
{
    public function all(): array;
    public function idAtivo(): int|false;
    public function criar(string $nome, string $periodoLetivo): void;
    public function excluir(int $id): void;
    public function duplicar(int $idOrigem, string $novoNome, string $novoPeriodo): void;
    public function aulasDoQuadro(int $idQuadro): array;
    public function salvarAula(array $dados): void;
    public function editarAula(int $idAula, array $dados): void;
    public function excluirAula(int $idAula): void;
    public function moverAula(int $idAula, string $novoDia): void;
}