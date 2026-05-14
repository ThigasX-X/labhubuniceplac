<?php
interface UsuarioDAO {
    public function findByEmail(string $email): ?array;
    public function findById(int $id): ?array;
    public function create(string $nome, string $email, string $senhaHash, string $perfil): int;
    public function listProfessores(): array;
}