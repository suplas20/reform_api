<?php

declare(strict_types=1);

use App\Database\ConnectionFactory;

require dirname(__DIR__) . '/vendor/autoload.php';

function assertSeedData(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$config = require dirname(__DIR__) . '/config/database.php';
$connection = (new ConnectionFactory($config))->connection();

$minimumCounts = [
    'categories' => 21,
    'services' => 18,
    'category_services' => 70,
    'regions' => 42,
    'brands' => 20,
    'help_categories' => 6,
    'help_faqs' => 11,
];

foreach ($minimumCounts as $table => $minimum) {
    $count = (int) $connection->query(sprintf('SELECT COUNT(*) FROM `%s`', $table))->fetchColumn();
    assertSeedData($count >= $minimum, sprintf('%s seed count must be at least %d; got %d.', $table, $minimum, $count));
}

foreach (['bag', 'shoes', 'clothing', 'furniture-sofa', 'watch', 'jewelry'] as $slug) {
    $statement = $connection->prepare('SELECT COUNT(*) FROM categories WHERE slug = :slug AND status = :status');
    $statement->execute(['slug' => $slug, 'status' => 'ACTIVE']);
    assertSeedData((int) $statement->fetchColumn() === 1, sprintf('Active category seed is missing: %s', $slug));
}

foreach (['repair', 'restoration', 'dyeing', 'cleaning', 'sole-replacement', 'size-alteration'] as $slug) {
    $statement = $connection->prepare('SELECT COUNT(*) FROM services WHERE slug = :slug AND status = :status');
    $statement->execute(['slug' => $slug, 'status' => 'ACTIVE']);
    assertSeedData((int) $statement->fetchColumn() === 1, sprintf('Active service seed is missing: %s', $slug));
}

$demoBusinessCount = (int) $connection->query(
    "SELECT COUNT(*) FROM businesses WHERE slug LIKE 'demo-%' AND status = 'ACTIVE' AND published_at IS NOT NULL",
)->fetchColumn();
assertSeedData($demoBusinessCount === 3, 'Three published demo businesses are required.');

$demoWorkCaseCount = (int) $connection->query(
    "SELECT COUNT(*) FROM work_cases WHERE slug LIKE 'demo-%' AND status = 'PUBLISHED'",
)->fetchColumn();
assertSeedData($demoWorkCaseCount === 4, 'Four published demo work cases are required.');

fwrite(STDOUT, "Seed data tests passed.\n");
