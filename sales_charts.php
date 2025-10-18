<?php
declare(strict_types=1);

// sales_charts.php
// - When opened in browser (no action param): renders a page with two Chart.js line charts (weekly and monthly)
// - When called with ?action=data&type=weekly|monthly&metric=amount|qty returns JSON aggregated from SQLite DB

$dbPath = __DIR__ . '/sales.db';

function openDb(string $path): PDO {
	$pdo = new PDO('sqlite:' . $path);
	$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	return $pdo;
}

function fetchSeries(PDO $pdo, string $type, string $metric): array {
	// Validate inputs
	$type = ($type === 'monthly') ? 'monthly' : 'weekly';
	$metric = ($metric === 'qty') ? 'qty' : 'amount';

	// Choose the aggregation column
	$aggExpr = $metric === 'qty' ? 'COALESCE(i.qty, 0.0)' : 'COALESCE(i.amount_with_gst, i.amount_without_gst, 0.0)';

	// Normalize date strings like '02-May-2024' -> '2024-05-02' for SQLite date functions
	$monthCase = "CASE SUBSTR(m.date,4,3)
		WHEN 'Jan' THEN '01' WHEN 'Feb' THEN '02' WHEN 'Mar' THEN '03' WHEN 'Apr' THEN '04'
		WHEN 'May' THEN '05' WHEN 'Jun' THEN '06' WHEN 'Jul' THEN '07' WHEN 'Aug' THEN '08'
		WHEN 'Sep' THEN '09' WHEN 'Oct' THEN '10' WHEN 'Nov' THEN '11' WHEN 'Dec' THEN '12'
		ELSE '01' END";
	$normDate = "SUBSTR(m.date,8,4) || '-' || ($monthCase) || '-' || SUBSTR(m.date,1,2)";

	if ($type === 'weekly') {
		// Past 7 days (including today) grouped by ISO date
		$sql = "
			WITH t AS (
				SELECT date($normDate) AS d, $aggExpr AS v
				FROM sales_master m
				JOIN sales_item i ON i.master_id = m.master_id
				WHERE m.date IS NOT NULL
			),
			g AS (
				SELECT d AS label, ROUND(SUM(v),2) AS value
				FROM t
				GROUP BY d
				ORDER BY d DESC
				LIMIT 7
			)
			SELECT label, value FROM g ORDER BY label
		";
		$rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
		return $rows;
	}

	// Monthly: last 12 months by YYYY-MM
	$sql = "
		WITH t AS (
			SELECT date($normDate) AS d, $aggExpr AS v
			FROM sales_master m
			JOIN sales_item i ON i.master_id = m.master_id
			WHERE m.date IS NOT NULL
		),
		g AS (
			SELECT strftime('%Y-%m', d) AS label, ROUND(SUM(v),2) AS value
			FROM t
			GROUP BY strftime('%Y-%m', d)
			ORDER BY label DESC
			LIMIT 12
		)
		SELECT label, value FROM g ORDER BY label
	";
	$rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
	return $rows;
}

try {
	$action = isset($_GET['action']) ? (string)$_GET['action'] : '';
	if ($action === 'data') {
		header('Content-Type: application/json');
		$pdo = openDb($dbPath);
		$type = isset($_GET['type']) ? (string)$_GET['type'] : 'weekly';
		$metric = isset($_GET['metric']) ? (string)$_GET['metric'] : 'amount';
		$series = fetchSeries($pdo, $type, $metric);
		echo json_encode([
			'success' => true,
			'type' => $type,
			'metric' => $metric,
			'labels' => array_column($series, 'label'),
			'values' => array_map(static function($r){ return (float)$r['value']; }, $series),
		], JSON_UNESCAPED_SLASHES);
		exit;
	}
} catch (Throwable $e) {
	// Fall through to HTML with a small warning banner
	$errMsg = 'Error loading data: ' . $e->getMessage();
}

?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sales Charts (Weekly & Monthly)</title>
<style>
body { font-family: Arial, sans-serif; background:#f7fbff; margin:0; padding:24px; }
.wrap { max-width: 980px; margin: 0 auto; }
h2 { margin: 12px 0 6px; color:#065f46; }
.row { display:grid; grid-template-columns: 1fr; gap:24px; }
.card { background:#fff; border:1px solid #e6eef5; border-radius:10px; padding:16px; box-shadow: 0 1px 2px rgba(0,0,0,.04); }
.toolbar { display:flex; gap:12px; align-items:center; margin-bottom:10px; }
select, button { padding:8px 10px; border:1px solid #cbd5e1; border-radius:6px; background:#fff; }
.alert { background:#fff3cd; color:#664d03; border:1px solid #ffecb5; padding:10px 12px; border-radius:8px; margin-bottom:16px; }
canvas { width:100%; height:360px; }
</style>
</head>
<body>
<div class="wrap">
	<h1 style="color:#0f766e; margin:0 0 10px">Sales Line Charts</h1>
	<p style="margin:0 0 16px; color:#334155">Data sourced from SQLite database <code>sales.db</code>.</p>
	<?php if (isset($errMsg)): ?>
		<div class="alert"><?php echo htmlspecialchars($errMsg, ENT_QUOTES, 'UTF-8'); ?></div>
	<?php endif; ?>

	<div class="row">
		<div class="card">
			<div class="toolbar">
				<h2 style="flex:1">Weekly (last 7 days)</h2>
			</div>
			<canvas id="weeklyChart"></canvas>
		</div>

		<div class="card">
			<div class="toolbar">
				<h2 style="flex:1">Monthly (last 12 months)</h2>
			</div>
			<canvas id="monthlyChart"></canvas>
		</div>
	</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
(function(){
	function fetchSeries(type, metric){
		const url = 'sales_charts.php?action=data&type=' + encodeURIComponent(type) + '&metric=' + encodeURIComponent(metric);
		return fetch(url).then(r => r.json());
	}

	function buildConfig(labels, datasets, title){
		// Build pretty green gradients like the reference image
		function applyGradient(ctx, color){
			const g = ctx.createLinearGradient(0, 0, 0, ctx.canvas.height);
			const base = (typeof color === 'string' && color.indexOf('rgba(') === 0) ? color : 'rgba(34,197,94,1)';
			g.addColorStop(0, base.replace('1)', '0.35)'));
			g.addColorStop(1, base.replace('1)', '0.05)'));
			return g;
		}

		return {
			type: 'line',
			data: {
				labels: labels,
					datasets: datasets.map(function(ds){
					return {
						label: ds.label,
						data: ds.values,
						fill: true,
						tension: 0.35,
						borderColor: ds.border || '#22c55e',
						backgroundColor: function(context){
							const ctx = context.chart.ctx;
							return applyGradient(ctx, ds.fillBase || 'rgba(34,197,94,1)');
						},
						pointRadius: 4,
						pointBackgroundColor: ds.point || '#16a34a',
						pointBorderWidth: 0
					};
				})
			},
			options: {
				responsive: true,
				plugins: {
					title: {
						display: true,
						text: title,
						color: '#0a7a20',
						font: { size: 28, weight: '700' },
						padding: { top: 10, bottom: 14 }
					},
					legend: { position: 'top', align: 'center', labels: { boxWidth: 18 } }
				},
				scales: {
					x: { grid: { color: '#e5efe8' } },
					y: { beginAtZero: true, grid: { color: '#e5efe8' } }
				}
			}
		};
	}

	const weeklyCtx = document.getElementById('weeklyChart');
	const monthlyCtx = document.getElementById('monthlyChart');
	let weeklyChart, monthlyChart;

	function loadWeekly(){
		// Fetch both amount and quantity to render two overlapping filled lines like the reference image
		Promise.all([
			fetchSeries('weekly', 'amount'),
			fetchSeries('weekly', 'qty')
		]).then(function(results){
			const amt = results[0];
			const qty = results[1];
			if (!amt || !amt.success) return;
			const labels = amt.labels;
			const datasets = [
				{ label: 'work load', values: amt.values, border: '#74c947', point: '#5bbd3a', fillBase: 'rgba(116,201,71,1)' },
				{ label: 'free hours', values: (qty && qty.success) ? qty.values : [], border: '#9aa14a', point: '#8c8f37', fillBase: 'rgba(154,161,74,1)' }
			];
			if (weeklyChart) weeklyChart.destroy();
			// Format x labels to weekday names when there are 7 points
			const prettyLabels = (labels.length === 7) ? ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'] : labels;
			weeklyChart = new Chart(weeklyCtx, buildConfig(prettyLabels, datasets, 'Line Chart'));
		});
	}

	function loadMonthly(){
		Promise.all([
			fetchSeries('monthly', 'amount'),
			fetchSeries('monthly', 'qty')
		]).then(function(results){
			const amt = results[0];
			const qty = results[1];
			if (!amt || !amt.success) return;
			const labels = amt.labels;
			const datasets = [
				{ label: 'work load', values: amt.values, border: '#74c947', point: '#5bbd3a', fillBase: 'rgba(116,201,71,1)' },
				{ label: 'free hours', values: (qty && qty.success) ? qty.values : [], border: '#a1a34a', point: '#8c8f37', fillBase: 'rgba(161,163,74,1)' }
			];
			if (monthlyChart) { try { monthlyChart.destroy(); } catch(e){} }
			monthlyChart = new Chart(monthlyCtx, buildConfig(labels, datasets, 'Line Chart'));
		});
	}

	loadWeekly();
	loadMonthly();
})();
</script>
</body>
</html>

