### Project Docs: Runbook for All Scripts

- **Runtime**: PHP 8.1+ with extensions `curl`, `pdo_sqlite`, `fileinfo`; SQLite 3; a web server (PHP’s built-in server is fine) for browser demos.
- **Database**: `sales.db` SQLite file created in the `Solutions` folder on-demand.
- **Folder layout**: keep all files together as provided; uploads save into `Solutions/uploads/`.

---

### Quick Start

- Install PHP 8+ and SQLite.
- Ensure PHP extensions are enabled: `curl`, `pdo_sqlite`, `fileinfo`.
- Optional: export Google Maps key for distance script:
  - Linux/macOS:
```bash
# illustrative only
export GOOGLE_MAPS_API_KEY="AIzaSyBv0hnS3pfteHN1XbAXFrrfs-PV3aX1C8Y"
```
  - Windows (PowerShell):
```powershell
# illustrative only
setx GOOGLE_MAPS_API_KEY "AIzaSyBv0hnS3pfteHN1XbAXFrrfs-PV3aX1C8Y"
```
- Start a local server for browser-based pages:
```bash
# illustrative only
php -S 127.0.0.1:8000 -t "/media/manish/New Volume10/Desktop/Test Task/Make It Simple - MIS - Ankur Agarwal/Solutions"
```
Then open `http://127.0.0.1:8000/<file>` in a browser.

---

### Task-to-File Map

- (1) Distance between pickup, waypoints, drop-off → `distance_calculator.php`
- (2) Prime numbers 1…100 → `primes.php`
- (3) Sum quantity from JSON and insert into DB → `import_sales.php` (+ `sales.db`)
- (4) Dynamic form add/delete rows + DB insert → `dynamic_form.php` (+ `sales.db`)
- (5) Bootstrap form with jQuery validation + auto image upload → `bootstrap_form.php`, `upload_image.php`, `uploads/`
- (6) Sum of series 0/1 + 1/2 + 2/3 + … → `series_sum.php`
- (7) Weekly/monthly charts from DB → `sales_charts.php` (+ `sales.db`, optional `seed_sales.php`)
- (8) jQuery grid: parse comma/@-separated string to table → `jquery_grid_test.html`

---

### 1) Distance Calculator
- **File**: `distance_calculator.php`
- **Purpose**: Compute total driving distance across origin → waypoints → destination using Google Directions API (metric units).
- **Inputs**
  - CLI positional strings: at least 2 addresses.
  - Optional flag `--key=YOUR_API_KEY` (falls back to `GOOGLE_MAPS_API_KEY` env var or embedded key).
- **Output**
  - Prints “Total driving distance: X.XX km (Y m)” and a per-leg breakdown.
- **Dependencies**
  - PHP ext `curl`; Internet access; Google Maps Directions API enabled on the API key.
- **Run**
```bash
# illustrative only
php distance_calculator.php --key="AIzaSyBv0hnS3pfteHN1XbAXFrrfs-PV3aX1C8Y" \
  "Pickup Location" "Location 2" "Location 3" "Drop-off location"
```
  Example:
```bash
# illustrative only
php distance_calculator.php "Delhi Airport" "Connaught Place, New Delhi" "India Gate, New Delhi" "Taj Mahal, Agra"
```
- **Notes**
  - Waypoints are kept in the order provided (no optimization).
  - API usage may require billing to be enabled in Google Cloud.

---

### 2) Prime Numbers 1–100
- **File**: `primes.php`
- **Purpose**: Print prime numbers between 1 and 100.
- **Inputs**: none.
- **Output**: single line list like “2, 3, 5, 7, 11, …”.
- **Run**
```bash
# illustrative only
php primes.php
```

---

### 3) Import Sales JSON and Sum Quantity
- **File**: `import_sales.php`
- **Purpose**: Fetch JSON from `https://apimis.in/api/jsonAPITest.php`, calculate total item `qty`, and store master/items into `sales.db`.
- **Schema** (created automatically if missing)
  - `sales_master(master_id, date, invoice_number, party_code, party_name, gst_no, party_group)`
  - `sales_item(master_id, item_code, item_name, item_group, sub_group, qty, unit, price_without_gst, amount_without_gst, gst_amount, amount_with_gst)`
- **Inputs**
  - None for default import; optional:
    - `--verify`: show aggregate counts/sums from DB.
    - `--fetch=<MasterId>`: dump items JSON for a specific master id.
- **Outputs**
  - On import: total quantity, DB path; then a verify summary: row counts, qty sum, and sample sales list.
  - On `--verify`: same summary without fetching new data.
  - On `--fetch`: JSON of items for `master_id`.
- **Dependencies**
  - PHP ext `curl`, `pdo_sqlite`; Internet access.
- **Run**
```bash
# illustrative only
php import_sales.php           # fetch, insert, and then verify
php import_sales.php --verify  # verify only
php import_sales.php --fetch=SOME_MASTER_ID
```
- **Notes**
  - The script sanitizes numeric strings and tolerates minor payload anomalies.
  - Reruns are idempotent for `sales_master` on `master_id` (uses INSERT OR IGNORE).

---

### 4) Dynamic Form: Add/Delete Rows + Insert to DB
- **File**: `dynamic_form.php`
- **Purpose**: Browser UI to add/remove rows (Name, Age, Job) and submit; data saved to SQLite table `people`.
- **Table** (auto-created): `people(id, name, age, job)`
- **Inputs**: Web form fields `rows[i][name|age|job]`.
- **Outputs**
  - Flash message with count of inserted rows.
  - Table of the last 50 records.
- **Dependencies**
  - PHP ext `pdo_sqlite`; a web server to serve the PHP page.
- **Run**
  - Start server and open:
    - `http://127.0.0.1:8000/dynamic_form.php`
- **Notes**
  - Name is required per row; empty rows are ignored.
  - UI matches the provided layout: Add Row, Delete row per line, Submit.

---

### 5) Bootstrap Form + jQuery Validation + Auto Image Upload
- **Files**: `bootstrap_form.php`, `upload_image.php`, directory `uploads/`
- **Purpose**
  - Form fields: Name, Mobile number, Email ID, City, State, Country.
  - jQuery Validation applied with proper data-type constraints.
  - Image gallery: selecting files triggers auto-upload to `upload_image.php`; thumbnails appear instantly; deletion is client-side.
- **Inputs**
  - Form fields (validation only; no form POST storage).
  - File input `image` sent as `multipart/form-data` to `upload_image.php`.
- **Outputs**
  - `upload_image.php` returns JSON: `{ "success": true, "url": "<absolute image URL>" }`.
  - Files saved under `Solutions/uploads/` with randomized names.
- **Dependencies**
  - `fileinfo` PHP ext for MIME detection; write permission to `uploads/`.
- **Run**
  - Start server and open:
    - `http://127.0.0.1:8000/bootstrap_form.php`
- **Notes**
  - Supported types: JPG, PNG, GIF, WEBP.
  - The gallery is a UI preview; server only persists files.

---

### 6) Sum of Series 0/1 + 1/2 + 2/3 + …
- **File**: `series_sum.php`
- **Purpose**: Compute sum of first N terms of series \( \sum_{k=0}^{N-1} \frac{k}{k+1} \).
- **Inputs**
  - CLI: first arg is `N` (defaults to 6).
  - Browser: query `?n=N` (defaults to 6).
- **Output**
  - Prints series text and sum with 6 decimal places.
- **Run**
```bash
# illustrative only
php series_sum.php 10
```
Or open: `http://127.0.0.1:8000/series_sum.php?n=10`

---

### 7) Weekly and Monthly Charts from Database
- **File**: `sales_charts.php` (uses Chart.js)
- **Purpose**: Render two filled line charts (weekly last 7 days; monthly last 12 months) using values queried from `sales.db`.
- **API Endpoints (same file)**
  - `?action=data&type=weekly|monthly&metric=amount|qty` → JSON:
```json
{ "success": true, "type": "weekly", "metric": "amount", "labels": ["2024-05-01", "..."], "values": [1234.0, ...] }
```
- **Dependencies**: `pdo_sqlite`, existing data in `sales.db` (via `import_sales.php` or seeding).
- **Optional Seeder**
  - **File**: `seed_sales.php`
  - **Purpose**: Insert 7 synthetic rows to make the weekly chart resemble the reference shape.
  - **Run**
```bash
# illustrative only
php seed_sales.php
```
- **Run Charts**
  - Start server and open:
    - `http://127.0.0.1:8000/sales_charts.php`
- **Notes**
  - The script normalizes `date` strings like `02-May-2024` for SQLite aggregation.
  - Two datasets are drawn to mimic “work load” and “free hours” styling.

---

### 8) jQuery Grid: Parse Comma/@-Separated String
- **File**: `jquery_grid_test.html`
- **Purpose**: Paste the provided string; script splits by rows (`,`) and columns (`@`) and maps to a grid with columns: `Cate, Item, Qty, Weight, Peti Rate, Amount, item150, oilSlip, pcs_rate, Rice`.
- **Input**: Large text string like the one in the prompt.
- **Output**: An HTML table with the structured data.
- **Run**
  - Open directly in browser:
    - `http://127.0.0.1:8000/jquery_grid_test.html`
  - Click “Build Grid”.
- **Notes**
  - First three tokens are taken as `Cate`, `Item`, `Qty`.
  - Remaining tokens are parsed as key-value pairs: `key value`.

---

### Environment Setup Details

- **Linux (Debian/Ubuntu)**
```bash
# illustrative only
sudo apt update
sudo apt install -y php php-curl php-sqlite3 php-fileinfo sqlite3
```
- **Windows**
  - Install PHP 8.x from `windows.php.net`, enable extensions in `php.ini`:
    - `extension=curl`, `extension=pdo_sqlite`, `extension=fileinfo`.
- **macOS**
```bash
# illustrative only
brew install php sqlite
```
- **Permissions**
  - Ensure the project directory is writable so `sales.db` and `uploads/` can be created.

---

### Troubleshooting

- **cURL or HTTP errors**: Check internet connectivity, firewall, or API endpoint availability.
- **Google Directions API error**: Verify API key is valid and billing is enabled; check quota and referrer restrictions.
- **SQLite errors**: Confirm `pdo_sqlite` is loaded and files are writable.
- **Uploads fail**: Ensure `fileinfo` is enabled and `uploads/` is writable; verify max upload size in PHP (`upload_max_filesize`, `post_max_size`).
- **Charts show no data**: Run `import_sales.php` or `seed_sales.php` first; then reload `sales_charts.php`.

---

- This README consolidates setup, inputs/outputs, dependencies, and run instructions for all tasks (1)–(8).

