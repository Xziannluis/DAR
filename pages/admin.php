<section class="dashboard-content" aria-labelledby="admin-title">
  <header class="dashboard-header">
    <div>
      <span class="welcome-back">ADMINISTRATOR</span>
      <h1 id="admin-title">Manage accounts</h1>
      <p>Create accounts for outgoing records users.</p>
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
    <?php if (is_array($flash)): ?>
      <p class="form-message" data-kind="<?= escape_html($flash['kind'] ?? 'error') ?>" role="status"><?= escape_html($flash['message'] ?? '') ?></p>
    <?php endif; ?>
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
          <time><?= $createdAt === false ? '—' : escape_html(date('M j, Y', $createdAt)) ?></time>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
</section>
