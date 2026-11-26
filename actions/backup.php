<?php
declare(strict_types=1);

const MAX_OUTGOING_BACKUP_BYTES = 2147483648;
const MAX_OUTGOING_BACKUP_LINE_BYTES = 33554432;
const MAX_LEGACY_JSON_BACKUP_BYTES = 36700160;

function backup_json_line(array $value): string
{
    return json_encode(
        $value,
        JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR
    ) . "\n";
}

function write_backup_line($stream, string $line): void
{
    $length = strlen($line);
    $offset = 0;
    while ($offset < $length) {
        $written = fwrite($stream, substr($line, $offset));
        if ($written === false || $written === 0) {
            throw new RuntimeException('The temporary backup could not be written. Check available disk space.');
        }
        $offset += $written;
    }
}

function is_streaming_backup_file(string $path): bool
{
    $stream = fopen($path, 'rb');
    if (!is_resource($stream)) {
        return false;
    }
    $firstLine = fgets($stream, 65537);
    fclose($stream);
    if (!is_string($firstLine)) {
        return false;
    }
    try {
        $header = json_decode($firstLine, true, 32, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        return false;
    }
    return is_array($header)
        && ($header['format'] ?? null) === 'dar-rms-outgoing-backup'
        && ($header['version'] ?? null) === 2;
}

function download_outgoing_backup(PDO $database): never
{
    $temporaryBackup = tmpfile();
    if (!is_resource($temporaryBackup)) {
        http_response_code(500);
        exit('Could not create the temporary backup file.');
    }

    try {
        write_backup_line($temporaryBackup, backup_json_line([
            'format' => 'dar-rms-outgoing-backup',
            'version' => 2,
            'created_at' => gmdate('Y-m-d\TH:i:s\Z'),
        ]));
        $recordIds = $database->query(
            "SELECT r.id
             FROM outgoing_records r
             INNER JOIN accounts a ON a.id = r.account_id
             WHERE a.role = 'outgoing'
             ORDER BY r.record_year, r.book_number, r.record_number, r.id"
        )->fetchAll(PDO::FETCH_COLUMN);
        $recordStatement = $database->prepare(
            'SELECT r.*, a.username AS owner_username
             FROM outgoing_records r
             INNER JOIN accounts a ON a.id = r.account_id
             WHERE r.id = :id AND a.role = \'outgoing\''
        );
        $attachmentStatement = $database->prepare(
            'SELECT original_name, mime_type, image_data, created_at
             FROM outgoing_images WHERE record_id = :record_id ORDER BY id'
        );
        foreach ($recordIds as $recordIdValue) {
            $recordStatement->execute(['id' => (int) $recordIdValue]);
            $record = $recordStatement->fetch(PDO::FETCH_ASSOC);
            if (!is_array($record)) {
                continue;
            }
            $attachmentStatement->execute(['record_id' => (int) $record['id']]);
            $attachments = [];
            foreach ($attachmentStatement->fetchAll(PDO::FETCH_ASSOC) as $attachment) {
                $attachments[] = [
                    'original_name' => (string) $attachment['original_name'],
                    'mime_type' => (string) $attachment['mime_type'],
                    'data_base64' => base64_encode((string) $attachment['image_data']),
                    'created_at' => (string) $attachment['created_at'],
                ];
            }
            unset($record['id'], $record['account_id'], $record['image_count']);
            $line = backup_json_line(['type' => 'record', 'record' => $record + ['attachments' => $attachments]]);
            if (strlen($line) > MAX_OUTGOING_BACKUP_LINE_BYTES) {
                throw new RuntimeException('A record exceeds the supported backup line size.');
            }
            write_backup_line($temporaryBackup, $line);
            if (ftell($temporaryBackup) > MAX_OUTGOING_BACKUP_BYTES) {
                fclose($temporaryBackup);
                http_response_code(413);
                header('Content-Type: text/plain; charset=utf-8');
                exit('This backup is larger than the supported 2 GB limit.');
            }
        }

        $backupStats = fstat($temporaryBackup);
        $backupSize = is_array($backupStats) ? (int) ($backupStats['size'] ?? 0) : 0;
        if ($backupSize <= 0 || $backupSize > MAX_OUTGOING_BACKUP_BYTES) {
            http_response_code(413);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'This backup is larger than the supported 2 GB limit.';
            exit;
        }
        rewind($temporaryBackup);
        header('Content-Type: application/x-ndjson; charset=utf-8');
        header('Content-Disposition: attachment; filename="dar-rms-outgoing-backup-' . gmdate('Y-m-d-His') . '.jsonl"');
        header('Content-Length: ' . $backupSize);
        header('Cache-Control: no-store, private');
        header('X-Content-Type-Options: nosniff');
        fpassthru($temporaryBackup);
        fclose($temporaryBackup);
        exit;
    } catch (Throwable $error) {
        fclose($temporaryBackup);
        error_log('DAR-RMS backup export failed: ' . $error->getMessage());
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/plain; charset=utf-8');
        }
        exit('The outgoing backup could not be created. Check the server error log.');
    }
}

function validate_backup_record(array $record, array $accountIds, int $recordIndex): array
{
    $ownerUsername = $record['owner_username'] ?? null;
    if (!is_string($ownerUsername) || !isset($accountIds[$ownerUsername])) {
        throw new RuntimeException('Record ' . $recordIndex . ' does not match an existing outgoing account username.');
    }
    $bookNumber = filter_var($record['book_number'] ?? null, FILTER_VALIDATE_INT);
    $recordYear = filter_var($record['record_year'] ?? null, FILTER_VALIDATE_INT);
    $stringLimits = [
        'record_number' => 100,
        'source_origin' => 200,
        'location_type' => 200,
        'location_details' => 200,
        'delivery_method' => 500,
        'delivery_details' => 100000,
        'tdoc' => 200,
        'addressee' => 200,
        'subject_matter' => 2000,
        'action_unit' => 200,
        'remarks' => 2000,
        'released_by' => 200,
    ];
    $values = [];
    foreach ($stringLimits as $field => $maximum) {
        $value = $record[$field] ?? (in_array($field, ['location_details', 'action_unit', 'remarks', 'released_by'], true) ? '' : null);
        if (!is_string($value) || strlen($value) > $maximum) {
            throw new RuntimeException('Record ' . $recordIndex . ' has invalid ' . str_replace('_', ' ', $field) . ' data.');
        }
        $values[$field] = $value;
    }
    if (
        $bookNumber === false || $bookNumber < 1 || $bookNumber > 15
        || $recordYear === false || $recordYear < 1970 || $recordYear > (int) date('Y') + OUTGOING_YEAR_RANGE
        || trim($values['record_number']) === '' || trim($values['source_origin']) === ''
        || trim($values['location_type']) === '' || trim($values['delivery_method']) === ''
        || trim($values['tdoc']) === '' || trim($values['addressee']) === ''
        || trim($values['subject_matter']) === ''
        || !valid_iso_date($record['date_sent'] ?? null)
        || !valid_iso_date($record['letter_date'] ?? null)
    ) {
        throw new RuntimeException('Record ' . $recordIndex . ' is missing required data or has an invalid date.');
    }
    $attachments = $record['attachments'] ?? [];
    if (!is_array($attachments) || count($attachments) > MAX_OUTGOING_ATTACHMENTS) {
        throw new RuntimeException('Record ' . $recordIndex . ' has an invalid attachment list.');
    }

    return [
        'account_id' => $accountIds[$ownerUsername],
        'book_number' => (int) $bookNumber,
        'record_number' => $values['record_number'],
        'record_year' => (int) $recordYear,
        'source_origin' => $values['source_origin'],
        'location_type' => $values['location_type'],
        'location_details' => $values['location_details'],
        'delivery_method' => $values['delivery_method'],
        'delivery_details' => $values['delivery_details'],
        'tdoc' => $values['tdoc'],
        'addressee' => $values['addressee'],
        'date_sent' => $record['date_sent'],
        'subject_matter' => $values['subject_matter'],
        'action_unit' => $values['action_unit'],
        'remarks' => $values['remarks'],
        'released_by' => $values['released_by'],
        'letter_date' => $record['letter_date'],
        'created_at' => is_string($record['created_at'] ?? null) && strtotime($record['created_at']) !== false
            ? $record['created_at']
            : gmdate('Y-m-d H:i:s'),
        'attachments' => $attachments,
    ];
}

function insert_backup_record(PDO $database, array $record, array $statements, finfo $mimeDetector): bool
{
    $statements['duplicate']->execute([
        'account_id' => $record['account_id'],
        'book_number' => $record['book_number'],
        'record_number' => $record['record_number'],
        'record_year' => $record['record_year'],
    ]);
    if ($statements['duplicate']->fetchColumn() !== false) {
        return false;
    }

    $statements['record']->execute([
        'account_id' => $record['account_id'],
        'book_number' => $record['book_number'],
        'record_number' => $record['record_number'],
        'record_year' => $record['record_year'],
        'source_origin' => $record['source_origin'],
        'location_type' => $record['location_type'],
        'location_details' => $record['location_details'],
        'delivery_method' => $record['delivery_method'],
        'delivery_details' => $record['delivery_details'],
        'tdoc' => $record['tdoc'],
        'addressee' => $record['addressee'],
        'date_sent' => $record['date_sent'],
        'subject_matter' => $record['subject_matter'],
        'action_unit' => $record['action_unit'],
        'remarks' => $record['remarks'],
        'released_by' => $record['released_by'],
        'letter_date' => $record['letter_date'],
        'created_at' => $record['created_at'],
    ]);
    $recordId = (int) $database->lastInsertId();
    $totalAttachmentBytes = 0;
    foreach ($record['attachments'] as $attachment) {
        if (!is_array($attachment) || !is_string($attachment['data_base64'] ?? null)) {
            throw new RuntimeException('An imported record contains an invalid attachment.');
        }
        $mimeType = $attachment['mime_type'] ?? null;
        $originalName = $attachment['original_name'] ?? null;
        $imageData = base64_decode($attachment['data_base64'], true);
        if (
            !is_string($mimeType)
            || !in_array($mimeType, ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'application/pdf'], true)
            || !is_string($originalName) || trim($originalName) === '' || strlen($originalName) > 255
            || $imageData === false || strlen($imageData) === 0
            || strlen($imageData) > MAX_OUTGOING_ATTACHMENT_BYTES
            || $mimeDetector->buffer($imageData) !== $mimeType
        ) {
            throw new RuntimeException('An imported record contains an invalid attachment file.');
        }
        $totalAttachmentBytes += strlen($imageData);
        if ($totalAttachmentBytes > MAX_OUTGOING_TOTAL_ATTACHMENT_BYTES) {
            throw new RuntimeException('A record exceeds the 20 MB combined attachment limit.');
        }
        $attachmentCreatedAt = $attachment['created_at'] ?? $record['created_at'];
        if (!is_string($attachmentCreatedAt) || strtotime($attachmentCreatedAt) === false) {
            $attachmentCreatedAt = $record['created_at'];
        }
        $statements['attachment']->bindValue(':record_id', $recordId, PDO::PARAM_INT);
        $statements['attachment']->bindValue(':original_name', basename($originalName), PDO::PARAM_STR);
        $statements['attachment']->bindValue(':mime_type', $mimeType, PDO::PARAM_STR);
        $statements['attachment']->bindValue(':image_data', $imageData, PDO::PARAM_LOB);
        $statements['attachment']->bindValue(':created_at', $attachmentCreatedAt, PDO::PARAM_STR);
        $statements['attachment']->execute();
    }
    return true;
}

function import_outgoing_backup(PDO $database): never
{
    $upload = $_FILES['backup_file'] ?? null;
    if (!is_array($upload) || ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        flash_message('Choose a valid DAR-RMS backup file to import.');
        redirect_to('admin_view=import');
    }
    $temporaryPath = $upload['tmp_name'] ?? null;
    if (!is_string($temporaryPath) || !is_uploaded_file($temporaryPath)) {
        flash_message('The uploaded backup could not be verified. Please try again.');
        redirect_to('admin_view=import');
    }
    $fileSize = filesize($temporaryPath);
    if ($fileSize === false || $fileSize <= 0 || $fileSize > MAX_OUTGOING_BACKUP_BYTES) {
        flash_message('Backup files must be smaller than 2 GB.');
        redirect_to('admin_view=import');
    }

    $uploadedName = strtolower((string) ($upload['name'] ?? ''));
    // Some browsers or older app versions save the streaming JSONL format
    // with a .json extension. Detect the backup header instead of trusting
    // the filename, so those files are still imported as streams.
    if (str_ends_with($uploadedName, '.json') && !is_streaming_backup_file($temporaryPath)) {
        if ($fileSize > MAX_LEGACY_JSON_BACKUP_BYTES) {
            flash_message('Older .json backups are limited to 35 MB. Use a newer .jsonl export for large backups.');
            redirect_to('admin_view=import');
        }
        $legacyContents = file_get_contents($temporaryPath);
        try {
            $legacyBackup = is_string($legacyContents)
                ? json_decode($legacyContents, true, 512, JSON_THROW_ON_ERROR)
                : null;
        } catch (JsonException) {
            $legacyBackup = null;
        }
        if (
            !is_array($legacyBackup)
            || ($legacyBackup['format'] ?? null) !== 'dar-rms-outgoing-backup'
            || ($legacyBackup['version'] ?? null) !== 1
            || !is_array($legacyBackup['records'] ?? null)
            || count($legacyBackup['records']) > 1000000
        ) {
            flash_message('This is not a supported DAR-RMS backup file.');
            redirect_to('admin_view=import');
        }
        $backupStream = tmpfile();
        if (!is_resource($backupStream)) {
            flash_message('Could not prepare the backup for import. Check available disk space.');
            redirect_to('admin_view=import');
        }
        write_backup_line($backupStream, backup_json_line([
            'format' => 'dar-rms-outgoing-backup',
            'version' => 2,
            'created_at' => $legacyBackup['created_at'] ?? gmdate('Y-m-d\TH:i:s\Z'),
        ]));
        foreach ($legacyBackup['records'] as $legacyRecord) {
            if (!is_array($legacyRecord)) {
                fclose($backupStream);
                flash_message('The older backup contains an invalid record.');
                redirect_to('admin_view=import');
            }
            write_backup_line($backupStream, backup_json_line(['type' => 'record', 'record' => $legacyRecord]));
        }
        rewind($backupStream);
        $header = ['format' => 'dar-rms-outgoing-backup', 'version' => 2];
    } else {
        $backupStream = fopen($temporaryPath, 'rb');
        $headerLine = is_resource($backupStream) ? fgets($backupStream, 65537) : false;
        try {
            $header = is_string($headerLine) ? json_decode($headerLine, true, 32, JSON_THROW_ON_ERROR) : null;
        } catch (JsonException) {
            $header = null;
        }
    }
    if (
        !is_resource($backupStream)
        || !is_array($header)
        || ($header['format'] ?? null) !== 'dar-rms-outgoing-backup'
        || ($header['version'] ?? null) !== 2
    ) {
        if (is_resource($backupStream)) {
            fclose($backupStream);
        }
        flash_message('This is not a supported DAR-RMS backup (.jsonl or older .json) file.');
        redirect_to('admin_view=import');
    }

    $accountIds = [];
    foreach ($database->query("SELECT id, username FROM accounts WHERE role = 'outgoing'")->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $accountIds[(string) $row['username']] = (int) $row['id'];
    }
    $statements = [
        'duplicate' => $database->prepare(
            'SELECT id FROM outgoing_records
             WHERE account_id = :account_id AND book_number = :book_number
               AND record_number = :record_number AND record_year = :record_year
             LIMIT 1'
        ),
        'record' => $database->prepare(
            'INSERT INTO outgoing_records (
                account_id, book_number, record_number, record_year, source_origin,
                location_type, location_details, delivery_method, delivery_details,
                tdoc, addressee, date_sent, subject_matter, action_unit, remarks,
                released_by, letter_date, created_at
             ) VALUES (
                :account_id, :book_number, :record_number, :record_year, :source_origin,
                :location_type, :location_details, :delivery_method, :delivery_details,
                :tdoc, :addressee, :date_sent, :subject_matter, :action_unit, :remarks,
                :released_by, :letter_date, :created_at
             )'
        ),
        'attachment' => $database->prepare(
            'INSERT INTO outgoing_images (record_id, original_name, mime_type, image_data, created_at)
             VALUES (:record_id, :original_name, :mime_type, :image_data, :created_at)'
        ),
    ];
    $mimeDetector = new finfo(FILEINFO_MIME_TYPE);
    $importedCount = 0;
    $skippedCount = 0;
    $committedImportedCount = 0;
    $committedSkippedCount = 0;
    $recordIndex = 0;
    $batchSize = 0;
    $database->beginTransaction();
    try {
        while (($line = fgets($backupStream, MAX_OUTGOING_BACKUP_LINE_BYTES + 2)) !== false) {
            if (strlen($line) > MAX_OUTGOING_BACKUP_LINE_BYTES && !str_ends_with($line, "\n")) {
                throw new RuntimeException('A backup record line exceeds the supported 32 MB limit.');
            }
            if (trim($line) === '') {
                continue;
            }
            try {
                $lineData = json_decode($line, true, 64, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                throw new RuntimeException('The backup contains invalid JSON at record ' . ($recordIndex + 1) . '.');
            }
            // A few backup files contain the stream metadata twice. Ignore a
            // repeated metadata object wherever it appears in the line stream.
            if (
                is_array($lineData)
                && ($lineData['format'] ?? null) === 'dar-rms-outgoing-backup'
                && ($lineData['version'] ?? null) === 2
            ) {
                continue;
            }
            // Accept both the current {type: record, record: {...}} envelope and
            // early streaming backups that wrote each record as a plain object.
            $recordData = null;
            if (is_array($lineData)) {
                if (is_array($lineData['record'] ?? null) && in_array($lineData['type'] ?? 'record', ['record', 'outgoing_record'], true)) {
                    $recordData = $lineData['record'];
                } elseif (is_array($lineData['data'] ?? null) && in_array($lineData['type'] ?? 'record', ['record', 'outgoing_record'], true)) {
                    $recordData = $lineData['data'];
                } elseif (is_array($lineData['outgoing_record'] ?? null)) {
                    $recordData = $lineData['outgoing_record'];
                } elseif (!isset($lineData['type']) && isset($lineData['book_number'], $lineData['record_number'])) {
                    $recordData = $lineData;
                }
            }
            if (!is_array($recordData)) {
                $lineType = is_array($lineData) && is_string($lineData['type'] ?? null) ? $lineData['type'] : 'unknown';
                $lineKeys = is_array($lineData) ? implode(', ', array_slice(array_map('strval', array_keys($lineData)), 0, 8)) : 'not an object';
                throw new RuntimeException('The backup contains an unsupported row at line ' . ($recordIndex + 2) . ' (type: ' . $lineType . '; fields: ' . $lineKeys . ').');
            }
            $recordIndex++;
            if ($recordIndex > 1000000) {
                throw new RuntimeException('The backup contains more than 1,000,000 records.');
            }
            $record = validate_backup_record($recordData, $accountIds, $recordIndex);
            if (insert_backup_record($database, $record, $statements, $mimeDetector)) {
                $importedCount++;
            } else {
                $skippedCount++;
            }
            $batchSize++;
            if ($batchSize >= 100) {
                $database->commit();
                $committedImportedCount = $importedCount;
                $committedSkippedCount = $skippedCount;
                $database->beginTransaction();
                $batchSize = 0;
            }
        }
        if (!feof($backupStream)) {
            throw new RuntimeException('The backup file could not be read completely.');
        }
        fclose($backupStream);
        $database->commit();
    } catch (Throwable $error) {
        if (is_resource($backupStream)) {
            fclose($backupStream);
        }
        if ($database->inTransaction()) {
            $database->rollBack();
        }
        $partialMessage = $committedImportedCount > 0 || $committedSkippedCount > 0
            ? ' Earlier batches were saved (' . $committedImportedCount . ' imported, ' . $committedSkippedCount . ' skipped).'
            : '';
        flash_message('Import stopped at record ' . ($recordIndex + 1) . ': ' . $error->getMessage() . $partialMessage);
        redirect_to('admin_view=import');
    }

    flash_message(
        'Backup import complete: ' . $importedCount . ' records imported, ' . $skippedCount . ' existing records skipped.',
        'success'
    );
    redirect_to('admin_view=import');
}
