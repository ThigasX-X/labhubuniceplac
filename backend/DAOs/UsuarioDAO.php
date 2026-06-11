<?php
interface UsuarioDAO {
    public function findByEmail(string $email): ?array;
    public function findById(int $id): ?array;
    public function create(string $nome, string $email, string $senhaHash, string $perfil): int;
    public function listProfessores(): array;
    public function updateFoto(int $id, string $caminho): void;
    public function upsertGoogle(string $email, string $nome, string $foto): array;
}