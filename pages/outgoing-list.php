<section class="outgoing-records-section" aria-labelledby="outgoing-records-title">
  <?php if ($outgoingEditId !== null): ?>
    <?php if ($outgoingEditingEntry === null): ?>
      <p class="empty-records">That outgoing record could not be found.</p>
    <?php else: ?>
      <?php require __DIR__ . '/outgoing-edit.php'; ?>
    <?php endif; ?>
  <?php elseif ($outgoingRecordYear === null): ?>
    <div class="list-heading">
      <h2 id="outgoing-records-title">Records by year</h2>
      <span><?= count($outgoingYears) ?> <?= count($outgoingYears) === 1 ? 'year' : 'years' ?></span>
    </div>
    <?php if ($outgoingYears === []): ?>
      <p class="empty-records">Your saved outgoing records will appear here.</p>
    <?php else: ?>
      <div class="year-card-grid">
        <?php foreach ($outgoingYears as $yearGroup): ?>
          <a class="year-card" href="index.php?view=list&amp;year=<?= (int) $yearGroup['record_year'] ?>">
            <span class="year-card-icon" aria-hidden="true">
              <svg viewBox="0 0 24 24" fill="none"><rect x="3.5" y="5" width="17" height="15.5" rx="2" /><path d="M7.5 3.5v3M16.5 3.5v3M3.5 9.5h17M8 13h2m4 0h2m-8 4h2" /></svg>
            </span>
            <span class="year-card-copy">
              <strong><?= escape_html($yearGroup['record_year']) ?></strong>
              <small><?= number_format((int) $yearGroup['record_count']) ?> <?= (int) $yearGroup['record_count'] === 1 ? 'record' : 'records' ?></small>
            </span>
            <span class="year-card-arrow" aria-hidden="true">›</span>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  <?php else: ?>
    <?php
      $monthNames = [
          1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
          5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
          9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December',
      ];
    ?>
    <div class="list-heading">
      <a class="year-list-back" href="index.php?view=list">All years</a>
    </div>
    <form class="month-filter" method="get" data-logbook-filters>
      <input type="hidden" name="view" value="list" />
      <input type="hidden" name="year" value="<?= escape_html($outgoingRecordYear) ?>" />
      <div class="record-filter-field">
        <label for="record-book-filter">Filter by book</label>
        <select id="record-book-filter" name="book">
          <option value="">All books</option>
          <?php for ($bookNumber = 1; $bookNumber <= 15; $bookNumber++): ?>
            <option value="<?= $bookNumber ?>"<?= $outgoingRecordBook === $bookNumber ? ' selected' : '' ?>>Book <?= $bookNumber ?></option>
          <?php endfor; ?>
        </select>
      </div>
      <div class="record-filter-field">
        <label for="record-month-filter">Filter by date of receive month</label>
        <select id="record-month-filter" name="month">
          <option value="">All months</option>
          <?php foreach ($monthNames as $monthNumber => $monthName): ?>
            <option value="<?= $monthNumber ?>"<?= $outgoingRecordMonth === $monthNumber ? ' selected' : '' ?>><?= escape_html($monthName) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="record-filter-field">
        <label for="record-day-filter">Filter by day received</label>
        <select id="record-day-filter" name="day">
          <option value="">All days</option>
          <?php for ($dayNumber = 1; $dayNumber <= 31; $dayNumber++): ?>
            <option value="<?= $dayNumber ?>"<?= $outgoingRecordDay === $dayNumber ? ' selected' : '' ?>><?= sprintf('%02d', $dayNumber) ?></option>
          <?php endfor; ?>
        </select>
      </div>
      <div class="record-filter-field record-search-field">
        <label for="record-search">Search records by keyword</label>
        <input
          class="record-search-input"
          id="record-search"
          name="search"
          type="search"
          maxlength="100"
          value="<?= escape_html($outgoingRecordSearch) ?>"
          placeholder="Name, subject, document type, service..."
        />
      </div>
      <?php if ($outgoingRecordMonth !== null || $outgoingRecordDay !== null || $outgoingRecordBook !== null || $outgoingRecordSearch !== ''): ?>
        <a class="year-list-back" href="index.php?<?= escape_html(outgoing_list_return_query($outgoingRecordYear, null)) ?>">Clear filters</a>
      <?php endif; ?>
    </form>

    <?php if ($outgoingEntries === []): ?>
      <p class="empty-records">
        <?php if ($outgoingRecordSearch !== ''): ?>
          No records matched "<?= escape_html($outgoingRecordSearch) ?>".
        <?php elseif ($outgoingRecordBook !== null && $outgoingRecordMonth === null && $outgoingRecordDay === null): ?>
          No records were found in Book <?= (int) $outgoingRecordBook ?> for <?= escape_html($outgoingRecordYear) ?>.
        <?php elseif ($outgoingRecordMonth === null && $outgoingRecordDay === null): ?>
          Your saved outgoing records will appear here.
        <?php else: ?>
          No records match the selected received date<?= $outgoingRecordMonth === null ? '' : ' in ' . escape_html($monthNames[$outgoingRecordMonth]) ?><?= $outgoingRecordDay === null ? '' : ' on day ' . sprintf('%02d', $outgoingRecordDay) ?> <?= escape_html($outgoingRecordYear) ?>.
        <?php endif; ?>
      </p>
    <?php else: ?>
      <p class="year-record-count">
        Showing up to 50<?= $outgoingRecordSearch === '' && $outgoingRecordBook === null && $outgoingRecordMonth === null && $outgoingRecordDay === null ? '' : ' matching' ?> records.
      </p>
      <div class="outgoing-record-list">
        <?php foreach ($outgoingEntries as $entry): ?>
          <?php
            $recordId = (int) $entry['id'];
            $locationLabels = ['pages' => 'Pages', 'folder' => 'Folder', 'binder' => 'Binder'];
            $deliveryLabels = [
                'handcarry' => 'Handcarry',
                'email' => 'Email',
                'jrs' => 'JRS',
                'philpost' => 'PhilPost',
                'odts' => 'ODTS',
                'lbc' => 'LBC',
            ];
            $locationType = (string) $entry['location_type'];
            $locationLabel = $locationLabels[$locationType] ?? $locationType;
            $locationDetails = trim((string) $entry['location_details']);
            $locationDisplay = isset($locationLabels[$locationType]) && $locationDetails !== ''
                ? $locationLabel . ': ' . $locationDetails
                : ($locationDetails !== '' ? $locationDetails : $locationLabel);
            $editUrl = 'index.php?' . outgoing_list_return_query(
                $outgoingRecordYear,
                $outgoingRecordMonth,
                $recordId,
                $outgoingRecordSearch,
                $outgoingRecordBook,
                $outgoingRecordDay
            );
            $receivedDate = (string) $entry['date_sent'];
            $deliveryMethods = explode(',', (string) $entry['delivery_method']);
            $deliveryDetails = json_decode((string) $entry['delivery_details'], true);
            if (!is_array($deliveryDetails)) {
                $deliveryDetails = $deliveryMethods === []
                    ? []
                    : [$deliveryMethods[0] => (string) $entry['delivery_details']];
            }
            $deliveryDisplay = [];
            foreach ($deliveryMethods as $method) {
                $methodDetails = $deliveryDetails[$method] ?? '';
                if ($method === 'handcarry' && is_array($methodDetails)) {
                    $deliveryDisplay[] = 'Handcarry: ' . (string) ($methodDetails['handcarry_name'] ?? '')
                        . ' · Name of receiver: ' . (string) ($methodDetails['receiver_name'] ?? '');
                } elseif (in_array($method, ['jrs', 'philpost'], true) && is_array($methodDetails)) {
                    $serviceLabel = $deliveryLabels[$method] ?? $method;
                    $deliveryDisplay[] = $serviceLabel . ' reference: ' . (string) ($methodDetails['reference'] ?? '')
                        . ' · Name of receiver: ' . (string) ($methodDetails['receiver_name'] ?? '');
                } else {
                    $deliveryDisplay[] = ($deliveryLabels[$method] ?? $method)
                        . ': ' . (string) $methodDetails;
                }
            }
          ?>
          <article class="outgoing-record">
            <header class="outgoing-record-heading">
              <div>
                <h3 class="record-identifier">B<?= escape_html($entry['book_number']) ?> #<?= escape_html($entry['record_number']) ?></h3>
              </div>
              <div class="record-actions">
                <a class="record-action-edit" href="<?= escape_html($editUrl) ?>">Update</a>
                <button class="record-action-print" type="button" data-print-record>Print</button>
                <form method="post" data-confirm="Remove record <?= escape_html($entry['record_number']) ?>? Its attachments will also be removed.">
                  <input type="hidden" name="csrf_token" value="<?= escape_html(csrf_token()) ?>" />
                  <input type="hidden" name="action" value="delete-outgoing-record" />
                  <input type="hidden" name="record_id" value="<?= $recordId ?>" />
                  <input type="hidden" name="return_year" value="<?= $outgoingRecordYear ?>" />
                  <input type="hidden" name="return_month" value="<?= $outgoingRecordMonth ?? '' ?>" />
                  <input type="hidden" name="return_day" value="<?= $outgoingRecordDay ?? '' ?>" />
                  <input type="hidden" name="return_search" value="<?= escape_html($outgoingRecordSearch) ?>" />
                  <input type="hidden" name="return_book" value="<?= $outgoingRecordBook ?? '' ?>" />
                  <button class="record-action-delete" type="submit">Remove</button>
                </form>
              </div>
            </header>
            <dl class="record-details">
              <div>
                <dt>Source / origin</dt>
                <dd><?= escape_html($entry['source_origin']) ?></dd>
              </div>
              <div>
                <dt>Pages / folder / binder</dt>
                <dd><?= escape_html($locationDisplay) ?></dd>
              </div>
              <div>
                <dt>Messengerial service</dt>
                <dd>
                  <?php foreach ($deliveryDisplay as $deliveryLine): ?>
                    <span class="delivery-service-item"><?= escape_html($deliveryLine) ?></span>
                  <?php endforeach; ?>
                </dd>
              </div>
              <div>
                <dt>Type of docs</dt>
                <dd><?= escape_html($entry['tdoc']) ?></dd>
              </div>
              <div>
                <dt>Addressee</dt>
                <dd><?= escape_html($entry['addressee']) ?></dd>
              </div>
              <div class="record-date-group">
                <div>
                  <dt>Date of letter</dt>
                  <dd><?= escape_html(date('M j, Y', strtotime($entry['letter_date']))) ?></dd>
                </div>
                <div>
                  <dt>Date of received</dt>
                  <dd><?= escape_html(date('M j, Y', strtotime($receivedDate))) ?></dd>
                </div>
              </div>
              <div class="record-subject">
                <dt>Subject matters</dt>
                <dd><?= escape_html($entry['subject_matter']) ?></dd>
              </div>
              <div>
                <dt>Action unit</dt>
                <dd><?= escape_html($entry['action_unit'] ?? '') ?></dd>
              </div>
              <div class="record-subject">
                <dt>Released by</dt>
                <dd><?= escape_html($entry['released_by'] ?? '') ?></dd>
              </div>
              <div>
                <dt>Remarks</dt>
                <dd><?= escape_html($entry['remarks'] ?? '') ?></dd>
              </div>
            </dl>
            <div class="record-images">
              <span>Attachments</span>
              <?php if (!empty($outgoingImages[$recordId])): ?>
                <?php foreach ($outgoingImages[$recordId] as $attachment): ?>
                  <a href="outgoing-image.php?id=<?= (int) $attachment['id'] ?>" target="_blank" rel="noopener"><?= escape_html($attachment['original_name']) ?></a>
                <?php endforeach; ?>
              <?php else: ?>
                <span>No attachments</span>
              <?php endif; ?>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</section>
