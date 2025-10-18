<?php
declare(strict_types=1);

// dynamic_form.php - Add/Delete rows dynamically and insert into SQLite

$dbPath = __DIR__ . '/sales.db';
session_start();

function openDb(string $path): PDO {
	$pdo = new PDO('sqlite:' . $path);
	$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	return $pdo;
}

function ensureSchema(PDO $pdo): void {
	$pdo->exec('CREATE TABLE IF NOT EXISTS people (
		id INTEGER PRIMARY KEY AUTOINCREMENT,
		name TEXT NOT NULL,
		age INTEGER,
		job TEXT
	)');
}

function insertPeople(PDO $pdo, array $rows): int {
	$inserted = 0;
	$pdo->beginTransaction();
	try {
		$stmt = $pdo->prepare('INSERT INTO people (name, age, job) VALUES (:name, :age, :job)');
		foreach ($rows as $row) {
			$name = trim((string)($row['name'] ?? ''));
			$ageVal = trim((string)($row['age'] ?? ''));
			$job = trim((string)($row['job'] ?? ''));
			if ($name === '' && $ageVal === '' && $job === '') { continue; }
			if ($name === '') { continue; }
			$age = ($ageVal === '' || !ctype_digit($ageVal)) ? null : (int)$ageVal;
			$stmt->execute([
				':name' => $name,
				':age' => $age,
				':job' => $job,
			]);
			$inserted++;
		}
		$pdo->commit();
	} catch (Throwable $e) {
		$pdo->rollBack();
		throw $e;
	}
	return $inserted;
}

$message = '';
$people = [];

try {
	$pdo = openDb($dbPath);
	ensureSchema($pdo);
	// Read and clear flash message (if any)
	if (isset($_SESSION['flash']) && is_string($_SESSION['flash'])) {
		$message = $_SESSION['flash'];
		unset($_SESSION['flash']);
	}

	if ($_SERVER['REQUEST_METHOD'] === 'POST') {
		$rows = $_POST['rows'] ?? [];
		if (is_array($rows)) {
			$inserted = insertPeople($pdo, $rows);
			$_SESSION['flash'] = $inserted > 0 ? (string)$inserted . ' row(s) inserted.' : 'No valid rows to insert.';
			// PRG: redirect to avoid resubmission on refresh
			$target = $_SERVER['REQUEST_URI'] ?? 'dynamic_form.php';
			header('Location: ' . $target);
			exit;
		}
	}
	$people = $pdo->query('SELECT id, name, age, job FROM people ORDER BY id DESC LIMIT 50')->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
	$message = 'Error: ' . $e->getMessage();
}

?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Dynamic Rows Form</title>
<style>
body { font-family: Arial, sans-serif; background:#f6e6a9; padding:24px; }
.grid { display:grid; grid-template-columns: 1fr 120px; gap:8px; max-width:720px; }
.row { display:grid; grid-template-columns: 1fr 140px 1fr 120px; gap:8px; }
input[type=text], input[type=number] { padding:8px; border:1px solid #ccc; border-radius:4px; }
button { background:#c8a100; color:#fff; border:none; padding:10px 14px; border-radius:4px; cursor:pointer; }
button.secondary { background:#888; }
.toolbar { margin-top:12px; display:flex; gap:12px; }
.msg { margin-bottom:12px; font-weight:bold; }
table { margin-top:24px; border-collapse: collapse; }
th, td { border:1px solid #ddd; padding:6px 8px; }
</style>
</head>
<body>
	<h2>Dynamic Add/Delete Rows</h2>
	<?php if ($message !== ''): ?>
		<div class="msg"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
	<?php endif; ?>

	<form method="post" autocomplete="off">
		<div id="rows">
			<div class="row">
				<input name="rows[0][name]" type="text" placeholder="Enter Name">
				<input name="rows[0][age]" type="number" min="0" placeholder="Enter Age">
				<input name="rows[0][job]" type="text" placeholder="Enter Job">
				<button type="button" class="delete">DELETE</button>
			</div>
		</div>
		<div class="toolbar">
			<button type="button" id="add">ADD ROW</button>
			<button type="submit">SUBMIT</button>
		</div>
	</form>

	<?php if (!empty($people)): ?>
		<h3>Last 50 Records</h3>
		<table>
			<tr><th>ID</th><th>Name</th><th>Age</th><th>Job</th></tr>
			<?php foreach ($people as $p): ?>
				<tr>
					<td><?php echo (int)$p['id']; ?></td>
					<td><?php echo htmlspecialchars((string)$p['name'], ENT_QUOTES, 'UTF-8'); ?></td>
					<td><?php echo $p['age'] === null ? '' : (int)$p['age']; ?></td>
					<td><?php echo htmlspecialchars((string)$p['job'], ENT_QUOTES, 'UTF-8'); ?></td>
				</tr>
			<?php endforeach; ?>
		</table>
	<?php endif; ?>

<script>
(function(){
	const rowsEl = document.getElementById('rows');
	const addBtn = document.getElementById('add');

	function nextIndex(){
		const items = rowsEl.querySelectorAll('.row');
		return items.length;
	}

	function createRow(index){
		const div = document.createElement('div');
		div.className = 'row';
		div.innerHTML = '\n\
<input name="rows['+index+'][name]" type="text" placeholder="Enter Name">\n\
<input name="rows['+index+'][age]" type="number" min="0" placeholder="Enter Age">\n\
<input name="rows['+index+'][job]" type="text" placeholder="Enter Job">\n\
<button type="button" class="delete">DELETE</button>';
		return div;
	}

	addBtn.addEventListener('click', function(){
		rowsEl.appendChild(createRow(nextIndex()));
	});

	rowsEl.addEventListener('click', function(e){
		const target = e.target;
		if (target && target.classList.contains('delete')){
			const row = target.closest('.row');
			if (row && rowsEl.children.length > 1){
				row.remove();
			}
		}
	});
})();
</script>

</body>
</html>

