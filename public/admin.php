<?php

require __DIR__ . '/../lib/bootstrap.php';
require __DIR__ . '/../lib/layout.php';

$staff = current_staff();
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $body = trim($_POST['body'] ?? '');
    $publishAtInput = trim((string) ($_POST['publish_at'] ?? ''));
    $publishAt = parse_publish_at_input($publishAtInput);

    if ($title === '' || $body === '') {
        $error = 'Title and body are required.';
    } elseif ($publishAtInput !== '' && $publishAt === null) {
        $error = 'Publish time must be a valid date/time.';
    } else {
        $docId = create_document($title, $body, (int) $staff['id'], $publishAt);

        $docStmt = db()->prepare('SELECT readable_id, publish_at FROM documents WHERE id = ?');
        $docStmt->execute([$docId]);
        $createdDoc = $docStmt->fetch();

        audit_log('create', 'document', $docId, [
            'title' => $title,
            'readable_id' => $createdDoc['readable_id'] ?? null,
            'publish_at' => $createdDoc['publish_at'] ?? null,
        ]);

        if (!empty($createdDoc['publish_at'])) {
            audit_log('schedule', 'document', $docId, [
                'publish_at' => $createdDoc['publish_at'],
            ]);
        }

        header('Location: /admin.php?created=' . $docId . '&rid=' . urlencode((string) ($createdDoc['readable_id'] ?? '')));
        exit;
    }
}

$search = trim((string) ($_GET['q'] ?? ''));
$nowTs = time();

if ($search !== '') {
    $stmt = db()->prepare('
        SELECT d.*, s.name AS creator_name
        FROM documents d
        JOIN staff s ON s.id = d.created_by
        WHERE d.title LIKE ?
        ORDER BY d.created_at DESC
    ');
    $stmt->execute([$search . '%']);
} else {
    $stmt = db()->query('
        SELECT d.*, s.name AS creator_name
        FROM documents d
        JOIN staff s ON s.id = d.created_by
        ORDER BY d.created_at DESC
    ');
}
$docs = $stmt->fetchAll();

render_header('Admin', $staff);
?>

<h1 class="page-title">Admin</h1>
<p class="page-subtitle">Create documents and generate share links for recipients.</p>

<?php if (!empty($_GET['created'])): ?>
    <div class="banner banner-success">
        Document #<?= (int) $_GET['created'] ?> created
        <?php if (!empty($_GET['rid'])): ?>
            (ID: <?= h((string) $_GET['rid']) ?>)
        <?php endif ?>.
    </div>
<?php endif ?>

<?php if ($error): ?>
    <div class="banner banner-error"><?= h($error) ?></div>
<?php endif ?>

<section class="card">
    <h2 class="card-title">New document</h2>
    <form method="post">
        <div class="form-field">
            <label for="title">Title</label>
            <input type="text" id="title" name="title" required>
        </div>
        <div class="form-field">
            <label for="body">Body</label>
            <textarea id="body" name="body" required></textarea>
        </div>
        <div class="form-field">
            <label for="publish_at">Publish at (optional)</label>
            <input type="datetime-local" id="publish_at" name="publish_at">
        </div>
        <button type="submit" class="btn">Create document</button>
    </form>
</section>

<section class="card">
    <h2 class="card-title">Documents</h2>
    <form method="get" class="search-form">
        <div class="search-row">
            <input type="text" name="q" value="<?= h($search) ?>" placeholder="Search by title prefix…" class="search-input">
            <button type="submit" class="btn">Search</button>
            <?php if ($search !== ''): ?>
                <a href="/admin.php" class="btn-link">Clear</a>
            <?php endif ?>
        </div>
    </form>
    <?php if ($search !== ''): ?>
        <p class="search-meta"><?= count($docs) ?> result(s) for "<?= h($search) ?>"</p>
    <?php endif ?>
    <?php if (empty($docs)): ?>
        <?php if ($search !== ''): ?>
            <p class="empty">No documents found for this search.</p>
        <?php else: ?>
            <p class="empty">No documents yet.</p>
        <?php endif ?>
    <?php else: ?>
        <table class="data">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Readable ID</th>
                    <th>Title</th>
                    <th>Creator</th>
                    <th>Created</th>
                    <th>Publish At</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($docs as $d): ?>
                    <?php $isScheduled = !empty($d['publish_at']) && strtotime($d['publish_at']) > $nowTs; ?>
                    <tr>
                        <td class="id">#<?= (int) $d['id'] ?></td>
                        <td><code><?= h((string) ($d['readable_id'] ?? '')) ?></code></td>
                        <td>
                            <?= h($d['title']) ?>
                            <?php if ($isScheduled): ?>
                                <span class="status-badge status-badge-scheduled">Scheduled</span>
                            <?php else: ?>
                                <span class="status-badge status-badge-live">Live</span>
                            <?php endif ?>
                        </td>
                        <td><?= h($d['creator_name']) ?></td>
                        <td><?= h($d['created_at']) ?></td>
                        <td><?= h((string) ($d['publish_at'] ?? 'Now')) ?></td>
                        <td><a href="/share.php?doc=<?= urlencode((string) ($d['readable_id'] ?? (string) $d['id'])) ?>" class="btn-link">Create share →</a></td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    <?php endif ?>
</section>

<?php render_footer(); ?>
