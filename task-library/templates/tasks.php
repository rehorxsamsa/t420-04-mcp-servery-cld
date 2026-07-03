<?php
/** @var list<\App\Model\Task> $tasks */
/** @var int $progress */
?>
<!doctype html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'><text y='14' font-size='14'>✅</text></svg>">
    <title>Seznam úkolů</title>
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5" style="max-width: 720px;">
    <div class="text-muted small mb-1">t420-04-mcp-servery-cld</div>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mb-0">📚 Seznam úkolů</h1>
        <a href="/audit" class="btn btn-outline-secondary btn-sm">📜 Audit log</a>
    </div>

    <div class="mb-4">
        <div class="d-flex justify-content-between mb-1">
            <span class="text-muted small">Hotovo</span>
            <span class="text-muted small"><?= $progress ?> %</span>
        </div>
        <div class="progress" role="progressbar" aria-valuenow="<?= $progress ?>"
             aria-valuemin="0" aria-valuemax="100">
            <div class="progress-bar bg-success" style="width: <?= $progress ?>%"></div>
        </div>
    </div>

    <form method="post" action="/tasks" class="input-group mb-4">
        <input type="text" name="title" class="form-control"
               placeholder="Nový úkol…" required>
        <button class="btn btn-primary" type="submit">Přidat</button>
    </form>

    <ul class="list-group">
        <?php foreach ($tasks as $task): ?>
            <li class="list-group-item d-flex justify-content-between align-items-center">
                <form method="post" action="/tasks/<?= $task->id ?>/toggle" class="flex-grow-1">
                    <button type="submit"
                            class="btn btn-link text-decoration-none p-0 text-start <?= $task->done ? 'text-success' : 'text-body' ?>">
                        <?= $task->done ? '✅' : '⬜' ?>
                        <span class="<?= $task->done ? 'text-decoration-line-through' : '' ?>">
                            <?= htmlspecialchars($task->title, ENT_QUOTES) ?>
                        </span>
                    </button>
                </form>
                <form method="post" action="/tasks/<?= $task->id ?>/delete">
                    <button type="submit" class="btn btn-sm btn-outline-danger">Smazat</button>
                </form>
            </li>
        <?php endforeach; ?>
    </ul>

    <p class="text-muted small mt-4 mb-0">
        Spustitelná codebase k tutoriálu Claude Code · čisté PHP-OOP + Bootstrap 5 · Docker
    </p>
</div>
</body>
</html>
