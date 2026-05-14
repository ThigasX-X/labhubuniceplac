<?php

interface DisciplinaDAO
{
    public function all(): array;
    public function create(string $nome): void;
    public function update(int $id, string $nome): void;
    public function delete(int $id): void;
}