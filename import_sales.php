<?php
declare(strict_types=1);

// Config
$url = 'https://apimis.in/api/jsonAPITest.php';
$dbPath = __DIR__ . '/sales.db';

function fetchJson(string $url): array {
	$ch = curl_init($url);
	if ($ch === false) {
		throw new RuntimeException('Failed to init cURL');
	}
	curl_setopt_array($ch, [
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_FOLLOWLOCATION => true,
		CURLOPT_TIMEOUT => 20,
		CURLOPT_HTTPHEADER => [
			'Accept: application/json',
		],
	]);
	$body = curl_exec($ch);
	if ($body === false) {
		$err = curl_error($ch);
		curl_close($ch);
		throw new RuntimeException('cURL error: ' . $err);
	}
	$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	curl_close($ch);
	// The endpoint may prepend PHP warnings; strip leading non-JSON content
	$body = ltrim($body);
	$firstBracePos = strpos($body, '{');
	if ($firstBracePos !== false) {
		$body = substr($body, $firstBracePos);
	}
	$data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
	if (!is_array($data)) {
		throw new RuntimeException('Invalid JSON payload');
	}
	return $data;
}

function openDb(string $path): PDO {
	$pdo = new PDO('sqlite:' . $path);
	$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	// Enable foreign keys
	$pdo->exec('PRAGMA foreign_keys = ON');
	return $pdo;
}

function ensureSchema(PDO $pdo): void {
	$pdo->exec('CREATE TABLE IF NOT EXISTS sales_master (
		id INTEGER PRIMARY KEY AUTOINCREMENT,
		master_id TEXT UNIQUE,
		date TEXT,
		invoice_number TEXT,
		party_code TEXT,
		party_name TEXT,
		gst_no TEXT,
		party_group TEXT
	)');
	$pdo->exec('CREATE TABLE IF NOT EXISTS sales_item (
		id INTEGER PRIMARY KEY AUTOINCREMENT,
		master_id TEXT,
		item_code TEXT,
		item_name TEXT,
		item_group TEXT,
		sub_group TEXT,
		qty REAL,
		unit TEXT,
		price_without_gst REAL,
		amount_without_gst REAL,
		gst_amount REAL,
		amount_with_gst REAL,
		FOREIGN KEY(master_id) REFERENCES sales_master(master_id) ON DELETE CASCADE
	)');
}

function toFloatOrNull(?string $value): ?float {
	if ($value === null || $value === '') return null;
	$normalized = str_replace(',', '', $value);
	if (!is_numeric($normalized)) return null;
	return (float) $normalized;
}

function insertData(PDO $pdo, array $data): float {
	$totalQty = 0.0;
	if (!isset($data['salesDetails']) || !is_array($data['salesDetails'])) {
		throw new RuntimeException('salesDetails not found');
	}
	$pdo->beginTransaction();
	try {
		$stmtMaster = $pdo->prepare('INSERT OR IGNORE INTO sales_master (master_id, date, invoice_number, party_code, party_name, gst_no, party_group)
			VALUES (:master_id, :date, :invoice_number, :party_code, :party_name, :gst_no, :party_group)');
		$stmtItem = $pdo->prepare('INSERT INTO sales_item (master_id, item_code, item_name, item_group, sub_group, qty, unit, price_without_gst, amount_without_gst, gst_amount, amount_with_gst)
			VALUES (:master_id, :item_code, :item_name, :item_group, :sub_group, :qty, :unit, :price_without_gst, :amount_without_gst, :gst_amount, :amount_with_gst)');
		foreach ($data['salesDetails'] as $sale) {
			$masterId = (string)($sale['MasterId'] ?? '');
			$stmtMaster->execute([
				':master_id' => $masterId,
				':date' => (string)($sale['date'] ?? ''),
				':invoice_number' => (string)($sale['invoice_Number'] ?? ''),
				':party_code' => (string)($sale['party_Code'] ?? ''),
				':party_name' => (string)($sale['party_Name'] ?? ''),
				':gst_no' => (string)($sale['gst_No'] ?? ''),
				':party_group' => (string)($sale['party_group'] ?? ''),
			]);
			$items = $sale['itemDetails'] ?? null;
			if (is_array($items)) {
				foreach ($items as $item) {
					$qty = toFloatOrNull((string)($item['qty'] ?? '0')) ?? 0.0;
					$totalQty += $qty;
					$stmtItem->execute([
						':master_id' => $masterId,
						':item_code' => (string)($item['item_code'] ?? ''),
						':item_name' => (string)($item['item_Name'] ?? ''),
						':item_group' => (string)($item['item_group'] ?? ''),
						':sub_group' => (string)($item['sub_group'] ?? ''),
						':qty' => $qty,
						':unit' => (string)($item['unit'] ?? ''),
						':price_without_gst' => toFloatOrNull(isset($item['price_without_gst']) ? (string)$item['price_without_gst'] : null),
						':amount_without_gst' => toFloatOrNull(isset($item['amount_without_gst']) ? (string)$item['amount_without_gst'] : null),
						':gst_amount' => toFloatOrNull(isset($item['gst_amount']) ? (string)$item['gst_amount'] : null),
						':amount_with_gst' => toFloatOrNull(isset($item['amount_with_gst']) ? (string)$item['amount_with_gst'] : null),
					]);
				}
			}
		}
		$pdo->commit();
	} catch (Throwable $e) {
		$pdo->rollBack();
		throw $e;
	}
	return $totalQty;
}

try {
	$pdo = openDb($dbPath);

	// Simple CLI modes
	// Default: import
	// --verify : show counts and total sum from DB
	// --fetch=<MasterId> : print items for a specific master id as JSON
	$mode = 'import';
	$fetchMasterId = null;
	foreach ($argv as $arg) {
		if ($arg === '--verify') {
			$mode = 'verify';
		} elseif (str_starts_with($arg, '--fetch=')) {
			$mode = 'fetch';
			$fetchMasterId = substr($arg, strlen('--fetch='));
		}
	}

	if ($mode === 'import') {
		$data = fetchJson($url);
		ensureSchema($pdo);
		$totalQty = insertData($pdo, $data);
		echo 'Total quantity: ' . (int) $totalQty . "\n";
		echo 'Database: ' . $dbPath . "\n";
		// fall-through to verify after import
		$mode = 'verify';
	}

	if ($mode === 'verify') {
		$cntSales = (int) $pdo->query('SELECT COUNT(*) FROM sales_master')->fetchColumn();
		$cntItems = (int) $pdo->query('SELECT COUNT(*) FROM sales_item')->fetchColumn();
		$sumQty = (float) $pdo->query('SELECT COALESCE(SUM(qty), 0) FROM sales_item')->fetchColumn();
		echo 'Rows in sales_master: ' . $cntSales . "\n";
		echo 'Rows in sales_item: ' . $cntItems . "\n";
		echo 'Sum of qty: ' . (int) $sumQty . "\n";
		$rows = $pdo->query("SELECT master_id, date, invoice_number, party_name FROM sales_master ORDER BY id DESC LIMIT 5")
			->fetchAll(PDO::FETCH_ASSOC);
		echo 'Sample sales (up to 5): ' . json_encode($rows, JSON_UNESCAPED_SLASHES) . "\n";
	}

	if ($mode === 'fetch') {
		if ($fetchMasterId === null || $fetchMasterId === '') {
			throw new InvalidArgumentException('Provide master id via --fetch=<MasterId>');
		}
		$stmt = $pdo->prepare('SELECT * FROM sales_item WHERE master_id = :m ORDER BY id LIMIT 50');
		$stmt->execute([':m' => $fetchMasterId]);
		$items = $stmt->fetchAll(PDO::FETCH_ASSOC);
		echo json_encode(['master_id' => $fetchMasterId, 'items' => $items], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
	}
} catch (Throwable $e) {
	fwrite(STDERR, 'Error: ' . $e->getMessage() . "\n");
	exit(1);
}
