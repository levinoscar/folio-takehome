<?php

function ensure_migrations_table(PDO $pdo): void {
    $pdo->exec('
        CREATE TABLE IF NOT EXISTS migrations (
            filename TEXT PRIMARY KEY,
            applied_at TEXT NOT NULL DEFAULT (datetime(\'now\'))
        )
    ');
}

function run_migrations(PDO $pdo, bool $verbose = true): int {
    ensure_migrations_table($pdo);

    $migrationsDir = __DIR__ . '/../migrations';
    $files = glob($migrationsDir . '/*.sql');
    if ($files === false) {
        return 0;
    }

    sort($files, SORT_STRING);

    $appliedCount = 0;
    $checkStmt = $pdo->prepare('SELECT 1 FROM migrations WHERE filename = ?');
    $markStmt = $pdo->prepare('INSERT INTO migrations (filename) VALUES (?)');

    foreach ($files as $path) {
        $filename = basename($path);
        $checkStmt->execute([$filename]);
        if ($checkStmt->fetchColumn()) {
            continue;
        }

        $sql = file_get_contents($path);
        if ($sql === false) {
            throw new RuntimeException('Unable to read migration file: ' . $filename);
        }

        $pdo->beginTransaction();
        try {
            $pdo->exec($sql);
            $markStmt->execute([$filename]);
            $pdo->commit();
            $appliedCount++;

            if ($verbose) {
                echo "Applied migration: {$filename}\n";
            }
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    if ($verbose) {
        if ($appliedCount === 0) {
            echo "No pending migrations.\n";
        } else {
            echo "{$appliedCount} migration(s) applied.\n";
        }
    }

    return $appliedCount;
}
