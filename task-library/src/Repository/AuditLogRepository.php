<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;
use App\Model\AuditEntry;
use PDO;

/**
 * Repository auditního logu. Log je append-only — záznamy se jen přidávají,
 * nikdy neupravují ani nemažou, jinak by ztratil důvěryhodnost.
 */
final class AuditLogRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function append(string $action, ?int $taskId, string $detail): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO audit_log (action, task_id, detail, created_at)
             VALUES (:action, :task_id, :detail, :created_at)'
        );
        $stmt->execute([
            'action' => $action,
            'task_id' => $taskId,
            'detail' => $detail,
            'created_at' => date('c'),
        ]);
    }

    /**
     * @return list<AuditEntry>
     */
    public function all(): array
    {
        $rows = $this->pdo
            ->query('SELECT * FROM audit_log ORDER BY id DESC')
            ->fetchAll();

        return array_map(static fn (array $row): AuditEntry => AuditEntry::fromRow($row), $rows);
    }
}
