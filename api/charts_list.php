<?php
# api/charts_list.php
declare(strict_types=1);

require __DIR__ . '/../config.php';

use QuantumAstrology\Core\DB;
use QuantumAstrology\Core\Session;

header('Content-Type: application/json');
Session::start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok'=>false, 'error'=>'Login required']);
    exit;
}

$uid = (int)$_SESSION['user_id'];
$pdo = DB::conn();

$limitRaw = filter_input(INPUT_GET, 'limit', FILTER_VALIDATE_INT);
$offsetRaw = filter_input(INPUT_GET, 'offset', FILTER_VALIDATE_INT);

if ($limitRaw === null || $limitRaw === false) {
    $limitRaw = isset($_GET['limit']) ? filter_var($_GET['limit'], FILTER_VALIDATE_INT) : null;
}
if ($offsetRaw === null || $offsetRaw === false) {
    $offsetRaw = isset($_GET['offset']) ? filter_var($_GET['offset'], FILTER_VALIDATE_INT) : null;
}

$limit = is_int($limitRaw) ? $limitRaw : 20;
$offset = is_int($offsetRaw) ? $offsetRaw : 0;
$limit = max(1, min(100, $limit));
$offset = max(0, $offset);

$search = trim((string)($_GET['q'] ?? ''));
$visibility = strtolower(trim((string)($_GET['visibility'] ?? 'all')));
if (!in_array($visibility, ['all', 'public', 'private'], true)) {
    $visibility = 'all';
}
$sort = strtolower(trim((string)($_GET['sort'] ?? 'newest')));
if (!in_array($sort, ['newest', 'oldest', 'name_asc', 'name_desc'], true)) {
    $sort = 'newest';
}

$where = ['user_id = :uid'];
$params = [':uid' => $uid];

if ($search !== '') {
    $where[] = "(LOWER(name) LIKE :search OR LOWER(COALESCE(birth_location_name, '')) LIKE :search)";
    $params[':search'] = '%' . strtolower($search) . '%';
}

if ($visibility === 'public') {
    $where[] = "is_public = 1";
} elseif ($visibility === 'private') {
    $where[] = "is_public = 0";
}

$orderBy = match ($sort) {
    'oldest' => 'id ASC',
    'name_asc' => 'name ASC, id DESC',
    'name_desc' => 'name DESC, id DESC',
    default => 'id DESC',
};

$whereSql = implode(' AND ', $where);
$totalStmt = $pdo->prepare("SELECT COUNT(*) FROM charts WHERE {$whereSql}");
foreach ($params as $key => $value) {
    $totalStmt->bindValue($key, $value, $key === ':uid' ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$totalStmt->execute();
$total = (int) $totalStmt->fetchColumn();

$stmt = $pdo->prepare("SELECT id, name, chart_type, is_public, birth_location_name, created_at FROM charts WHERE {$whereSql} ORDER BY {$orderBy} LIMIT :lim OFFSET :off");
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value, $key === ':uid' ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
$stmt->bindValue(':off', $offset, PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll();

$count = count($rows);
$nextOffset = $offset + $count;
$hasMore = $nextOffset < $total;

echo json_encode([
    'ok' => true,
    'charts' => $rows,
    'pagination' => [
        'limit' => $limit,
        'offset' => $offset,
        'count' => $count,
        'total' => $total,
        'has_more' => $hasMore,
        'next_offset' => $hasMore ? $nextOffset : null,
    ],
    'filters' => [
        'q' => $search,
        'visibility' => $visibility,
        'sort' => $sort,
    ],
], JSON_PRETTY_PRINT);
