<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../web.php';

$sqlitePath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'data'
    . DIRECTORY_SEPARATOR . 'ledgerly.sqlite';
if (!is_file($sqlitePath)) {
    throw new RuntimeException('The existing SQLite database was not found at ' . $sqlitePath);
}

$source = new PDO('sqlite:' . $sqlitePath, null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$target = application_database();

foreach (['accounts', 'outgoing_records', 'outgoing_images'] as $table) {
    $count = (int) $target->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn();
    if ($count !== 0) {
        throw new RuntimeException(
            'The MySQL ' . $table . ' table is not empty. Migration stopped to avoid duplicates.'
        );
    }
}

$accountCount = 0;
$recordCount = 0;
$imageCount = 0;

try {
    $target->beginTransaction();

    $accountInsert = $target->prepare(
        'INSERT INTO accounts (id, username, password_hash, role, created_at)
         VALUES (:id, :username, :password_hash, :role, :created_at)'
    );
    $accountSelect = $source->query(
        'SELECT id, username, password_hash, role, created_at FROM accounts ORDER BY id'
    );
    while (($account = $accountSelect->fetch()) !== false) {
        $accountInsert->execute($account);
        $accountCount++;
    }

    $recordInsert = $target->prepare(
        'INSERT INTO outgoing_records (
            id, account_id, book_number, record_number, record_year, source_origin,
            location_type, location_details, delivery_method, delivery_details,
            tdoc, addressee, date_sent, subject_matter, letter_date, created_at
        ) VALUES (
            :id, :account_id, :book_number, :record_number, :record_year, :source_origin,
            :location_type, :location_details, :delivery_method, :delivery_details,
            :tdoc, :addressee, :date_sent, :subject_matter, :letter_date, :created_at
        )'
    );
    $recordSelect = $source->query(
        'SELECT id, account_id, book_number, record_number, record_year, source_origin,
                location_type, location_details, delivery_method, delivery_details,
                tdoc, addressee, date_sent, subject_matter, letter_date, created_at
         FROM outgoing_records
         ORDER BY id'
    );
    while (($record = $recordSelect->fetch()) !== false) {
        $recordInsert->execute($record);
        $recordCount++;
    }

    $imageInsert = $target->prepare(
        'INSERT INTO outgoing_images (
            id, record_id, original_name, mime_type, image_data, created_at
        ) VALUES (
            :id, :record_id, :original_name, :mime_type, :image_data, :created_at
        )'
    );
    $imageSelect = $source->query(
        'SELECT id, record_id, original_name, mime_type, image_data, created_at
         FROM outgoing_images
         ORDER BY id'
    );
    while (($image = $imageSelect->fetch()) !== false) {
        $imageInsert->bindValue(':id', (int) $image['id'], PDO::PARAM_INT);
        $imageInsert->bindValue(':record_id', (int) $image['record_id'], PDO::PARAM_INT);
        $imageInsert->bindValue(':original_name', $image['original_name'], PDO::PARAM_STR);
        $imageInsert->bindValue(':mime_type', $image['mime_type'], PDO::PARAM_STR);
        $imageInsert->bindValue(':image_data', $image['image_data'], PDO::PARAM_LOB);
        $imageInsert->bindValue(':created_at', $image['created_at'], PDO::PARAM_STR);
        $imageInsert->execute();
        $imageCount++;
    }

    $target->commit();
} catch (Throwable $error) {
    if ($target->inTransaction()) {
        $target->rollBack();
    }
    throw $error;
}

printf(
    "SQLite data copied to MySQL: %d accounts, %d outgoing records, %d images.\n",
    $accountCount,
    $recordCount,
    $imageCount
);
