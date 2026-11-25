<?php
$monthNames = [
    1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
    5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
    9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December',
];
?>
<section class="logbook-section" aria-labelledby="logbook-records-title">
  <?php if ($outgoingRecordYear === null): ?>
    <?php if ($outgoingYears === []): ?>
      <p class="empty-records">Your saved outgoing records will appear in the logbook here.</p>
    <?php else: ?>
      <div class="year-card-grid">
        <?php foreach ($outgoingYears as $yearGroup): ?>
          <a class="year-card" href="index.php?view=logbook&amp;year=<?= (int) $yearGroup['record_year'] ?>">
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
    <div class="logbook-toolbar">
      <a class="year-list-back" href="index.php?view=logbook">All years</a>
      <div class="logbook-toolbar-actions">
        <button class="secondary-button" type="button" data-print-logbook>Print</button>
        <a class="year-list-back" href="index.php?view=list&amp;year=<?= escape_html($outgoingRecordYear) ?>">Manage records</a>
      </div>
    </div>
    <form class="month-filter" method="get">
      <input type="hidden" name="view" value="logbook" />
      <input type="hidden" name="year" value="<?= escape_html($outgoingRecordYear) ?>" />
      <div class="record-filter-field">
        <label for="logbook-book-filter">Book</label>
        <select id="logbook-book-filter" name="book">
          <option value="">All books</option>
          <?php for ($bookNumber = 1; $bookNumber <= 15; $bookNumber++): ?>
            <option value="<?= $bookNumber ?>"<?= $outgoingRecordBook === $bookNumber ? ' selected' : '' ?>>Book <?= $bookNumber ?></option>
          <?php endfor; ?>
        </select>
      </div>
      <div class="record-filter-field">
        <label for="logbook-month-filter">Date received month</label>
        <select id="logbook-month-filter" name="month">
          <option value="">All months</option>
          <?php foreach ($monthNames as $monthNumber => $monthName): ?>
            <option value="<?= $monthNumber ?>"<?= $outgoingRecordMonth === $monthNumber ? ' selected' : '' ?>><?= escape_html($monthName) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="record-filter-field record-search-field">
        <label for="logbook-search">Search name or record number</label>
        <input class="record-search-input" id="logbook-search" name="search" type="search" maxlength="100" value="<?= escape_html($outgoingRecordSearch) ?>" placeholder="Enter a name or record number" />
      </div>
      <button class="secondary-button" type="submit">Apply filters</button>
      <?php if ($outgoingRecordBook !== null || $outgoingRecordMonth !== null || $outgoingRecordSearch !== ''): ?>
        <a class="year-list-back" href="index.php?view=logbook&amp;year=<?= escape_html($outgoingRecordYear) ?>">Clear filters</a>
      <?php endif; ?>
    </form>

    <?php if ($outgoingEntries === []): ?>
      <p class="empty-records">No logbook records match the selected filters.</p>
    <?php else: ?>
      <p class="year-record-count">Showing up to 50<?= $outgoingRecordBook === null && $outgoingRecordSearch === '' ? '' : ' matching' ?> records.</p>
      <div class="logbook-table-scroll">
        <table class="logbook-table">
          <caption>Book : <?= $outgoingRecordBook === null ? 'All Books' : 'B' . (int) $outgoingRecordBook ?></caption>
          <thead>
            <tr>
              <th scope="col">Doc. Control No.</th>
              <th scope="col">Date of Letter</th>
              <th scope="col">Date Rec'd</th>
              <th scope="col">Rec'd From</th>
              <th scope="col">Addressee</th>
              <th scope="col">Subject</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($outgoingEntries as $entry): ?>
              <?php
                $locationLabels = ['pages' => 'Pages', 'folder' => 'Folder', 'binder' => 'Binder'];
                $locationType = (string) $entry['location_type'];
                $locationDetails = trim((string) $entry['location_details']);
                $locationDisplay = isset($locationLabels[$locationType]) && $locationDetails !== ''
                    ? $locationLabels[$locationType] . ': ' . $locationDetails
                    : ($locationDetails !== '' ? $locationDetails : ($locationLabels[$locationType] ?? $locationType));
                $serviceNames = [
                    'handcarry' => 'Handcarry', 'email' => 'Email', 'jrs' => 'JRS',
                    'philpost' => 'PhilPost', 'odts' => 'ODTS', 'lbc' => 'LBC',
                ];
                $methods = explode(',', (string) $entry['delivery_method']);
                $detailsByMethod = json_decode((string) $entry['delivery_details'], true);
                if (!is_array($detailsByMethod)) {
                    $detailsByMethod = $methods === [] ? [] : [$methods[0] => (string) $entry['delivery_details']];
                }
              ?>
              <tr>
                <td class="logbook-control-number">
                  <strong>B<?= escape_html($entry['book_number']) ?>#<?= escape_html($entry['record_number']) ?>-<?= escape_html($entry['record_year']) ?></strong>
                  <span><?= escape_html($locationDisplay) ?></span>
                  <?php foreach ($methods as $method): ?>
                    <?php $methodDetails = $detailsByMethod[$method] ?? ''; ?>
                    <span>
                      <?= escape_html($serviceNames[$method] ?? $method) ?>
                      <?php if (in_array($method, ['handcarry', 'email', 'odts'], true)): ?>
                      <?php elseif (is_array($methodDetails)): ?>
                        <?php if ($method === 'handcarry'): ?>
                          · <?= escape_html($methodDetails['handcarry_name'] ?? '') ?> · <?= escape_html($methodDetails['receiver_name'] ?? '') ?>
                        <?php else: ?>
                          · <?= escape_html($methodDetails['reference'] ?? '') ?> · <?= escape_html($methodDetails['receiver_name'] ?? '') ?>
                        <?php endif; ?>
                      <?php elseif ($methodDetails !== ''): ?>
                        · <?= escape_html($methodDetails) ?>
                      <?php endif; ?>
                    </span>
                  <?php endforeach; ?>
                </td>
                <td><?= escape_html(date('Y-m-d', strtotime($entry['letter_date']))) ?></td>
                <td><?= escape_html(date('Y-m-d', strtotime($entry['date_sent']))) ?></td>
                <td><?= escape_html($entry['source_origin']) ?></td>
                <td><?= escape_html($entry['addressee']) ?></td>
                <td><?= escape_html($entry['subject_matter']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</section>
