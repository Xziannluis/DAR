<?php
$outgoingViewContent = [
    'dashboard' => [
        'title' => 'Dashboard',
        'description' => 'Check how many outgoing records you have entered.',
    ],
    'entry' => [
        'title' => 'Outgoing Entry',
        'description' => 'Record sent correspondence and attach scanned images.',
    ],
    'list' => [
        'title' => $outgoingEditId !== null
            ? 'Update Outgoing Record'
            : ($outgoingRecordYear === null
                ? 'Outgoing List'
                : $outgoingRecordYear . ' Outgoing Records'),
        'description' => $outgoingEditId !== null
            ? 'Edit the outgoing record details.'
            : ($outgoingRecordYear === null
                ? 'Choose a year to review your saved outgoing records.'
                : ''),
    ],
    'logbook' => [
        'title' => $outgoingRecordYear === null
            ? 'Logbook'
            : 'Logbook · ' . $outgoingRecordYear,
        'description' => $outgoingRecordYear === null
            ? 'Choose a year to review the logbook.'
            : '',
    ],
][$outgoingView];
?>
<section class="dashboard-content outgoing-dashboard" aria-labelledby="outgoing-title">
  <header class="dashboard-header">
    <div>
      <span class="welcome-back">OUTGOING RECORDS</span>
      <h1 id="outgoing-title"><?= escape_html($outgoingViewContent['title']) ?></h1>
      <?php if ($outgoingViewContent['description'] !== ''): ?>
        <p><?= escape_html($outgoingViewContent['description']) ?></p>
      <?php endif; ?>
    </div>
    <div class="dashboard-header-actions">
      <button class="theme-toggle" type="button" aria-label="Toggle dark mode" aria-pressed="false" title="Toggle dark mode" data-theme-toggle>
        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M8 4h8l1 4H7l1-4Z" /><path d="M8 8h8v11l-4 2-4-2V8Z" /><path d="M10 12h4" /></svg>
      </button>
      <form method="post">
        <input type="hidden" name="csrf_token" value="<?= escape_html(csrf_token()) ?>" />
        <input type="hidden" name="action" value="sign-out" />
        <button class="secondary-button" type="submit">Sign out</button>
      </form>
    </div>
  </header>

  <?php if (is_array($flash)): ?>
    <p class="form-message" data-kind="<?= escape_html($flash['kind'] ?? 'error') ?>" role="status"><?= escape_html($flash['message'] ?? '') ?></p>
  <?php endif; ?>

  <?php if ($outgoingView === 'dashboard'): ?>
    <?php require __DIR__ . '/outgoing-dashboard.php'; ?>
  <?php elseif ($outgoingView === 'entry'): ?>
    <?php require __DIR__ . '/outgoing-entry.php'; ?>
  <?php elseif ($outgoingView === 'logbook'): ?>
    <?php require __DIR__ . '/outgoing-logbook.php'; ?>
  <?php else: ?>
    <?php require __DIR__ . '/outgoing-list.php'; ?>
  <?php endif; ?>
</section>
