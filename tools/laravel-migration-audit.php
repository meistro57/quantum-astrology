<?php // tools/laravel-migration-audit.php
declare(strict_types=1);

/**
 * Laravel migration audit utility.
 *
 * Logical default: legacy API endpoints are mapped to Laravel API routes,
 * while legacy page scripts are mapped to web routes.
 */

$rootPath = realpath(__DIR__ . '/..');
if ($rootPath === false) {
    fwrite(STDERR, "Unable to resolve project root path.\n");
    exit(1);
}

$apiPath = $rootPath . '/api';
$pagesPath = $rootPath . '/pages';
$outputPath = $rootPath . '/storage/reports';

if (!is_dir($outputPath) && !mkdir($outputPath, 0775, true) && !is_dir($outputPath)) {
    fwrite(STDERR, "Unable to create output directory: {$outputPath}\n");
    exit(1);
}

/**
 * @return list<string>
 */
function collectPhpFiles(string $path): array
{
    if (!is_dir($path)) {
        return [];
    }

    $files = [];
    $directoryIterator = new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS);
    $iterator = new RecursiveIteratorIterator($directoryIterator);

    /** @var SplFileInfo $file */
    foreach ($iterator as $file) {
        if (!$file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        $files[] = $file->getPathname();
    }

    sort($files);
    return $files;
}

/**
 * @return array{method: string, action: string}
 */
function inferHttpIntent(string $fileName): array
{
    $stem = strtolower(pathinfo($fileName, PATHINFO_FILENAME));

    $mapping = [
        'create' => ['POST', 'store'],
        'store' => ['POST', 'store'],
        'update' => ['PUT', 'update'],
        'delete' => ['DELETE', 'destroy'],
        'destroy' => ['DELETE', 'destroy'],
        'list' => ['GET', 'index'],
        'index' => ['GET', 'index'],
        'show' => ['GET', 'show'],
        'get' => ['GET', 'show'],
        'health' => ['GET', 'health'],
    ];

    foreach ($mapping as $needle => [$method, $action]) {
        if (str_contains($stem, $needle)) {
            return ['method' => $method, 'action' => $action];
        }
    }

    return ['method' => 'POST', 'action' => 'handle'];
}

/**
 * @return non-empty-string
 */
function toControllerName(string $relativePath): string
{
    $segments = explode('/', str_replace('\\', '/', $relativePath));

    $parts = [];
    foreach ($segments as $segment) {
        $stem = pathinfo($segment, PATHINFO_FILENAME);
        $clean = preg_replace('/[^a-z0-9]+/i', ' ', $stem) ?? $stem;
        $parts[] = str_replace(' ', '', ucwords(trim($clean)));
    }

    $controller = implode('', array_filter($parts));
    return $controller !== '' ? $controller . 'Controller' : 'LegacyController';
}

/**
 * @param list<string> $files
 * @return list<array<string, string>>
 */
function buildInventory(array $files, string $basePath, string $routePrefix): array
{
    $items = [];

    foreach ($files as $file) {
        $relative = ltrim(str_replace($basePath, '', $file), DIRECTORY_SEPARATOR);
        $relativeUrlPath = str_replace(['\\', '.php'], ['/', ''], $relative);
        $intent = inferHttpIntent($relative);

        $controller = toControllerName($relative);
        $route = '/' . trim($routePrefix . '/' . $relativeUrlPath, '/');

        $items[] = [
            'legacy_file' => str_replace('\\', '/', $relative),
            'suggested_route' => $route,
            'http_method' => $intent['method'],
            'controller' => $controller,
            'controller_action' => $intent['action'],
        ];
    }

    return $items;
}

$apiInventory = buildInventory(collectPhpFiles($apiPath), $apiPath, 'api/v1');
$pageInventory = buildInventory(collectPhpFiles($pagesPath), $pagesPath, '');

$report = [
    'generated_at_utc' => gmdate('c'),
    'summary' => [
        'api_endpoint_count' => count($apiInventory),
        'page_script_count' => count($pageInventory),
    ],
    'api_endpoints' => $apiInventory,
    'page_routes' => $pageInventory,
];

$jsonPath = $outputPath . '/laravel-migration-inventory.json';
$markdownPath = $outputPath . '/laravel-migration-inventory.md';

$jsonEncoded = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
if ($jsonEncoded === false) {
    fwrite(STDERR, "Failed to encode migration inventory JSON.\n");
    exit(1);
}

if (file_put_contents($jsonPath, $jsonEncoded . PHP_EOL) === false) {
    fwrite(STDERR, "Failed to write JSON report: {$jsonPath}\n");
    exit(1);
}

$lines = [
    '# Laravel Migration Inventory',
    '',
    sprintf('- Generated at (UTC): `%s`', $report['generated_at_utc']),
    sprintf('- API endpoints discovered: **%d**', $report['summary']['api_endpoint_count']),
    sprintf('- Page scripts discovered: **%d**', $report['summary']['page_script_count']),
    '',
    '## Suggested API Route Mapping',
    '',
    '| Legacy File | Method | Route | Controller@Action |',
    '|---|---:|---|---|',
];

foreach ($apiInventory as $item) {
    $lines[] = sprintf(
        '| `%s` | `%s` | `%s` | `%s@%s` |',
        $item['legacy_file'],
        $item['http_method'],
        $item['suggested_route'],
        $item['controller'],
        $item['controller_action']
    );
}

$lines[] = '';
$lines[] = '## Suggested Web Route Mapping';
$lines[] = '';
$lines[] = '| Legacy File | Method | Route | Controller@Action |';
$lines[] = '|---|---:|---|---|';

foreach ($pageInventory as $item) {
    $lines[] = sprintf(
        '| `%s` | `%s` | `%s` | `%s@%s` |',
        $item['legacy_file'],
        $item['http_method'],
        $item['suggested_route'],
        $item['controller'],
        $item['controller_action']
    );
}

$markdown = implode(PHP_EOL, $lines) . PHP_EOL;
if (file_put_contents($markdownPath, $markdown) === false) {
    fwrite(STDERR, "Failed to write Markdown report: {$markdownPath}\n");
    exit(1);
}

fwrite(STDOUT, "Laravel migration inventory generated successfully.\n");
fwrite(STDOUT, "- JSON report: {$jsonPath}\n");
fwrite(STDOUT, "- Markdown report: {$markdownPath}\n");
