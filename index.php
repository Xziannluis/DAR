<?php
declare(strict_types=1);

ini_set('session.use_strict_mode', '1');
$isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => $isHttps,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();
require_once __DIR__ . '/web.php';
require_once __DIR__ . '/actions/outgoing.php';

$database = application_database();
$account = current_account($database);
$flash = $_SESSION['flash_message'] ?? null;
unset($_SESSION['flash_message']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($_POST === [] && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        flash_message('The form submission is larger than the server allows. Reduce the image sizes and try again.');
        redirect_to();
    }

    $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';

    if (!csrf_is_valid()) {
        flash_message('Your form session expired. Please try again.');
        redirect_to();
    }

    if ($action === 'login') {
        $username = normalized_username($_POST['username'] ?? null);
        $password = $_POST['password'] ?? null;
        if ($username === null || !valid_password($password)) {
            flash_message('Enter a valid username and password.');
            redirect_to();
        }

        $matched = find_account($database, $username);
        if (
            $matched === null
            || !in_array($matched['role'], ['admin', 'outgoing'], true)
            || !password_verify($password, $matched['password_hash'])
        ) {
            flash_message('Username or password is incorrect.');
            redirect_to();
        }

        session_regenerate_id(true);
        $_SESSION['account_id'] = (int) $matched['id'];
        flash_message('Signed in successfully.', 'success');
        redirect_to();
    }

    if ($action === 'bootstrap-admin') {
        if ($account !== null) {
            flash_message('Sign out before setting up an administrator.');
            redirect_to();
        }

        $statement = $database->query("SELECT COUNT(*) FROM accounts WHERE role = 'admin'");
        if ($statement !== false && (int) $statement->fetchColumn() > 0) {
            flash_message('An administrator has already been created.');
            redirect_to();
        }

        $username = normalized_username($_POST['username'] ?? null);
        $password = $_POST['password'] ?? null;
        if ($username === null || !valid_password($password)) {
            flash_message('Username must be 3–32 characters, and password must be 8–128 characters.');
            redirect_to('setup=1');
        }

        $statement = $database->prepare(
            'INSERT INTO accounts (username, password_hash, role, created_at)
             VALUES (:username, :password_hash, :role, :created_at)'
        );
        try {
            $statement->execute([
                'username' => $username,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'role' => 'admin',
                'created_at' => gmdate('Y-m-d H:i:s'),
            ]);
        } catch (PDOException) {
            flash_message('That username is already in use.');
            redirect_to('setup=1');
        }

        flash_message('Administrator created. Sign in with the username and password you just set up.', 'success');
        redirect_to();
    }

    if ($action === 'create-outgoing-record') {
        if ($account === null || $account['role'] !== 'outgoing') {
            flash_message('Sign in as an outgoing user to create an outgoing record.');
            redirect_to();
        }
        save_outgoing_record($database, $account);
    }

    if ($action === 'update-outgoing-record') {
        if ($account === null || $account['role'] !== 'outgoing') {
            flash_message('Sign in as an outgoing user to update records.');
            redirect_to();
        }
        update_outgoing_record($database, $account);
    }

    if ($action === 'delete-outgoing-record') {
        if ($account === null || $account['role'] !== 'outgoing') {
            flash_message('Sign in as an outgoing user to remove records.');
            redirect_to();
        }
        delete_outgoing_record($database, $account);
    }

    if ($action === 'create-user') {
        if ($account === null || $account['role'] !== 'admin') {
            flash_message('Sign in as an administrator to create user accounts.');
            redirect_to();
        }

        $username = normalized_username($_POST['username'] ?? null);
        $password = $_POST['password'] ?? null;
        $role = $_POST['role'] ?? null;
        if (
            $username === null
            || !valid_password($password)
            || $role !== 'outgoing'
        ) {
            flash_message('Enter a valid username, an 8–128 character password, and choose Outgoing.');
            redirect_to();
        }

        $statement = $database->prepare(
            'INSERT INTO accounts (username, password_hash, role, created_at)
             VALUES (:username, :password_hash, :role, :created_at)'
        );
        try {
            $statement->execute([
                'username' => $username,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'role' => $role,
                'created_at' => gmdate('Y-m-d H:i:s'),
            ]);
        } catch (PDOException) {
            flash_message('That username is already in use.');
            redirect_to();
        }

        flash_message(ucfirst($role) . ' account "' . $username . '" created.', 'success');
        redirect_to();
    }

    if ($action === 'sign-out') {
        unset($_SESSION['account_id']);
        session_regenerate_id(true);
        flash_message('You have signed out.', 'success');
        redirect_to();
    }

    flash_message('Unknown form action.');
    redirect_to();
}

$adminCount = $database->query("SELECT COUNT(*) FROM accounts WHERE role = 'admin'");
$hasAdmin = $adminCount !== false && (int) $adminCount->fetchColumn() > 0;
$showSetup = $account === null && !$hasAdmin && isset($_GET['setup']);
$requestedOutgoingView = $_GET['view'] ?? 'dashboard';
$outgoingView = is_string($requestedOutgoingView)
    && in_array($requestedOutgoingView, ['dashboard', 'entry', 'list', 'logbook'], true)
        ? $requestedOutgoingView
        : 'dashboard';
$requestedRecordYear = filter_var($_GET['year'] ?? null, FILTER_VALIDATE_INT);
$outgoingRecordYear = in_array($outgoingView, ['list', 'logbook'], true)
    && $requestedRecordYear !== false
    && $requestedRecordYear !== null
    && $requestedRecordYear >= 1970
    && $requestedRecordYear <= (int) date('Y') + OUTGOING_YEAR_RANGE
        ? $requestedRecordYear
        : null;
$requestedRecordMonth = filter_var($_GET['month'] ?? null, FILTER_VALIDATE_INT);
$outgoingRecordMonth = $outgoingRecordYear !== null
    && $requestedRecordMonth !== false
    && $requestedRecordMonth !== null
    && $requestedRecordMonth >= 1
    && $requestedRecordMonth <= 12
        ? $requestedRecordMonth
        : null;
$requestedRecordBook = filter_var($_GET['book'] ?? null, FILTER_VALIDATE_INT);
$outgoingRecordBook = $requestedRecordYear !== null
    && $requestedRecordBook !== false
    && $requestedRecordBook !== null
    && $requestedRecordBook >= 1
    && $requestedRecordBook <= 15
        ? $requestedRecordBook
        : null;
$requestedRecordSearch = $_GET['search'] ?? '';
$outgoingRecordSearch = is_string($requestedRecordSearch)
    ? trim($requestedRecordSearch)
    : '';
$requestedEditId = filter_var($_GET['edit'] ?? null, FILTER_VALIDATE_INT);
$outgoingEditId = $outgoingRecordYear !== null
    && $requestedEditId !== false
    && $requestedEditId !== null
    && $requestedEditId > 0
        ? $requestedEditId
        : null;
$accountList = $account !== null && $account['role'] === 'admin'
    ? list_accounts($database)
    : [];
$outgoingRecordCount = $account !== null && $account['role'] === 'outgoing'
    ? count_outgoing_records($database, (int) $account['id'])
    : 0;
$outgoingYears = $account !== null
    && $account['role'] === 'outgoing'
    && in_array($outgoingView, ['list', 'logbook'], true)
        ? list_outgoing_years($database, (int) $account['id'])
        : [];
$outgoingEntries = $account !== null
    && $account['role'] === 'outgoing'
    && in_array($outgoingView, ['list', 'logbook'], true)
    && $outgoingRecordYear !== null
    && $outgoingEditId === null
    ? list_outgoing_records(
        $database,
        (int) $account['id'],
        $outgoingRecordYear,
        $outgoingRecordMonth,
        $outgoingRecordSearch,
        $outgoingRecordBook
    )
    : [];
$outgoingImages = $account !== null
    && $account['role'] === 'outgoing'
    && in_array($outgoingView, ['list', 'logbook'], true)
    && $outgoingRecordYear !== null
    ? list_outgoing_images(
        $database,
        (int) $account['id'],
        $outgoingRecordYear,
        $outgoingRecordMonth,
        $outgoingRecordSearch
    )
    : [];
$outgoingEditingEntry = $account !== null
    && $account['role'] === 'outgoing'
    && $outgoingView === 'list'
    && $outgoingRecordYear !== null
    && $outgoingEditId !== null
        ? find_outgoing_record(
            $database,
            (int) $account['id'],
            $outgoingEditId,
            $outgoingRecordYear
        )
        : null;
$outgoingPageTitles = [
    'dashboard' => 'Dashboard',
    'entry' => 'Outgoing Entry',
    'list' => 'Outgoing List',
    'logbook' => 'Logbook',
];
$pageTitle = $account !== null && $account['role'] === 'outgoing'
    ? $outgoingPageTitles[$outgoingView] . ' | DAR-RMS'
    : 'Sign in | DAR-RMS';
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="description" content="Sign in to the DAR-RMS outgoing records workspace." />
    <title><?= escape_html($pageTitle) ?></title>
    <link rel="icon" type="image/png" href="assets/dar-rms-logo.png" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="styles.css?v=<?= (int) filemtime(__DIR__ . '/styles.css') ?>" />
    <script src="script.js?v=<?= (int) filemtime(__DIR__ . '/script.js') ?>" defer></script>
  </head>
  <body class="<?= $account === null ? 'login-background' : 'dashboard-background' ?>">
    <main class="page-shell<?= $account !== null ? ' page-shell-dashboard' : '' ?>">
      <section class="welcome-panel<?= $account !== null && $account['role'] === 'outgoing' ? ' workspace-sidebar' : '' ?>" aria-label="<?= $account !== null && $account['role'] === 'outgoing' ? 'Outgoing workspace navigation' : 'About DAR-RMS' ?>">
        <a class="brand brand-light" href="index.php" aria-label="DAR-RMS home">
          <img class="brand-logo" src="assets/dar-rms-logo.png" alt="" />
          <span class="brand-copy">
            <span class="brand-name">DAR-RMS</span>
            <span class="brand-description">DAR (RECORD MANAGEMENT SYSTEM)</span>
          </span>
        </a>

        <?php if ($account !== null && $account['role'] === 'outgoing'): ?>
          <nav class="workspace-nav" aria-label="Outgoing sections">
            <a href="index.php?view=dashboard"<?= $outgoingView === 'dashboard' ? ' aria-current="page"' : '' ?>>
              <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="3.5" y="3.5" width="7" height="7" rx="1.5" /><rect x="13.5" y="3.5" width="7" height="7" rx="1.5" /><rect x="3.5" y="13.5" width="7" height="7" rx="1.5" /><rect x="13.5" y="13.5" width="7" height="7" rx="1.5" /></svg>
              <span>Dashboard</span>
            </a>
            <a href="index.php?view=entry"<?= $outgoingView === 'entry' ? ' aria-current="page"' : '' ?>>
              <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 20h9" /><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L8 18l-4 1 1-4Z" /></svg>
              <span>Outgoing Entry</span>
            </a>
            <a href="index.php?view=list"<?= $outgoingView === 'list' ? ' aria-current="page"' : '' ?>>
              <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M8 6h12M8 12h12M8 18h12" /><circle cx="4" cy="6" r=".7" /><circle cx="4" cy="12" r=".7" /><circle cx="4" cy="18" r=".7" /></svg>
              <span>List</span>
            </a>
            <a href="index.php?view=logbook"<?= $outgoingView === 'logbook' ? ' aria-current="page"' : '' ?>>
              <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6 4.5h12a2 2 0 0 1 2 2v13H8a2 2 0 0 1-2-2v-13Z" /><path d="M6 4.5v13a2 2 0 0 1-2 2M10 8h6m-6 4h6m-6 4h4" /></svg>
              <span>Logbook</span>
            </a>
          </nav>
        <?php else: ?>
          <div class="welcome-copy">
            <span class="eyebrow"><span class="status-dot"></span> YOUR WORKSPACE, IN SYNC</span>
            <h1>Every movement.<br />One clear picture.</h1>
            <p>Keep outgoing records organized, accurate, and easy to find.</p>
          </div>
        <?php endif; ?>

        <div class="panel-footer">
          
        </div>
        <span class="decorative-orb orb-one" aria-hidden="true"></span>
        <span class="decorative-orb orb-two" aria-hidden="true"></span>
      </section>

      <section class="login-panel" aria-label="Account access">
        <?php if ($account === null): ?>
          <button class="theme-toggle" type="button" aria-label="Toggle dark mode" aria-pressed="false" title="Toggle dark mode" data-theme-toggle>
            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M9 3h6l1.2 4H7.8L9 3Z" fill="currentColor" /><path d="M8 7h8v11l-4 3-4-3V7Z" /><path d="M10 11h4M12 2V1M7 2 5.8 1M17 2l1.2-1" /></svg>
          </button>
        <?php endif; ?>
        <?php if ($account === null): ?>
          <span class="decorative-orb orb-one login-orb-one" aria-hidden="true"></span>
          <span class="decorative-orb orb-two login-orb-two" aria-hidden="true"></span>
        <?php endif; ?>
        <div class="mobile-brand-wrap">
          <a class="brand brand-dark" href="index.php" aria-label="DAR-RMS home">
            <img class="brand-logo" src="assets/dar-rms-logo.png" alt="" />
            <span class="brand-copy">
              <span class="brand-name">DAR-RMS</span>
              <span class="brand-description">DAR (RECORD MANAGEMENT SYSTEM)</span>
            </span>
          </a>
        </div>

        <?php if ($account !== null && $account['role'] === 'admin'): ?>
          <?php require __DIR__ . '/pages/admin.php'; ?>
        <?php elseif ($account !== null && $account['role'] === 'outgoing'): ?>
          <?php require __DIR__ . '/pages/outgoing.php'; ?>
        <?php elseif ($showSetup): ?>
          <div class="login-content">
            <div class="login-heading">
              <span class="welcome-back">ONE-TIME SETUP</span>
              <h1>Create administrator</h1>
              <p>Create the first local administrator account.</p>
            </div>
            <form method="post">
              <input type="hidden" name="csrf_token" value="<?= escape_html(csrf_token()) ?>" />
              <input type="hidden" name="action" value="bootstrap-admin" />
              <div class="form-field">
                <label for="setup-username">Admin username</label>
                <input id="setup-username" name="username" type="text" autocomplete="username" minlength="3" maxlength="32" pattern="[A-Za-z0-9]+(([._]|-)[A-Za-z0-9]+)*" required />
              </div>
              <div class="form-field">
                <label for="setup-password">Admin password</label>
                <div class="input-wrap">
                  <input id="setup-password" name="password" type="password" autocomplete="new-password" minlength="8" maxlength="128" required />
                  <button class="password-toggle" type="button" aria-label="Show password" aria-pressed="false" data-password-toggle="setup-password">
                    <svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M2.5 10s2.7-5 7.5-5 7.5 5 7.5 5-2.7 5-7.5 5-7.5-5-7.5-5Z" /><circle cx="10" cy="10" r="2" /></svg>
                  </button>
                </div>
              </div>
              <button class="submit-button" type="submit"><span>Create administrator</span></button>
              <?php if (is_array($flash)): ?>
                <p class="form-message" data-kind="<?= escape_html($flash['kind'] ?? 'error') ?>" role="status"><?= escape_html($flash['message'] ?? '') ?></p>
              <?php endif; ?>
            </form>
            <a class="text-link back-link" href="index.php">Back to sign in</a>
          </div>
        <?php else: ?>
          <div class="login-content">
            <div class="login-heading">
              <span class="welcome-back">WELCOME BACK</span>
              <h1 id="login-title">Sign in to your account</h1>
              <p>Enter your username and password to continue.</p>
            </div>
            <form method="post">
              <input type="hidden" name="csrf_token" value="<?= escape_html(csrf_token()) ?>" />
              <input type="hidden" name="action" value="login" />
              <div class="form-field">
                <label for="username">Username</label>
                <input id="username" name="username" type="text" placeholder="Enter your username" autocomplete="username" minlength="3" maxlength="32" pattern="[A-Za-z0-9]+(([._]|-)[A-Za-z0-9]+)*" required />
              </div>
              <div class="form-field">
                <label for="password">Password</label>
                <div class="input-wrap">
                  <input id="password" name="password" type="password" placeholder="Enter your password" autocomplete="current-password" minlength="8" maxlength="128" required />
                  <button class="password-toggle" type="button" aria-label="Show password" aria-pressed="false" data-password-toggle="password">
                    <svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M2.5 10s2.7-5 7.5-5 7.5 5 7.5 5-2.7 5-7.5 5-7.5-5-7.5-5Z" /><circle cx="10" cy="10" r="2" /></svg>
                  </button>
                </div>
              </div>
              <button class="submit-button" type="submit">
                <span>Sign in</span>
                <svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M4 10h12m-5-5 5 5-5 5" /></svg>
              </button>
              <?php if (is_array($flash)): ?>
                <p class="form-message" data-kind="<?= escape_html($flash['kind'] ?? 'error') ?>" role="status"><?= escape_html($flash['message'] ?? '') ?></p>
              <?php endif; ?>
            </form>
            <div class="login-bottom">
              <p>Need an account? Ask your administrator to get access.</p>
              <div class="secure-note">
                <svg viewBox="0 0 16 16" fill="none" aria-hidden="true"><rect x="3.5" y="7" width="9" height="7" rx="1.5" /><path d="M5.5 7V5a2.5 2.5 0 0 1 5 0v2" /></svg>
                <span>Your data is protected and secure</span>
              </div>
            </div>
          </div>
        <?php endif; ?>
        <span class="login-copyright">© 2026 DAR-RMS. All rights reserved.</span>
      </section>
    </main>
  </body>
</html>
