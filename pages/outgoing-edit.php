<?php
$currentYear = (int) date('Y');
$firstYear = min($currentYear, (int) $outgoingEditingEntry['record_year']);
$lastYear = max($currentYear + OUTGOING_YEAR_RANGE, (int) $outgoingEditingEntry['record_year']);
$returnUrl = 'index.php?view=list&year=' . $outgoingRecordYear
    . ($outgoingRecordMonth === null ? '' : '&month=' . $outgoingRecordMonth)
    . ($outgoingRecordBook === null ? '' : '&book=' . $outgoingRecordBook)
    . ($outgoingRecordSearch === '' ? '' : '&search=' . rawurlencode($outgoingRecordSearch));
$deliveryLabels = [
    'handcarry' => 'Handcarry',
    'email' => 'Email',
    'jrs' => 'JRS',
    'philpost' => 'PhilPost',
    'odts' => 'ODTS',
];
$selectedDeliveryMethods = array_values(array_filter(explode(',', (string) $outgoingEditingEntry['delivery_method'])));
$deliveryDetailsByMethod = json_decode((string) $outgoingEditingEntry['delivery_details'], true);
if (!is_array($deliveryDetailsByMethod)) {
    $deliveryDetailsByMethod = $selectedDeliveryMethods === []
        ? []
        : [$selectedDeliveryMethods[0] => (string) $outgoingEditingEntry['delivery_details']];
}
$locationLabels = ['pages' => 'Pages', 'folder' => 'Folder', 'binder' => 'Binder'];
$storedLocationType = (string) $outgoingEditingEntry['location_type'];
$storedLocationDetails = trim((string) $outgoingEditingEntry['location_details']);
$locationValue = isset($locationLabels[$storedLocationType]) && $storedLocationDetails !== ''
    ? $locationLabels[$storedLocationType] . ': ' . $storedLocationDetails
    : ($storedLocationDetails !== '' ? $storedLocationDetails : $storedLocationType);
?>
<div class="list-heading">
  <h2 id="outgoing-records-title">Update record <?= escape_html($outgoingEditingEntry['record_number']) ?></h2>
  <a class="year-list-back" href="<?= escape_html($returnUrl) ?>">Back to list</a>
</div>
<form class="account-form outgoing-entry-form" method="post">
  <input type="hidden" name="csrf_token" value="<?= escape_html(csrf_token()) ?>" />
  <input type="hidden" name="action" value="update-outgoing-record" />
  <input type="hidden" name="record_id" value="<?= (int) $outgoingEditingEntry['id'] ?>" />
  <input type="hidden" name="return_year" value="<?= $outgoingRecordYear ?>" />
  <input type="hidden" name="return_month" value="<?= $outgoingRecordMonth ?? '' ?>" />
  <input type="hidden" name="return_search" value="<?= escape_html($outgoingRecordSearch) ?>" />
  <input type="hidden" name="return_book" value="<?= $outgoingRecordBook ?? '' ?>" />
  <div class="outgoing-fields">
    <div class="form-field">
      <label for="edit-book-number">Select book</label>
      <select id="edit-book-number" name="book_number" required>
        <?php for ($book = 1; $book <= 15; $book++): ?>
          <option value="<?= $book ?>"<?= (int) $outgoingEditingEntry['book_number'] === $book ? ' selected' : '' ?>>Book <?= $book ?></option>
        <?php endfor; ?>
      </select>
    </div>
    <div class="form-field">
      <label for="edit-record-number">Record number</label>
      <input id="edit-record-number" name="record_number" type="text" maxlength="100" value="<?= escape_html($outgoingEditingEntry['record_number']) ?>" required />
    </div>
    <div class="form-field">
      <label for="edit-record-year">Year</label>
      <select id="edit-record-year" name="record_year" required>
        <?php for ($year = $firstYear; $year <= $lastYear; $year++): ?>
          <option value="<?= $year ?>"<?= (int) $outgoingEditingEntry['record_year'] === $year ? ' selected' : '' ?>><?= $year ?></option>
        <?php endfor; ?>
      </select>
    </div>
    <div class="form-field">
      <label for="edit-source-origin">Source / origin</label>
      <input id="edit-source-origin" name="source_origin" type="text" maxlength="200" value="<?= escape_html($outgoingEditingEntry['source_origin']) ?>" required />
    </div>
    <div class="form-field">
      <label for="edit-location-type">Pages / folder / binder</label>
      <input id="edit-location-type" name="location_type" type="text" maxlength="200" value="<?= escape_html($locationValue) ?>" required />
    </div>
    <div class="form-field">
      <label for="edit-delivery-method">Messengerial service</label>
      <div id="edit-delivery-service-rows" class="service-picker-rows" data-service-picker data-initial-methods="<?= escape_html(json_encode($selectedDeliveryMethods, JSON_THROW_ON_ERROR)) ?>" data-initial-details="<?= escape_html(json_encode($deliveryDetailsByMethod, JSON_THROW_ON_ERROR)) ?>">
        <div class="service-picker-row service-picker-fallback">
          <select name="delivery_methods[]" required>
            <?php if ($selectedDeliveryMethods === []): ?><option value="" selected disabled>Choose a messengerial service</option><?php endif; ?>
            <?php foreach ($deliveryLabels as $method => $label): ?>
              <option value="<?= escape_html($method) ?>"<?= $selectedDeliveryMethods !== [] && $selectedDeliveryMethods[0] === $method ? ' selected' : '' ?>><?= escape_html($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <button class="secondary-button add-service-button" type="button" data-add-service="edit-delivery-service-rows" hidden>Add another messengerial service</button>
    </div>
    <div class="form-field">
      <label for="edit-tdoc">T/Doc</label>
      <input id="edit-tdoc" name="tdoc" type="text" maxlength="200" value="<?= escape_html($outgoingEditingEntry['tdoc']) ?>" required />
    </div>
    <div class="form-field">
      <label for="edit-addressee">Addressee</label>
      <input id="edit-addressee" name="addressee" type="text" maxlength="200" value="<?= escape_html($outgoingEditingEntry['addressee']) ?>" required />
    </div>
    <div class="outgoing-date-fields">
      <div class="form-field">
        <label for="edit-letter-date">Date of letter</label>
        <input id="edit-letter-date" name="letter_date" type="date" value="<?= escape_html($outgoingEditingEntry['letter_date']) ?>" required />
      </div>
      <div class="form-field">
        <label for="edit-date-received">Date of received</label>
        <input id="edit-date-received" name="date_sent" type="date" value="<?= escape_html($outgoingEditingEntry['date_sent']) ?>" required />
      </div>
    </div>
    <div class="form-field">
      <label for="edit-action-unit">Action unit</label>
      <input id="edit-action-unit" name="action_unit" type="text" maxlength="200" value="<?= escape_html($outgoingEditingEntry['action_unit'] ?? '') ?>" />
    </div>
    <div class="form-field">
      <label for="edit-released-by">Released by</label>
      <input id="edit-released-by" name="released_by" type="text" maxlength="200" value="<?= escape_html($outgoingEditingEntry['released_by'] ?? '') ?>" />
    </div>
    <div class="form-field outgoing-field-wide">
      <label for="edit-remarks">Remarks</label>
      <input id="edit-remarks" name="remarks" type="text" maxlength="2000" value="<?= escape_html($outgoingEditingEntry['remarks'] ?? '') ?>" />
    </div>
    <div class="form-field outgoing-field-wide">
      <label for="edit-subject-matter">Subject matters</label>
      <textarea id="edit-subject-matter" name="subject_matter" rows="3" maxlength="2000" required><?= escape_html($outgoingEditingEntry['subject_matter']) ?></textarea>
    </div>
  </div>
  <?php if (!empty($outgoingImages[(int) $outgoingEditingEntry['id']])): ?>
    <div class="edit-attachments">
      <strong>Current attachments</strong>
      <?php foreach ($outgoingImages[(int) $outgoingEditingEntry['id']] as $attachment): ?>
        <a href="outgoing-image.php?id=<?= (int) $attachment['id'] ?>" target="_blank" rel="noopener"><?= escape_html($attachment['original_name']) ?></a>
      <?php endforeach; ?>
      <small>Attachments stay with this record when you update it.</small>
    </div>
  <?php endif; ?>
  <div class="edit-form-actions">
    <button class="submit-button" type="submit"><span>Save changes</span></button>
    <a class="secondary-button edit-cancel" href="<?= escape_html($returnUrl) ?>">Cancel</a>
  </div>
</form>
