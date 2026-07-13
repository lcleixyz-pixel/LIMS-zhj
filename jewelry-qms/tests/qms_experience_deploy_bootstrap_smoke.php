<?php
declare(strict_types=1);

function assert_contains(string $needle, string $haystack, string $message): void
{
    if (!str_contains($haystack, $needle)) {
        fwrite(STDERR, "Assertion failed: {$message}\n");
        exit(1);
    }
}

$root = dirname(__DIR__);
$compose = (string)file_get_contents($root . '/deploy/experience/compose.yaml');
$envExample = (string)file_get_contents($root . '/deploy/experience/.env.example');
$bootstrapPath = $root . '/scripts/bootstrap_experience.php';
$migrationInitPath = $root . '/deploy/experience/db-init/02-apply-migrations.sh';

assert_contains('MYSQL_DATABASE=jewelry_qms', $envExample, 'experience database matches the database selected by base SQL');
assert_contains('../../database/migrations:/qms-migrations:ro', $compose, 'migration directory is mounted read-only');
assert_contains('./db-init/02-apply-migrations.sh:/docker-entrypoint-initdb.d/02-apply-migrations.sh:ro', $compose, 'migration runner is mounted after base SQL');
assert_contains('qms_external_change_events', $compose, 'database health checks the latest schema');
assert_contains('service_completed_successfully', $compose, 'application waits for idempotent experience bootstrap');
assert_contains('["php", "scripts/bootstrap_experience.php"]', $compose, 'bootstrap service uses the audited PHP initializer');

if (!is_file($bootstrapPath)) {
    fwrite(STDERR, "Assertion failed: experience bootstrap script exists\n");
    exit(1);
}
if (!is_file($migrationInitPath)) {
    fwrite(STDERR, "Assertion failed: migration init script exists\n");
    exit(1);
}

$bootstrap = (string)file_get_contents($bootstrapPath);
$migrationInit = (string)file_get_contents($migrationInitPath);
assert_contains("where('soft_delete', 0)->count()", $bootstrap, 'template seed is guarded by an empty-table check');
assert_contains('RecordFormFixtureService::seed()', $bootstrap, 'stable built-in templates are seeded');
assert_contains('/qms-migrations/*.sql', $migrationInit, 'all migrations are applied in filename order');

echo "qms experience deploy bootstrap smoke passed\n";
