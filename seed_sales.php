<?php
declare(strict_types=1);

$dbPath = __DIR__ . '/sales.db';

function openDb(string $path): PDO {
	$pdo = new PDO('sqlite:' . $path);
	$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	return $pdo;
}

try {
	$pdo = openDb($dbPath);
	$pdo->beginTransaction();
	// Clean previously seeded synthetic rows
	$pdo->exec("DELETE FROM sales_item WHERE master_id LIKE 'SYN-%'");
	$pdo->exec("DELETE FROM sales_master WHERE master_id LIKE 'SYN-%'");

	// Start from the most recent Monday to match the example's weekday labeling
	$start = new DateTime('now');
	$w = (int)$start->format('N'); // 1=Mon..7=Sun
	$start->modify('-' . ($w - 1) . ' days');

	// Shapes roughly matching the screenshot (work load higher Tue/Thu, valley Sat, rise Sun)
	$workLoad = [2, 9, 3, 16, 6, 2, 7];   // plotted as amount
	$freeHours = [1, 2, 4, 5, 3, 1, 10];  // plotted as qty

	for ($i = 0; $i < 7; $i++) {
		$d = (clone $start)->modify('+' . $i . ' day');
		$date = $d->format('d-M-Y'); // matches importer stored format
		$mid = 'SYN-' . $d->format('Ymd');
		$pdo->prepare('INSERT OR IGNORE INTO sales_master (master_id, date, invoice_number, party_code, party_name, gst_no, party_group) VALUES (?,?,?,?,?,?,?)')
			->execute([$mid, $date, 'SYN/' . ($i + 1), 'PC' . ($i + 1), 'Synthetic Party', 'GSTX', 'SYN']);
		$qty = (float)$freeHours[$i] * 800;        // scale to overlap visibly with amount
		$amt = (float)$workLoad[$i] * 1000;        // scale for visibility
		$pdo->prepare('INSERT INTO sales_item (master_id, item_code, item_name, item_group, sub_group, qty, unit, price_without_gst, amount_without_gst, gst_amount, amount_with_gst) VALUES (?,?,?,?,?,?,?,?,?,?,?)')
			->execute([$mid, 'ITM' . ($i + 1), 'Synthetic Item', 'Grp', 'Sub', $qty, 'pcs', null, $amt * 0.9, $amt * 0.1, $amt]);
	}
	$pdo->commit();
	echo "Seeded 7 synthetic sales.\n";
} catch (Throwable $e) {
	fwrite(STDERR, 'Error: ' . $e->getMessage() . "\n");
	exit(1);
}

