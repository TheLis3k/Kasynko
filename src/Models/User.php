<?php

namespace App\Models;

use PDO;

class User
{
    public function __construct(private PDO $db) {}

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
        $stmt->execute([':email' => $email]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO users (first_name, last_name, email, password_hash, role, balance, lang_preference)
             VALUES (:first_name, :last_name, :email, :password_hash, :role, :balance, :lang)
             RETURNING id'
        );
        $stmt->execute([
            ':first_name'    => $data['first_name'],
            ':last_name'     => $data['last_name'],
            ':email'         => $data['email'],
            ':password_hash' => password_hash($data['password'], PASSWORD_ARGON2ID),
            ':role'          => $data['role'] ?? 'user',
            ':balance'       => $data['balance'] ?? 1000.00,
            ':lang'          => $data['lang'] ?? 'pl',
        ]);
        return (int)$stmt->fetchColumn();
    }

    public function updateBalance(int $id, float $delta): void
    {
        $stmt = $this->db->prepare(
            'UPDATE users SET balance = balance + :delta WHERE id = :id'
        );
        $stmt->execute([':delta' => $delta, ':id' => $id]);
    }

    public function updateLang(int $id, string $lang): void
    {
        $stmt = $this->db->prepare(
            'UPDATE users SET lang_preference = :lang WHERE id = :id'
        );
        $stmt->execute([':lang' => $lang, ':id' => $id]);
    }

    public function updateProfile(int $id, array $data): void
    {
        $sets = [];
        $params = [':id' => $id];

        foreach (['first_name', 'last_name', 'lang_preference'] as $col) {
            if (array_key_exists($col, $data)) {
                $sets[]         = "{$col} = :{$col}";
                $params[":{$col}"] = $data[$col];
            }
        }
        if (array_key_exists('avatar_path', $data)) {
            $sets[]             = 'avatar_path = :avatar_path';
            $params[':avatar_path'] = $data['avatar_path'];
        }

        if (empty($sets)) {
            return;
        }

        $sql = 'UPDATE users SET ' . implode(', ', $sets) . ' WHERE id = :id';
        $this->db->prepare($sql)->execute($params);
    }

    public function delete(int $id): void
    {
        $this->db->prepare('DELETE FROM users WHERE id = :id')->execute([':id' => $id]);
    }

    public function all(): array
    {
        return $this->db->query('SELECT id, first_name, last_name, email, role, balance, created_at FROM users ORDER BY created_at DESC')->fetchAll();
    }
}
