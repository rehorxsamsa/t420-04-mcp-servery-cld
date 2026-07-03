<?php

declare(strict_types=1);

namespace App\Service;

use App\Model\AuditEntry;
use App\Model\Task;
use App\Repository\AuditLogRepository;
use App\Repository\TaskRepository;

/**
 * Service vrstva — business logika. Controller nikdy nesahá na repository přímo.
 */
final class TaskService
{
    public function __construct(
        private readonly TaskRepository $repository = new TaskRepository(),
        private readonly AuditLogRepository $auditLog = new AuditLogRepository(),
    ) {
    }

    /**
     * @return list<Task>
     */
    public function list(): array
    {
        return $this->repository->all();
    }

    public function add(string $title): Task
    {
        $title = trim($title);
        if ($title === '') {
            throw new \InvalidArgumentException('Název úkolu nesmí být prázdný');
        }

        $task = $this->repository->create($title);
        $this->auditLog->append(
            'task.created',
            $task->id,
            sprintf('Úkol „%s" vytvořen', $task->title),
        );

        return $task;
    }

    public function toggle(int $id): void
    {
        $task = $this->repository->find($id);
        $this->repository->toggle($id);

        if ($task !== null) {
            $this->auditLog->append(
                $task->done ? 'task.reopened' : 'task.completed',
                $id,
                sprintf(
                    'Úkol „%s" označen jako %s',
                    $task->title,
                    $task->done ? 'nehotový' : 'hotový',
                ),
            );
        }
    }

    public function remove(int $id): void
    {
        $task = $this->repository->find($id);
        $this->repository->delete($id);

        if ($task !== null) {
            $this->auditLog->append(
                'task.deleted',
                $id,
                sprintf('Úkol „%s" smazán', $task->title),
            );
        }
    }

    /**
     * @return list<AuditEntry>
     */
    public function auditEntries(): array
    {
        return $this->auditLog->all();
    }

    /**
     * Spočítá kolik úkolů je hotových. (V dílu 2 na téhle metodě uděláme /review a testy.)
     */
    public function progress(): int
    {
        $tasks = $this->repository->all();
        if (count($tasks) === 0) {
            return 0;
        }

        $done = 0;
        foreach ($tasks as $task) {
            if ($task->done) {
                $done++;
            }
        }

        return (int) round($done / count($tasks) * 100);
    }
}
