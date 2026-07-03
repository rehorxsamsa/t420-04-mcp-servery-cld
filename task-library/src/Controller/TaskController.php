<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\TaskService;

/**
 * Controller vrstva — orchestruje request a vykreslení šablony.
 */
final class TaskController
{
    public function __construct(
        private readonly TaskService $service = new TaskService(),
    ) {
    }

    public function index(): void
    {
        $tasks = $this->service->list();
        $progress = $this->service->progress();
        $justAdded = isset($_GET['added']);
        $this->render('tasks', ['tasks' => $tasks, 'progress' => $progress, 'justAdded' => $justAdded]);
    }

    public function store(): void
    {
        $title = (string) ($_POST['title'] ?? '');
        try {
            $this->service->add($title);
            // ?added=1 je signál pro šablonu, ať po redirectu zobrazí potvrzovací toast.
            $this->redirect('/?added=1');
        } catch (\InvalidArgumentException) {
            // Neplatný název — bez potvrzení zpět na seznam.
            $this->redirect('/');
        }
    }

    public function toggle(int $id): void
    {
        $this->service->toggle($id);
        $this->redirect('/');
    }

    public function destroy(int $id): void
    {
        $this->service->remove($id);
        $this->redirect('/');
    }

    public function audit(): void
    {
        $entries = $this->service->auditEntries();
        $this->render('audit', ['entries' => $entries]);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function render(string $template, array $data = []): void
    {
        extract($data, EXTR_OVERWRITE);
        require dirname(__DIR__, 2) . '/templates/' . $template . '.php';
    }

    private function redirect(string $to): void
    {
        header('Location: ' . $to);
        exit;
    }
}
