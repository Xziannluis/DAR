<section class="outgoing-summary dashboard-overview" aria-label="Outgoing record overview">
  <div class="summary-card">
    <span class="summary-label">Total records entered</span>
    <strong class="summary-count"><?= number_format($outgoingRecordCount) ?></strong>
    <p>Outgoing records saved to your account</p>
  </div>

  <div class="dashboard-overview-side">
    <div class="dashboard-chart-card" aria-label="Records overview graphic">
      <svg viewBox="0 0 360 150" role="img" aria-label="Decorative rising records graph">
        <defs>
          <linearGradient id="records-chart-fill" x1="0" x2="0" y1="0" y2="1">
            <stop offset="0" stop-color="#7bbd83" stop-opacity=".34" />
            <stop offset="1" stop-color="#7bbd83" stop-opacity="0" />
          </linearGradient>
          <linearGradient id="records-chart-line" x1="0" x2="1" y1="0" y2="0">
            <stop offset="0" stop-color="#7bbd83" />
            <stop offset=".72" stop-color="#a4ce77" />
            <stop offset="1" stop-color="#d6bd48" />
          </linearGradient>
        </defs>
        <path class="chart-area" d="M8 117 C36 104 47 82 72 91 S108 110 130 76 S166 104 194 71 S232 77 252 51 S286 72 307 36 S337 51 352 12 V150 H8Z" />
        <path class="chart-line" d="M8 117 C36 104 47 82 72 91 S108 110 130 76 S166 104 194 71 S232 77 252 51 S286 72 307 36 S337 51 352 12" />
        <path class="chart-bar" d="M67 105v37M137 94v48M209 81v61M280 61v81M330 43v99" />
      </svg>
    </div>

    <section class="recent-activity-card" aria-labelledby="recent-activity-title">
      <h2 id="recent-activity-title">Recent Activity</h2>
      <?php if ($outgoingRecentEntries === []): ?>
        <p class="recent-activity-empty">Your recent records will appear here.</p>
      <?php else: ?>
        <ul>
          <?php foreach ($outgoingRecentEntries as $recentEntry): ?>
            <li>
              <span class="activity-icon" aria-hidden="true">
                <svg viewBox="0 0 20 20" fill="none"><rect x="3.5" y="5" width="13" height="11.5" rx="2" /><path d="M6.5 3.5v3M13.5 3.5v3M3.5 8.5h13" /></svg>
              </span>
              <span class="activity-copy">
                <strong>Record <?= escape_html((string) $recentEntry['record_number']) ?></strong>
                <time datetime="<?= escape_html((string) $recentEntry['date_sent']) ?>"><?= escape_html(date('M j, Y', strtotime((string) $recentEntry['date_sent']))) ?></time>
              </span>
              <span class="activity-book">Book <?= escape_html((string) $recentEntry['book_number']) ?></span>
              <a class="activity-view-button" href="index.php?<?= escape_html(http_build_query([
                'view' => 'list',
                'year' => (int) $recentEntry['record_year'],
                'book' => (int) $recentEntry['book_number'],
                'search' => (string) $recentEntry['record_number'],
              ])) ?>">View</a>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>
  </div>
</section>
