<?php
declare(strict_types=1);

const MAX_OUTGOING_ATTACHMENTS = 10;
const MAX_OUTGOING_ATTACHMENT_BYTES = 8388608;
const MAX_OUTGOING_TOTAL_ATTACHMENT_BYTES = 20971520;
const OUTGOING_YEAR_RANGE = 10;
const OUTGOING_DOCUMENT_TYPES = [
    '1ST INDORSEMENT',
    '2ND INDORSEMENT',
    'ACTIVITY PROPOSAL',
    'AMENDED ORDER',
    'APPLICATION',
    'APPLICATION FOR LEAVE',
    'CERTIFICATE OF FINALITY',
    'CONVERSION',
    'COPY FURNISH',
    'DESIGN/ACTIVITY PROPOSAL',
    'ENDORSEMENT/INDORSEMENT',
    'LAND TRANSFER CLEARANCE',
    'LETTER',
    'MEMORANDUM',
    'NOTICE',
    'NOTICE OF MEETING',
    'ORDER',
    'PROJECT PROPOSAL',
    'RESOLUTION',
    'SPECIAL ORDER',
    'TRANSMITTAL',
    'TRAVEL ORDER',
];

function database_configuration(): array
{
    $configuration = require __DIR__ . '/database.config.example.php';
    $localConfigurationPath = __DIR__ . '/database.config.php';
    if (is_file($localConfigurationPath)) {
        $localConfiguration = require $localConfigurationPath;
        if (!is_array($localConfiguration)) {
            throw new RuntimeException('database.config.php must return a configuration array.');
        }
        $configuration = array_replace($configuration, $localConfiguration);
    }

    foreach (['host', 'database', 'username', 'password'] as $setting) {
        if (!isset($configuration[$setting]) || !is_string($configuration[$setting])) {
            throw new RuntimeException('The MySQL ' . $setting . ' setting must be a string.');
        }
    }

    if (preg_match('/^[A-Za-z0-9_]+$/', $configuration['database']) !== 1) {
        throw new RuntimeException('The MySQL database name contains invalid characters.');
    }

    return $configuration;
}

function application_database(): PDO
{
    $configuration = database_configuration();
    $database = new PDO(
        'mysql:host=' . $configuration['host']
            . ';dbname=' . $configuration['database']
            . ';charset=utf8mb4',
        $configuration['username'],
        $configuration['password'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
    $database->exec(
        'CREATE TABLE IF NOT EXISTS accounts (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            username VARCHAR(32) NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            role ENUM(\'admin\', \'outgoing\') NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY accounts_username_unique (username)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
    $database->exec(
        'CREATE TABLE IF NOT EXISTS outgoing_records (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            account_id INT UNSIGNED NOT NULL,
            book_number TINYINT UNSIGNED NOT NULL,
            record_number VARCHAR(100) NOT NULL,
            record_year SMALLINT UNSIGNED NOT NULL,
            source_origin VARCHAR(200) NOT NULL,
            location_type VARCHAR(200) NOT NULL,
            location_details VARCHAR(200) NOT NULL,
            delivery_method VARCHAR(500) NOT NULL,
            delivery_details TEXT NOT NULL,
            tdoc VARCHAR(200) NOT NULL,
            addressee VARCHAR(200) NOT NULL,
            date_sent DATE NOT NULL,
            subject_matter TEXT NOT NULL,
            action_unit VARCHAR(200) NOT NULL DEFAULT \'\',
            remarks VARCHAR(2000) NOT NULL DEFAULT \'\',
            released_by VARCHAR(200) NOT NULL DEFAULT \'\',
            letter_date DATE NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY outgoing_records_account_created (account_id, created_at, id),
            CONSTRAINT outgoing_records_account_fk
                FOREIGN KEY (account_id) REFERENCES accounts (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
    foreach ([
        'action_unit' => "ALTER TABLE outgoing_records ADD COLUMN action_unit VARCHAR(200) NOT NULL DEFAULT ''",
        'remarks' => "ALTER TABLE outgoing_records ADD COLUMN remarks VARCHAR(2000) NOT NULL DEFAULT ''",
        'released_by' => "ALTER TABLE outgoing_records ADD COLUMN released_by VARCHAR(200) NOT NULL DEFAULT ''",
    ] as $columnName => $alterSql) {
        $columnCheck = $database->prepare(
            'SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = \'outgoing_records\' AND COLUMN_NAME = :column_name'
        );
        $columnCheck->execute(['column_name' => $columnName]);
        if ((int) $columnCheck->fetchColumn() === 0) {
            $database->exec($alterSql);
        }
    }
    $locationTypeColumn = $database->query(
        "SELECT COLUMN_TYPE
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'outgoing_records'
           AND COLUMN_NAME = 'location_type'"
    )->fetchColumn();
    if (is_string($locationTypeColumn) && strpos($locationTypeColumn, 'enum(') === 0) {
        $database->exec(
            'ALTER TABLE outgoing_records
             MODIFY location_type VARCHAR(200) NOT NULL'
        );
    }
    $deliveryMethodColumn = $database->query(
        "SELECT COLUMN_TYPE
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'outgoing_records'
           AND COLUMN_NAME = 'delivery_method'"
    )->fetchColumn();
    if (is_string($deliveryMethodColumn) && strpos($deliveryMethodColumn, 'enum(') === 0) {
        $database->exec(
            'ALTER TABLE outgoing_records
             MODIFY delivery_method VARCHAR(500) NOT NULL'
        );
    }
    $deliveryDetailsColumn = $database->query(
        "SELECT DATA_TYPE FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'outgoing_records'
           AND COLUMN_NAME = 'delivery_details'"
    )->fetchColumn();
    if (is_string($deliveryDetailsColumn) && $deliveryDetailsColumn !== 'text') {
        $database->exec('ALTER TABLE outgoing_records MODIFY delivery_details TEXT NOT NULL');
    }
    $database->exec(
        'CREATE TABLE IF NOT EXISTS outgoing_images (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            record_id BIGINT UNSIGNED NOT NULL,
            original_name VARCHAR(255) NOT NULL,
            mime_type VARCHAR(127) NOT NULL,
            image_data MEDIUMBLOB NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY outgoing_images_record_id (record_id),
            CONSTRAINT outgoing_images_record_fk
                FOREIGN KEY (record_id) REFERENCES outgoing_records (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
    $attachmentMimeColumn = $database->query(
        "SELECT COLUMN_TYPE
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'outgoing_images'
           AND COLUMN_NAME = 'mime_type'"
    )->fetchColumn();
    if (is_string($attachmentMimeColumn) && strpos($attachmentMimeColumn, 'enum(') === 0) {
        $database->exec(
            'ALTER TABLE outgoing_images
             MODIFY mime_type VARCHAR(127) NOT NULL'
        );
    }
    return $database;
}

function csrf_token(): string
{
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_is_valid(): bool
{
    $submittedToken = $_POST['csrf_token'] ?? '';
    return is_string($submittedToken)
        && isset($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $submittedToken);
}

function flash_message(string $message, string $kind = 'error'): void
{
    $_SESSION['flash_message'] = ['message' => $message, 'kind' => $kind];
}

function redirect_to(string $query = ''): never
{
    $location = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
    if ($query !== '') {
        $location .= '?' . $query;
    }
    header('Location: ' . $location, true, 303);
    exit;
}

function normalized_username(mixed $value): ?string
{
    if (!is_string($value)) {
        return null;
    }
    $username = strtolower(trim($value));
    return preg_match('/^(?=.{3,32}$)[a-z0-9]+(?:[._-][a-z0-9]+)*$/', $username) === 1
        ? $username
        : null;
}

function valid_password(mixed $value): bool
{
    return is_string($value) && strlen($value) >= 8 && strlen($value) <= 128;
}

function find_account(PDO $database, string $username): ?array
{
    $statement = $database->prepare(
        'SELECT id, username, password_hash, role, created_at
         FROM accounts
         WHERE username = :username
         LIMIT 1'
    );
    $statement->execute(['username' => $username]);
    $account = $statement->fetch(PDO::FETCH_ASSOC);
    return is_array($account) ? $account : null;
}

function current_account(PDO $database): ?array
{
    $accountId = (int) ($_SESSION['account_id'] ?? 0);
    if ($accountId <= 0) {
        return null;
    }

    $statement = $database->prepare(
        'SELECT id, username, role, created_at FROM accounts WHERE id = :id LIMIT 1'
    );
    $statement->execute(['id' => $accountId]);
    $account = $statement->fetch(PDO::FETCH_ASSOC);
    return is_array($account) ? $account : null;
}

function list_accounts(PDO $database): array
{
    $statement = $database->query(
        'SELECT id, username, role, created_at
         FROM accounts
         ORDER BY created_at DESC, id DESC'
    );
    return $statement === false ? [] : $statement->fetchAll(PDO::FETCH_ASSOC);
}

function valid_iso_date(mixed $value): bool
{
    if (!is_string($value) || $value === '') {
        return false;
    }

    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    $errors = DateTimeImmutable::getLastErrors();
    return $date !== false
        && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))
        && $date->format('Y-m-d') === $value;
}

function list_outgoing_years(PDO $database, int $accountId): array
{
    $statement = $database->prepare(
        'SELECT record_year, COUNT(*) AS record_count
         FROM outgoing_records
         WHERE account_id = :account_id
         GROUP BY record_year
         ORDER BY record_year DESC'
    );
    $statement->execute(['account_id' => $accountId]);
    return $statement->fetchAll(PDO::FETCH_ASSOC);
}

function list_outgoing_records(
    PDO $database,
    int $accountId,
    ?int $year = null,
    ?int $month = null,
    string $search = '',
    ?int $book = null
): array
{
    $yearFilter = $year === null ? '' : ' AND r.record_year = :record_year';
    $monthFilter = $month === null ? '' : ' AND MONTH(r.date_sent) = :record_month';
    $bookFilter = $book === null ? '' : ' AND r.book_number = :book_number';
    $searchFilter = $search === ''
        ? ''
        : ' AND (
            LOCATE(LOWER(:record_search), LOWER(r.record_number)) > 0
            OR LOCATE(LOWER(:addressee_search), LOWER(r.addressee)) > 0
            OR LOCATE(LOWER(:source_search), LOWER(r.source_origin)) > 0
        )';
    $statement = $database->prepare(
        'SELECT r.*,
                (SELECT COUNT(*) FROM outgoing_images i WHERE i.record_id = r.id) AS image_count
         FROM outgoing_records r
         WHERE r.account_id = :account_id
         ' . $yearFilter . '
         ' . $monthFilter . '
         ' . $bookFilter . '
         ' . $searchFilter . '
         ORDER BY r.created_at DESC, r.id DESC
         LIMIT 50'
    );
    $parameters = ['account_id' => $accountId];
    if ($year !== null) {
        $parameters['record_year'] = $year;
    }
    if ($month !== null) {
        $parameters['record_month'] = $month;
    }
    if ($book !== null) {
        $parameters['book_number'] = $book;
    }
    if ($search !== '') {
        $parameters['record_search'] = $search;
        $parameters['addressee_search'] = $search;
        $parameters['source_search'] = $search;
    }
    $statement->execute($parameters);
    return $statement->fetchAll(PDO::FETCH_ASSOC);
}

function find_outgoing_record(PDO $database, int $accountId, int $recordId, int $year): ?array
{
    $statement = $database->prepare(
        'SELECT *
         FROM outgoing_records
         WHERE id = :id AND account_id = :account_id AND record_year = :record_year
         LIMIT 1'
    );
    $statement->execute([
        'id' => $recordId,
        'account_id' => $accountId,
        'record_year' => $year,
    ]);
    $record = $statement->fetch(PDO::FETCH_ASSOC);
    return is_array($record) ? $record : null;
}

function count_outgoing_records(PDO $database, int $accountId): int
{
    $statement = $database->prepare(
        'SELECT COUNT(*) FROM outgoing_records WHERE account_id = :account_id'
    );
    $statement->execute(['account_id' => $accountId]);
    return (int) $statement->fetchColumn();
}

function list_outgoing_images(
    PDO $database,
    int $accountId,
    ?int $year = null,
    ?int $month = null,
    string $search = ''
): array
{
    $yearFilter = $year === null ? '' : ' AND r.record_year = :record_year';
    $monthFilter = $month === null ? '' : ' AND MONTH(r.date_sent) = :record_month';
    $searchFilter = $search === ''
        ? ''
        : ' AND (
            LOCATE(LOWER(:record_search), LOWER(r.record_number)) > 0
            OR LOCATE(LOWER(:addressee_search), LOWER(r.addressee)) > 0
            OR LOCATE(LOWER(:source_search), LOWER(r.source_origin)) > 0
        )';
    $statement = $database->prepare(
        'SELECT i.id, i.record_id, i.original_name
         FROM outgoing_images i
         INNER JOIN outgoing_records r ON r.id = i.record_id
         WHERE r.account_id = :account_id
         ' . $yearFilter . '
         ' . $monthFilter . '
         ' . $searchFilter . '
         ORDER BY i.id'
    );
    $parameters = ['account_id' => $accountId];
    if ($year !== null) {
        $parameters['record_year'] = $year;
    }
    if ($month !== null) {
        $parameters['record_month'] = $month;
    }
    if ($search !== '') {
        $parameters['record_search'] = $search;
        $parameters['addressee_search'] = $search;
        $parameters['source_search'] = $search;
    }
    $statement->execute($parameters);
    $imagesByRecord = [];
    foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $image) {
        $imagesByRecord[(int) $image['record_id']][] = $image;
    }
    return $imagesByRecord;
}

function validate_outgoing_attachments(mixed $uploads): array
{
    if ($uploads === null) {
        return ['ok' => true, 'files' => [], 'error' => null];
    }
    if (
        !is_array($uploads)
        || !isset($uploads['name'], $uploads['tmp_name'], $uploads['error'])
        || !is_array($uploads['name'])
        || !is_array($uploads['tmp_name'])
        || !is_array($uploads['error'])
    ) {
        return ['ok' => false, 'files' => [], 'error' => 'The uploaded attachment data is invalid. Please try again.'];
    }

    $files = [];
    $totalBytes = 0;
    $fileInfo = new finfo(FILEINFO_MIME_TYPE);
    foreach ($uploads['error'] as $index => $error) {
        if ($error === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        if ($error !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'files' => [], 'error' => 'An attachment upload failed. Check the file size and try again.'];
        }
        if (!isset($uploads['name'][$index], $uploads['tmp_name'][$index])) {
            return ['ok' => false, 'files' => [], 'error' => 'The uploaded attachment data is incomplete. Please try again.'];
        }
        if (count($files) >= MAX_OUTGOING_ATTACHMENTS) {
            return ['ok' => false, 'files' => [], 'error' => 'You can attach up to 10 files to one record.'];
        }

        $temporaryPath = $uploads['tmp_name'][$index];
        if (!is_string($temporaryPath) || !is_uploaded_file($temporaryPath)) {
            return ['ok' => false, 'files' => [], 'error' => 'An uploaded attachment could not be verified. Please try again.'];
        }
        $size = filesize($temporaryPath);
        if ($size === false || $size <= 0 || $size > MAX_OUTGOING_ATTACHMENT_BYTES) {
            return ['ok' => false, 'files' => [], 'error' => 'Each attachment must be smaller than 8 MB.'];
        }
        $totalBytes += $size;
        if ($totalBytes > MAX_OUTGOING_TOTAL_ATTACHMENT_BYTES) {
            return ['ok' => false, 'files' => [], 'error' => 'The combined attachment size must be 20 MB or less.'];
        }

        $mimeType = $fileInfo->file($temporaryPath);
        if (!in_array($mimeType, ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'application/pdf'], true)) {
            return ['ok' => false, 'files' => [], 'error' => 'Use JPEG, PNG, WebP, GIF, or PDF files only.'];
        }
        $imageData = file_get_contents($temporaryPath);
        if ($imageData === false) {
            return ['ok' => false, 'files' => [], 'error' => 'An uploaded attachment could not be read. Please try again.'];
        }

        $files[] = [
            'original_name' => basename((string) $uploads['name'][$index]),
            'mime_type' => $mimeType,
            'image_data' => $imageData,
        ];
    }

    return ['ok' => true, 'files' => $files, 'error' => null];
}

function escape_html(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
