<?php
declare(strict_types=1);

function outgoing_list_return_query(
    mixed $yearValue,
    mixed $monthValue,
    ?int $editId = null,
    mixed $searchValue = null,
    mixed $bookValue = null,
    mixed $dayValue = null
): string
{
    $query = ['view' => 'list'];
    $year = filter_var($yearValue, FILTER_VALIDATE_INT);
    if (
        $year !== false
        && $year !== null
        && $year >= 1970
        && $year <= (int) date('Y') + OUTGOING_YEAR_RANGE
    ) {
        $query['year'] = $year;
        $month = filter_var($monthValue, FILTER_VALIDATE_INT);
        if ($month !== false && $month !== null && $month >= 1 && $month <= 12) {
            $query['month'] = $month;
        }
    }
    if ($editId !== null && $editId > 0) {
        $query['edit'] = $editId;
    }
    $day = filter_var($dayValue, FILTER_VALIDATE_INT);
    if ($day !== false && $day !== null && $day >= 1 && $day <= 31) {
        $query['day'] = $day;
    }
    if (is_string($searchValue) && trim($searchValue) !== '') {
        $query['search'] = trim($searchValue);
    }
    $book = filter_var($bookValue, FILTER_VALIDATE_INT);
    if ($book !== false && $book !== null && $book >= 1 && $book <= 15) {
        $query['book'] = $book;
    }
    return http_build_query($query);
}

function validate_outgoing_record_input(string $returnQuery): array
{
    $bookNumber = filter_var($_POST['book_number'] ?? null, FILTER_VALIDATE_INT);
    $recordYear = filter_var($_POST['record_year'] ?? null, FILTER_VALIDATE_INT);
    $locationType = $_POST['location_type'] ?? null;
    $deliveryMethods = $_POST['delivery_methods'] ?? null;
    $deliveryDetails = $_POST['delivery_details'] ?? null;
    $allowedDeliveryMethods = ['handcarry', 'email', 'jrs', 'philpost', 'odts'];
    if (!is_array($deliveryMethods)) {
        $deliveryMethods = [];
    }
    $deliveryMethods = array_values(array_unique(array_filter(
        $deliveryMethods,
        static fn ($method): bool => is_string($method) && in_array($method, $allowedDeliveryMethods, true)
    )));
    $validDeliveryDetails = is_array($deliveryDetails);
    $cleanDeliveryDetails = [];
    foreach ($deliveryMethods as $method) {
        $detail = $validDeliveryDetails ? ($deliveryDetails[$method] ?? null) : null;
        if (in_array($method, ['handcarry', 'jrs', 'philpost'], true)) {
            if (!is_array($detail)) {
                $validDeliveryDetails = false;
                break;
            }
            $requiredDetailFields = $method === 'handcarry'
                ? ['handcarry_name', 'receiver_name']
                : ['reference', 'receiver_name'];
            foreach ($requiredDetailFields as $nameField) {
                $nameValue = $detail[$nameField] ?? null;
                if (!is_string($nameValue) || trim($nameValue) === '' || strlen(trim($nameValue)) > 200) {
                    $validDeliveryDetails = false;
                    break 2;
                }
                $cleanDeliveryDetails[$method][$nameField] = trim($nameValue);
            }
        } else {
            if (!is_string($detail) || trim($detail) === '' || strlen(trim($detail)) > 200) {
                $validDeliveryDetails = false;
                break;
            }
            $cleanDeliveryDetails[$method] = trim($detail);
        }
    }
    $fieldRules = [
        'record_number' => ['Record number', 100],
        'source_origin' => ['Source / origin', 200],
        'tdoc' => ['T/Doc', 200],
        'addressee' => ['Addressee', 200],
        'subject_matter' => ['Subject matter', 2000],
        'action_unit' => ['Action unit', 200],
        'remarks' => ['Remarks', 2000],
        'released_by' => ['Released by', 200],
    ];
    $fields = [];
    $invalidField = null;
    foreach ($fieldRules as $fieldName => [$label, $maximumLength]) {
        $value = $_POST[$fieldName] ?? null;
        if (!is_string($value) || strlen(trim($value)) > $maximumLength
            || !in_array($fieldName, ['action_unit', 'remarks', 'released_by'], true) && trim($value) === '') {
            $invalidField = $label;
            break;
        }
        $fields[$fieldName] = trim($value);
    }

    if (
        $bookNumber === false
        || $bookNumber < 1
        || $bookNumber > 15
        || $recordYear === false
        || $recordYear < 1970
        || $recordYear > (int) date('Y') + OUTGOING_YEAR_RANGE
        || !is_string($locationType)
        || trim($locationType) === ''
        || strlen(trim($locationType)) > 200
        || $deliveryMethods === []
        || !$validDeliveryDetails
        || !valid_iso_date($_POST['date_sent'] ?? null)
        || !valid_iso_date($_POST['letter_date'] ?? null)
        || $invalidField !== null
    ) {
        flash_message(
            $invalidField === null
                ? 'Complete all required fields with valid values.'
                : $invalidField . ' is required and must be within its allowed length.'
        );
        redirect_to($returnQuery);
    }

    return [
        'book_number' => $bookNumber,
        'record_number' => $fields['record_number'],
        'record_year' => $recordYear,
        'source_origin' => $fields['source_origin'],
        'location_type' => trim($locationType),
        'delivery_method' => implode(',', $deliveryMethods),
        'delivery_details' => json_encode($cleanDeliveryDetails, JSON_THROW_ON_ERROR),
        'tdoc' => $fields['tdoc'],
        'addressee' => $fields['addressee'],
        'date_sent' => $_POST['date_sent'],
        'subject_matter' => $fields['subject_matter'],
        'action_unit' => $fields['action_unit'],
        'remarks' => $fields['remarks'],
        'released_by' => $fields['released_by'],
        'letter_date' => $_POST['letter_date'],
    ];
}

function save_outgoing_record(PDO $database, array $account): never
{
    $record = validate_outgoing_record_input('view=entry');
    $uploads = validate_outgoing_attachments($_FILES['attachments'] ?? null);
    if (!$uploads['ok']) {
        flash_message((string) $uploads['error']);
        redirect_to('view=entry');
    }

    try {
        $database->beginTransaction();
        $statement = $database->prepare(
            'INSERT INTO outgoing_records (
                account_id, book_number, record_number, record_year, source_origin,
                location_type, location_details, delivery_method, delivery_details,
                tdoc, addressee, date_sent, subject_matter, action_unit, remarks, released_by, letter_date, created_at
            ) VALUES (
                :account_id, :book_number, :record_number, :record_year, :source_origin,
                :location_type, :location_details, :delivery_method, :delivery_details,
                :tdoc, :addressee, :date_sent, :subject_matter, :action_unit, :remarks, :released_by, :letter_date, :created_at
            )'
        );
        $statement->execute([
            'account_id' => (int) $account['id'],
            'book_number' => $record['book_number'],
            'record_number' => $record['record_number'],
            'record_year' => $record['record_year'],
            'source_origin' => $record['source_origin'],
            'location_type' => $record['location_type'],
            'location_details' => '',
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
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);
        $recordId = (int) $database->lastInsertId();

        $imageStatement = $database->prepare(
            'INSERT INTO outgoing_images (record_id, original_name, mime_type, image_data, created_at)
             VALUES (:record_id, :original_name, :mime_type, :image_data, :created_at)'
        );
        foreach ($uploads['files'] as $image) {
            $imageStatement->bindValue(':record_id', $recordId, PDO::PARAM_INT);
            $imageStatement->bindValue(':original_name', $image['original_name'], PDO::PARAM_STR);
            $imageStatement->bindValue(':mime_type', $image['mime_type'], PDO::PARAM_STR);
            $imageStatement->bindValue(':image_data', $image['image_data'], PDO::PARAM_LOB);
            $imageStatement->bindValue(':created_at', gmdate('Y-m-d H:i:s'), PDO::PARAM_STR);
            $imageStatement->execute();
        }
        $database->commit();
    } catch (PDOException $error) {
        if ($database->inTransaction()) {
            try {
                $database->rollBack();
            } catch (PDOException $rollbackError) {
                error_log('Outgoing record rollback failed: ' . $rollbackError->getMessage());
            }
        }
        error_log('Outgoing record save failed: ' . $error->getMessage());
        $databaseErrorCode = (int) ($error->errorInfo[1] ?? 0);
        flash_message(
            in_array($databaseErrorCode, [2006, 1153], true)
                ? 'The database connection closed while saving. Restart MySQL with max_allowed_packet set to 32M, then try again.'
                : 'The outgoing record could not be saved. Please try again.'
        );
        redirect_to('view=entry');
    }

    flash_message('Outgoing record saved.', 'success');
    redirect_to('view=list');
}

function update_outgoing_record(PDO $database, array $account): never
{
    $recordId = filter_var($_POST['record_id'] ?? null, FILTER_VALIDATE_INT);
    if ($recordId === false || $recordId === null || $recordId <= 0) {
        flash_message('The outgoing record could not be found.');
        redirect_to('view=list');
    }

    $returnYear = filter_var($_POST['return_year'] ?? null, FILTER_VALIDATE_INT);
    if (
        $returnYear === false
        || $returnYear === null
        || $returnYear < 1970
        || $returnYear > (int) date('Y') + OUTGOING_YEAR_RANGE
    ) {
        flash_message('The outgoing record could not be found.');
        redirect_to('view=list');
    }

    $returnQuery = outgoing_list_return_query(
        $returnYear,
        $_POST['return_month'] ?? null,
        $recordId,
        $_POST['return_search'] ?? null,
        $_POST['return_book'] ?? null,
        $_POST['return_day'] ?? null
    );
    $record = validate_outgoing_record_input($returnQuery);
    $existing = find_outgoing_record(
        $database,
        (int) $account['id'],
        $recordId,
        $returnYear
    );
    if ($existing === null) {
        flash_message('The outgoing record could not be found.');
        redirect_to(outgoing_list_return_query(
            $_POST['return_year'] ?? null,
            $_POST['return_month'] ?? null,
            null,
            $_POST['return_search'] ?? null,
            $_POST['return_book'] ?? null,
            $_POST['return_day'] ?? null
        ));
    }

    try {
        $statement = $database->prepare(
            'UPDATE outgoing_records
             SET book_number = :book_number,
                 record_number = :record_number,
                 record_year = :record_year,
                 source_origin = :source_origin,
                 location_type = :location_type,
                 location_details = :location_details,
                 delivery_method = :delivery_method,
                 delivery_details = :delivery_details,
                 tdoc = :tdoc,
                 addressee = :addressee,
                 date_sent = :date_sent,
                 subject_matter = :subject_matter,
                 action_unit = :action_unit,
                 remarks = :remarks,
                 released_by = :released_by,
                 letter_date = :letter_date
             WHERE id = :id AND account_id = :account_id'
        );
        $statement->execute([
            'book_number' => $record['book_number'],
            'record_number' => $record['record_number'],
            'record_year' => $record['record_year'],
            'source_origin' => $record['source_origin'],
            'location_type' => $record['location_type'],
            'location_details' => '',
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
            'id' => $recordId,
            'account_id' => (int) $account['id'],
        ]);
    } catch (PDOException $error) {
        error_log('Outgoing record update failed: ' . $error->getMessage());
        flash_message('The outgoing record could not be updated. Please try again.');
        redirect_to($returnQuery);
    }

    flash_message('Outgoing record updated.', 'success');
    $returnMonth = filter_var($_POST['return_month'] ?? null, FILTER_VALIDATE_INT);
    if ($returnMonth === false || $returnMonth === null || $returnMonth < 1 || $returnMonth > 12) {
        $returnMonth = null;
    } else {
        $returnMonth = (int) date('n', strtotime($record['date_sent']));
    }
    redirect_to(
        outgoing_list_return_query(
            $record['record_year'],
            $returnMonth,
            null,
            $_POST['return_search'] ?? null,
            $_POST['return_book'] ?? null,
            $_POST['return_day'] ?? null
        )
    );
}

function delete_outgoing_record(PDO $database, array $account): never
{
    $recordId = filter_var($_POST['record_id'] ?? null, FILTER_VALIDATE_INT);
    if ($recordId === false || $recordId === null || $recordId <= 0) {
        flash_message('The outgoing record could not be found.');
        redirect_to('view=list');
    }

    $returnQuery = outgoing_list_return_query(
        $_POST['return_year'] ?? null,
        $_POST['return_month'] ?? null,
        null,
        $_POST['return_search'] ?? null,
        $_POST['return_book'] ?? null,
        $_POST['return_day'] ?? null
    );
    $statement = $database->prepare(
        'DELETE FROM outgoing_records WHERE id = :id AND account_id = :account_id'
    );
    try {
        $statement->execute([
            'id' => $recordId,
            'account_id' => (int) $account['id'],
        ]);
    } catch (PDOException $error) {
        error_log('Outgoing record deletion failed: ' . $error->getMessage());
        flash_message('The outgoing record could not be removed. Please try again.');
        redirect_to($returnQuery);
    }

    if ($statement->rowCount() !== 1) {
        flash_message('The outgoing record could not be found.');
        redirect_to($returnQuery);
    }

    flash_message('Outgoing record removed.', 'success');
    redirect_to($returnQuery);
}
