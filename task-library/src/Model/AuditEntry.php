<?php

declare(strict_types=1);

namespace App\Model;

/**
 * Jeden záznam auditního logu — co se kdy v aplikaci stalo a s jakým úkolem.
 */
final class AuditEntry
{
    public function __construct(
        public readonly ?int $id,
        public readonly string $action,
        public readonly ?int $taskId,
        public readonly string $detail,
        public readonly string $createdAt,
    ) {
    }

    /**
     * @param array{id: int, action: string, task_id: int|null, detail: string, created_at: string} $row
     */
    public static function fromRow(array $row): self
    {
        return new self(
            id: (int) $row['id'],
            action: (string) $row['action'],
            taskId: $row['task_id'] !== null ? (int) $row['task_id'] : null,
            detail: (string) $row['detail'],
            createdAt: (string) $row['created_at'],
        );
    }
}
