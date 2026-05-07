<?php

require __DIR__ . '/../lib/bootstrap.php';

system('php ' . escapeshellarg(__DIR__ . '/../seed.php') . ' > /dev/null', $rc);
if ($rc !== 0) {
    fwrite(STDERR, "seed failed\n");
    exit(1);
}

$pass = 0;
$fail = 0;

function test(string $name, callable $fn): void {
    global $pass, $fail;
    try {
        $fn();
        echo "  [ok] {$name}\n";
        $pass++;
    } catch (Throwable $e) {
        echo "  [FAIL] {$name}: " . $e->getMessage() . "\n";
        $fail++;
    }
}

function assert_true($cond, string $msg = ''): void {
    if (!$cond) {
        throw new RuntimeException($msg !== '' ? $msg : 'expected true');
    }
}

echo "\nRunning tests:\n";

test('seeded share link resolves to the seeded document', function () {
    $stmt = db()->prepare('
        SELECT d.title
        FROM shares s
        JOIN documents d ON d.id = s.document_id
        LIMIT 1
    ');
    $stmt->execute();
    $row = $stmt->fetch();
    assert_true($row !== false, 'expected the seeded share to resolve');
    assert_true($row['title'] === 'Welcome Packet', 'unexpected title: ' . var_export($row['title'], true));
});

test('creating documents assigns unique readable IDs', function () {
    $staff = current_staff();

    $docA = create_document('Onboarding Packet', 'Body A', (int) $staff['id']);
    $docB = create_document('Onboarding Packet', 'Body B', (int) $staff['id']);

    $stmt = db()->prepare('SELECT readable_id FROM documents WHERE id IN (?, ?) ORDER BY id ASC');
    $stmt->execute([$docA, $docB]);
    $rows = $stmt->fetchAll();

    assert_true(count($rows) === 2, 'expected 2 created documents');
    assert_true($rows[0]['readable_id'] !== '', 'first readable_id should not be empty');
    assert_true($rows[1]['readable_id'] !== '', 'second readable_id should not be empty');
    assert_true($rows[0]['readable_id'] !== $rows[1]['readable_id'], 'readable IDs should be unique');
    assert_true(strpos($rows[0]['readable_id'], 'onboarding-packet-') === 0, 'first readable_id prefix mismatch');
});

test('scheduled documents are hidden before publish_at and visible after', function () {
    $staff = current_staff();
    $publishAt = date('Y-m-d H:i:s', time() + 3600);

    $docId = create_document('Scheduled Notice', 'Body', (int) $staff['id'], $publishAt);

    $token = random_token();
    $insertShare = db()->prepare('INSERT INTO shares (document_id, token, recipient_email) VALUES (?, ?, ?)');
    $insertShare->execute([$docId, $token, 'future@example.com']);

    $stmt = db()->prepare('SELECT * FROM documents WHERE id = ?');
    $stmt->execute([$docId]);
    $doc = $stmt->fetch();

    assert_true($doc !== false, 'expected scheduled document to exist');
    assert_true(is_document_published($doc, time()) === false, 'expected document to be hidden before publish time');
    assert_true(is_document_published($doc, time() + 7200) === true, 'expected document to be visible after publish time');
});

echo "\n{$pass} passed, {$fail} failed.\n";
exit($fail > 0 ? 1 : 0);
