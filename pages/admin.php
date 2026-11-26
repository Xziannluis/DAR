<?php
$adminPageDetails = [
    'accounts' => ['Manage Accounts', 'Create accounts for outgoing records users.'],
    'import' => ['Import Backup', 'Restore outgoing records and their attached files from a DAR-RMS backup.'],
    'export' => ['Export Backup', 'Download a streaming backup of all outgoing records and their attached files.'],
][$adminView];
?>
<section class="dashboard-content admin-content" aria-labelledby="admin-title">
  <header class="dashboard-header">
    <div>
      <span class="welcome-back">ADMINISTRATOR</span>
      <h1 id="admin-title"><?= escape_html($adminPageDetails[0]) ?></h1>
      <p><?= escape_html($adminPageDetails[1]) ?></p>
    </div>
    <div class="dashboard-header-actions">
      <button class="theme-toggle" type="button" aria-label="Toggle dark mode" aria-pressed="false" title="Toggle dark mode" data-theme-toggle>
        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M9 3h6l1.2 4H7.8L9 3Z" fill="currentColor" /><path d="M8 7h8v11l-4 3-4-3V7Z" /><path d="M10 11h4M12 2V1M7 2 5.8 1M17 2l1.2-1" /></svg>
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

  <?php if ($adminView === 'accounts'): ?>
  <form class="account-form" method="post">
    <input type="hidden" name="csrf_token" value="<?= escape_html(csrf_token()) ?>" />
    <input type="hidden" name="action" value="create-user" />
    <h2>Create a user account</h2>
    <div class="account-fields">
      <div class="form-field">
        <label for="new-username">Username</label>
        <input id="new-username" name="username" type="text" autocomplete="off" minlength="3" maxlength="32" pattern="[A-Za-z0-9]+(([._]|-)[A-Za-z0-9]+)*" required />
      </div>
      <div class="form-field">
        <label for="new-password">Temporary password</label>
        <div class="input-wrap">
          <input id="new-password" name="password" type="password" autocomplete="new-password" minlength="8" maxlength="128" required />
          <button class="password-toggle" type="button" aria-label="Show password" aria-pressed="false" data-password-toggle="new-password">
            <svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M2.5 10s2.7-5 7.5-5 7.5 5 7.5 5-2.7 5-7.5 5-7.5-5-7.5-5Z" /><circle cx="10" cy="10" r="2" /></svg>
          </button>
        </div>
      </div>
      <div class="form-field">
        <label for="new-role">Account type</label>
        <select id="new-role" name="role" required>
          <option value="outgoing">Outgoing</option>
        </select>
      </div>
    </div>
    <button class="submit-button" type="submit"><span>Create account</span></button>
  </form>

  <div class="account-list-section">
    <div class="list-heading">
      <h2>User accounts</h2>
      <span><?= count($accountList) ?> <?= count($accountList) === 1 ? 'account' : 'accounts' ?></span>
    </div>
    <ul class="account-list">
      <?php foreach ($accountList as $listed): ?>
        <?php
          $role = (string) $listed['role'];
          $roleLabel = $role === 'admin' ? 'Administrator' : ucfirst($role);
          $createdAt = strtotime((string) $listed['created_at']);
        ?>
        <li>
          <div class="account-identity">
            <strong><?= escape_html($listed['username']) ?></strong>
            <span class="role-badge role-<?= escape_html($role) ?>"><?= escape_html($roleLabel) ?></span>
          </div>
          <div class="account-actions">
            <time><?= $createdAt === false ? '—' : escape_html(date('M j, Y', $createdAt)) ?></time>
            <button class="account-reset-trigger" type="button" data-reset-dialog="reset-password-dialog-<?= (int) $listed['id'] ?>">Reset password</button>
          </div>
          <dialog class="account-reset-dialog" id="reset-password-dialog-<?= (int) $listed['id'] ?>" aria-labelledby="reset-password-title-<?= (int) $listed['id'] ?>">
            <form method="post">
              <input type="hidden" name="csrf_token" value="<?= escape_html(csrf_token()) ?>" />
              <input type="hidden" name="action" value="reset-user-password" />
              <input type="hidden" name="account_id" value="<?= (int) $listed['id'] ?>" />
              <h2 id="reset-password-title-<?= (int) $listed['id'] ?>">Reset password</h2>
              <p>Set a new password for <strong><?= escape_html($listed['username']) ?></strong>.</p>
              <label for="reset-password-<?= (int) $listed['id'] ?>">New password</label>
              <input id="reset-password-<?= (int) $listed['id'] ?>" name="new_password" type="password" autocomplete="new-password" minlength="8" maxlength="128" placeholder="At least 8 characters" required />
              <div class="account-reset-dialog-actions">
                <button class="secondary-button" type="button" data-close-dialog>Cancel</button>
                <button class="submit-button" type="submit"><span>Save password</span></button>
              </div>
            </form>
          </dialog>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
  <?php elseif ($adminView === 'import'): ?>
    <section class="account-form backup-panel" aria-labelledby="backup-import-title">
      <h2 id="backup-import-title">Import outgoing records</h2>
      <p class="backup-description">Choose a DAR-RMS streaming backup to restore outgoing records and their attachments. Existing records with the same account, book, year, and record number will be skipped.</p>
      <form class="backup-upload-form" method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= escape_html(csrf_token()) ?>" />
        <input type="hidden" name="action" value="import-outgoing-backup" />
        <div class="form-field">
          <label for="backup-file">Backup file</label>
          <input id="backup-file" name="backup_file" type="file" accept=".jsonl,.ndjson,.json,application/json,application/x-ndjson" required />
          <small>Use a DAR-RMS .jsonl backup file, up to 2 GB. Older .json backups up to 35 MB are also supported. Matching outgoing accounts and enough temporary disk space must be available.</small>
        </div>
        <button class="submit-button" type="submit"><span>Import backup</span></button>
      </form>
    </section>
  <?php else: ?>
    <section class="account-form backup-panel" aria-labelledby="backup-export-title">
      <h2 id="backup-export-title">Export a full outgoing backup</h2>
      <p class="backup-description">The streaming backup includes all outgoing records, record dates, messenger-service details, subjects, account ownership, and attached files. Account passwords are never included.</p>
      <dl class="backup-includes">
        <div><dt>Outgoing records</dt><dd>All accounts, books, and years</dd></div>
        <div><dt>Attachments</dt><dd>Included with each record</dd></div>
        <div><dt>File type</dt><dd>Streaming JSON Lines (.jsonl), up to 2 GB</dd></div>
      </dl>
      <a class="submit-button backup-download-button" href="index.php?admin_view=export&amp;admin_export=download">
        <span>Download backup</span>
        <svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M10 2.5v10m0 0 3.5-3.5M10 12.5 6.5 9M3.5 14v2.5h13V14" /></svg>
      </a>
    </section>
  <?php endif; ?>
</section>
