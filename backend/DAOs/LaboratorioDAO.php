<?php

interface LaboratorioDAO
{
    public function all(): array;
    public function create(string $nome, int $capacidade, string $localizacao, string $andar): void;
    public function update(int $id, string $nome, int $capacidade, string $localizacao, string $andar): void;
    public function delete(int $id): void;
}