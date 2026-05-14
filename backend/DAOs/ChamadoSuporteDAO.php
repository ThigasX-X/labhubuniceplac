<?php

interface ChamadoSuporteDAO
{
    public function abrir(int $idProfessor, string $professorNome, string $laboratorio, string $mensagem): void;
    public function resolver(int $id): void;
    public function pendentes(): array;
    public function resolvidos(): array;
    public function countPendentes(): int;
}