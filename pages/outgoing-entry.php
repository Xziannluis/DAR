<form class="account-form outgoing-entry-form" method="post" enctype="multipart/form-data">
  <input type="hidden" name="csrf_token" value="<?= escape_html(csrf_token()) ?>" />
  <input type="hidden" name="action" value="create-outgoing-record" />
  <h2>New outgoing record</h2>
  <div class="outgoing-fields">
    <div class="form-field">
      <label for="book-number">Select book</label>
      <select id="book-number" name="book_number" required>
        <option value="">Choose a book</option>
        <?php for ($book = 1; $book <= 15; $book++): ?>
          <option value="<?= $book ?>">Book <?= $book ?></option>
        <?php endfor; ?>
      </select>
    </div>
    <div class="form-field">
      <label for="record-number">Record number</label>
      <input id="record-number" name="record_number" type="text" maxlength="100" required />
    </div>
    <div class="form-field">
      <label for="record-year">Year</label>
      <select id="record-year" name="record_year" required>
        <option value="">Choose a year</option>
        <?php for ($year = (int) date('Y'); $year <= (int) date('Y') + OUTGOING_YEAR_RANGE; $year++): ?>
          <option value="<?= $year ?>"<?= $year === (int) date('Y') ? ' selected' : '' ?>><?= $year ?></option>
        <?php endfor; ?>
      </select>
    </div>
    <div class="form-field">
      <label for="source-origin">Source / origin</label>
      <input id="source-origin" name="source_origin" type="text" maxlength="200" required />
    </div>
    <div class="form-field">
      <label for="location-type">Pages / folder / binder</label>
      <input id="location-type" name="location_type" type="text" maxlength="200" placeholder="Enter pages, folder, or binder details" required />
    </div>
    <div class="form-field">
      <label for="delivery-method">Messengerial service</label>
      <div id="delivery-service-rows" class="service-picker-rows" data-service-picker data-initial-methods="[]" data-initial-details="{}">
        <div class="service-picker-row service-picker-fallback">
          <select name="delivery_methods[]" required>
            <option value="" selected disabled>Choose a messengerial service</option>
            <option value="handcarry">Handcarry</option><option value="email">Email</option>
            <option value="jrs">JRS</option><option value="philpost">PhilPost</option><option value="odts">ODTS</option>
          </select>
        </div>
      </div>
      <button class="secondary-button add-service-button" type="button" data-add-service="delivery-service-rows" hidden>Add another messengerial service</button>
    </div>
    <div class="form-field">
      <label for="tdoc">T/Doc</label>
      <input id="tdoc" name="tdoc" type="text" maxlength="200" required />
    </div>
    <div class="form-field">
      <label for="addressee">Addressee</label>
      <input id="addressee" name="addressee" type="text" maxlength="200" required />
    </div>
    <div class="outgoing-date-fields">
      <div class="form-field">
        <label for="letter-date">Date of letter</label>
        <input id="letter-date" name="letter_date" type="date" required />
      </div>
      <div class="form-field">
        <label for="date-received">Date of received</label>
        <input id="date-received" name="date_sent" type="date" required />
      </div>
    </div>
    <div class="form-field">
      <label for="action-unit">Action unit</label>
      <input id="action-unit" name="action_unit" type="text" maxlength="200" />
    </div>
    <div class="form-field">
      <label for="released-by">Released by</label>
      <input id="released-by" name="released_by" type="text" maxlength="200" />
    </div>
    <div class="form-field outgoing-field-wide">
      <label for="remarks">Remarks</label>
      <input id="remarks" name="remarks" type="text" maxlength="2000" />
    </div>
    <div class="form-field outgoing-field-wide">
      <label for="subject-matter">Subject matters</label>
      <textarea id="subject-matter" name="subject_matter" rows="3" maxlength="2000" required></textarea>
    </div>
    <div class="form-field outgoing-field-wide">
      <label for="outgoing-attachments">Attached files <span class="optional-label">(optional)</span></label>
      <input id="outgoing-attachments" name="attachments[]" type="file" accept="image/jpeg,image/png,image/webp,image/gif,application/pdf,.pdf" multiple />
      <small>Attach up to 10 JPEG, PNG, WebP, GIF, or PDF files. Maximum 8 MB each and 20 MB total.</small>
    </div>
  </div>
  <button class="submit-button" type="submit"><span>Save outgoing record</span></button>
</form>
