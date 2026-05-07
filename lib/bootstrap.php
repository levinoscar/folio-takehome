<?php

date_default_timezone_set('America/Chicago');

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $path = __DIR__ . '/../db.sqlite';
        $pdo = new PDO('sqlite:' . $path);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('PRAGMA foreign_keys = ON');
    }
    return $pdo;
}

function current_staff(): array {
    $stmt = db()->prepare('SELECT * FROM staff WHERE id = 1');
    $stmt->execute();
    $row = $stmt->fetch();
    if (!$row) {
        throw new RuntimeException('No staff row #1 found. Did you run `php seed.php`?');
    }
    return $row;
}

function audit_log(string $action, string $entity_type, int $entity_id, array $details = []): void {
    $staff = current_staff();
    $stmt = db()->prepare('
        INSERT INTO audit_log (staff_id, action, entity_type, entity_id, details)
        VALUES (?, ?, ?, ?, ?)
    ');
    $stmt->execute([
        $staff['id'],
        $action,
        $entity_type,
        $entity_id,
        json_encode($details),
    ]);
}

function random_token(int $bytes = 16): string {
    return bin2hex(random_bytes($bytes));
}

function slugify(string $input): string {
    $value = strtolower(trim($input));
    $value = preg_replace('/[^a-z0-9]+/', '-', $value);
    $value = trim((string) $value, '-');
    return $value !== '' ? $value : 'doc';
}

function generate_readable_id(string $title): string {
    $base = slugify($title);
    $suffix = strtolower(substr(bin2hex(random_bytes(2)), 0, 4));
    return $base . '-' . $suffix;
}

function create_document(string $title, string $body, int $createdBy): int {
    $attempts = 0;
    while ($attempts < 5) {
        $attempts++;
        $readableId = generate_readable_id($title);
        try {
            $stmt = db()->prepare('
                INSERT INTO documents (title, body, created_by, readable_id)
                VALUES (?, ?, ?, ?)
            ');
            $stmt->execute([$title, $body, $createdBy, $readableId]);
            return (int) db()->lastInsertId();
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'documents.readable_id') === false) {
                throw $e;
            }
        }
    }

    throw new RuntimeException('Failed to create a unique readable document ID after multiple attempts.');
}

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}
