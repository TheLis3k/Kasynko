<?php

namespace App\Models;

use PDO;

class Game
{
    public function __construct(private PDO $db) {}

    public function all(): array
    {
        return $this->db->query('SELECT * FROM games ORDER BY id')->fetchAll();
    }

    public function findByType(string $type): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM games WHERE type = :type LIMIT 1');
        $stmt->execute([':type' => $type]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM games WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}
