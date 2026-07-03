<?php
/** @var list<\App\Model\AuditEntry> $entries */

$labels = [
    'task.created' => ['➕ Vytvořen', 'text-bg-primary'],
    'task.completed' => ['✅ Dokončen', 'text-bg-success'],
    'task.reopened' => ['↩️ Znovu otevřen', 'text-bg-warning'],
    'task.deleted' => ['🗑️ Smazán', 'text-bg-danger'],
];
?>
<!doctype html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'><text y='14' font-size='14'>✅</text></svg>">
    <title>Audit log — Seznam úkolů</title>
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5" style="max-width: 720px;">
    <div class="text-muted small mb-1">t420-04-mcp-servery-cld</div>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mb-0">📜 Audit log</h1>
        <a href="/" class="btn btn-outline-secondary btn-sm">← Zpět na úkoly</a>
    </div>

    <?php if (count($entries) === 0): ?>
        <p class="text-muted">Zatím žádné záznamy — zkus vytvořit, dokončit nebo smazat úkol.</p>
    <?php else: ?>
        <table class="table table-hover bg-white shadow-sm">
            <thead>
            <tr>
                <th scope="col">Kdy</th>
                <th scope="col">Akce</th>
                <th scope="col">Úkol</th>
                <th scope="col">Detail</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($entries as $entry): ?>
                <?php [$label, $badge] = $labels[$entry->action] ?? [$entry->action, 'text-bg-secondary']; ?>
                <tr>
                    <td class="text-muted small text-nowrap">
                        <?= htmlspecialchars(date('j. n. Y H:i:s', strtotime($entry->createdAt)), ENT_QUOTES) ?>
                    </td>
                    <td><span class="badge <?= $badge ?>"><?= $label ?></span></td>
                    <td class="text-muted">#<?= $entry->taskId ?? '—' ?></td>
                    <td><?= htmlspecialchars($entry->detail, ENT_QUOTES) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <p class="text-muted small mt-4 mb-0">
        Audit log je append-only — záznamy se jen přidávají, nikdy neupravují ani nemažou.
    </p>
</div>
</body>
</html>
