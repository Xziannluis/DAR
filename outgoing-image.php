<?php
declare(strict_types=1);

ini_set('session.use_strict_mode', '1');
$isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => $isHttps,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();
require_once __DIR__ . '/web.php';

$imageId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
$database = application_database();
$account = current_account($database);
if ($imageId === false || $imageId === null || $account === null || $account['role'] !== 'outgoing') {
    http_response_code(404);
    exit;
}

$statement = $database->prepare(
    'SELECT i.original_name, i.mime_type, i.image_data
     FROM outgoing_images i
     INNER JOIN outgoing_records r ON r.id = i.record_id
     WHERE i.id = :image_id AND r.account_id = :account_id
     LIMIT 1'
);
$statement->execute([
    'image_id' => $imageId,
    'account_id' => (int) $account['id'],
]);
$image = $statement->fetch(PDO::FETCH_ASSOC);
if (
    !is_array($image)
    || !in_array(
        $image['mime_type'],
        ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'application/pdf'],
        true
    )
    || !is_string($image['original_name'])
    || !is_string($image['image_data'])
) {
    http_response_code(404);
    exit;
}

header('Content-Type: ' . $image['mime_type']);
header('Content-Length: ' . strlen($image['image_data']));
$downloadName = basename($image['original_name']);
$fallbackName = preg_replace('/[^A-Za-z0-9._-]/', '_', $downloadName);
header(
    'Content-Disposition: inline'
        . '; filename="' . ($fallbackName === '' ? 'attachment' : $fallbackName) . '"'
        . "; filename*=UTF-8''" . rawurlencode($downloadName)
);
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
echo $image['image_data'];
