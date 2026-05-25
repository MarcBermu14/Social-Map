<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';

function fail(string $message): void
{
    fwrite(STDERR, "❌ {$message}" . PHP_EOL);
    exit(1);
}

$db = getDB();

$expectedTables = [
    'users',
    'publications',
    'reviews',
    'token_transactions',
    'subscriptions',
    'followers',
    'saves',
    'event_registrations',
];

$stmt = $db->query(
    'SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()'
);
$existingTables = $stmt->fetchAll(PDO::FETCH_COLUMN);
$missingTables = array_values(array_diff($expectedTables, $existingTables));

if ($missingTables !== []) {
    fail('Missing database tables: ' . implode(', ', $missingTables));
}

$stmt = $db->query(
    "SELECT COLUMN_NAME FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'publications'"
);
$publicationColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);
$requiredPublicationColumns = ['min_attendees', 'max_attendees'];
$missingColumns = array_values(array_diff($requiredPublicationColumns, $publicationColumns));

if ($missingColumns !== []) {
    fail('Missing publications columns: ' . implode(', ', $missingColumns));
}

$stmt = $db->query('SELECT COUNT(*) FROM users');
$userCount = $stmt->fetchColumn();

if ($userCount === false) {
    fail('Could not query users table.');
}

echo '✅ Database smoke test passed. Tables and required columns exist.' . PHP_EOL;
