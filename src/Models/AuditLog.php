<?php

namespace App\Models;

use PDO;

class AuditLog
{
    public function __construct(private PDO $db) {}

    public function log(
        ?int   $actorId,
        string $actorName,
        string $action,
        string $entityType,
        ?int   $entityId = null,
        string $description = ''
    ): void {
        $stmt = $this->db->prepare(
            'INSERT INTO audit_log (actor_id, actor_name, action, entity_type, entity_id, description)
             VALUES (:actor_id, :actor_name, :action, :entity_type, :entity_id, :description)'
        );
        $stmt->execute([
            ':actor_id'    => $actorId,
            ':actor_name'  => $actorName,
            ':action'      => $action,
            ':entity_type' => $entityType,
            ':entity_id'   => $entityId,
            ':description' => $description,
        ]);
    }

    /** Recent entries for admin/croupier panels, newest first. */
    public function recent(int $limit = 50): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM audit_log ORDER BY created_at DESC LIMIT :limit'
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
