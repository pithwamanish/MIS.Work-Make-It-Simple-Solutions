<?php
declare(strict_types=1);

// distance_calculator.php
// Usage:
//   php distance_calculator.php [--key=YOUR_API_KEY] "Pickup Location" "Location 2" "Location 3" ... "Drop-off Location"
// Example:
//   php distance_calculator.php --key=AIzaSyBv0hnS3pfteHN1XbAXFrrfs-PV3aX1C8Y "Delhi Airport" "Connaught Place, New Delhi" "India Gate, New Delhi" "Taj Mahal, Agra"
// Notes:
// - Provide at least two locations: origin and destination. Any addresses in between are treated as waypoints.
// - Distance is computed using Google Directions API (driving, metric units).

function readApiKey(array $argv): string {
	$defaultKey = 'AIzaSyBv0hnS3pfteHN1XbAXFrrfs-PV3aX1C8Y'; // Provided key
	$envKey = getenv('GOOGLE_MAPS_API_KEY') ?: '';
	$cliKey = '';

	foreach ($argv as $arg) {
		if (strpos($arg, '--key=') === 0) {
			$cliKey = substr($arg, 6);
			break;
		}
	}

	return $cliKey !== '' ? $cliKey : ($envKey !== '' ? $envKey : $defaultKey);
}

function buildDirectionsUrl(string $apiKey, string $origin, array $waypoints, string $destination): string {
	$params = [
		'origin' => $origin,
		'destination' => $destination,
		'mode' => 'driving',
		'units' => 'metric',
		'key' => $apiKey,
	];

	$query = http_build_query($params);

	if (!empty($waypoints)) {
		// Waypoints joined by '|', keep given order (no optimization)
		$joined = implode('|', array_map(static function ($w) {
			return $w;
		}, $waypoints));
		$query .= '&waypoints=' . urlencode($joined);
	}

	return 'https://maps.googleapis.com/maps/api/directions/json?' . $query;
}

function httpGetJson(string $url, int $timeoutSeconds = 20): array {
	$ch = curl_init();
	curl_setopt_array($ch, [
		CURLOPT_URL => $url,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_TIMEOUT => $timeoutSeconds,
	]);
	$response = curl_exec($ch);
	$errno = curl_errno($ch);
	$error = curl_error($ch);
	$httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
	curl_close($ch);

	if ($errno !== 0) {
		throw new RuntimeException('HTTP request failed: ' . $error);
	}
	if ($httpCode < 200 || $httpCode >= 300) {
		throw new RuntimeException('HTTP error status: ' . $httpCode);
	}
	$decoded = json_decode($response ?? '', true);
	if (!is_array($decoded)) {
		throw new RuntimeException('Invalid JSON response');
	}
	return $decoded;
}

function computeTotalDistanceMeters(array $directionsResponse): int {
	if (!isset($directionsResponse['status']) || $directionsResponse['status'] !== 'OK') {
		$status = $directionsResponse['status'] ?? 'UNKNOWN';
		$message = $directionsResponse['error_message'] ?? '';
		throw new RuntimeException('Directions API error: ' . $status . ($message ? (' - ' . $message) : ''));
	}
	if (empty($directionsResponse['routes'][0]['legs'])) {
		throw new RuntimeException('No route legs found in response');
	}
	$legs = $directionsResponse['routes'][0]['legs'];
	$total = 0;
	foreach ($legs as $leg) {
		if (!isset($leg['distance']['value'])) {
			continue;
		}
		$total += (int)$leg['distance']['value']; // meters
	}
	return $total;
}

function formatDistance(int $meters): string {
	$km = $meters / 1000.0;
	return number_format($km, 2) . ' km (' . number_format($meters) . ' m)';
}

function printUsage(string $script): void {
	fwrite(STDERR, "\nUsage:\n");
	fwrite(STDERR, "  php {$script} [--key=YOUR_API_KEY] \"Pickup Location\" [\"Location 2\"] [\"Location 3\"] ... \"Drop-off Location\"\n\n");
	fwrite(STDERR, "Examples:\n");
	fwrite(STDERR, "  php {$script} --key=YOUR_API_KEY \"Pickup Location\" \"Location 2\" \"Drop-off Location\"\n");
	fwrite(STDERR, "  php {$script} \"Delhi Airport\" \"Connaught Place, New Delhi\" \"India Gate, New Delhi\" \"Taj Mahal, Agra\"\n\n");
}

// --- Main ---
$apiKey = readApiKey($argv);

// Filter out the --key flag from positional args
$positional = [];
foreach ($argv as $idx => $arg) {
	if ($idx === 0) { continue; } // skip script name
	if (strpos($arg, '--key=') === 0) { continue; }
	$positional[] = $arg;
}

if (count($positional) < 2) {
	printUsage($argv[0] ?? 'distance_calculator.php');
	exit(64); // EX_USAGE
}

$origin = $positional[0];
$destination = $positional[count($positional) - 1];
$waypoints = array_slice($positional, 1, -1);

$url = buildDirectionsUrl($apiKey, $origin, $waypoints, $destination);

try {
	$response = httpGetJson($url);
	$totalMeters = computeTotalDistanceMeters($response);
	echo "Total driving distance: " . formatDistance($totalMeters) . "\n";
	// Optional: echo breakdown
	if (!empty($response['routes'][0]['legs'])) {
		echo "\nLeg breakdown:\n";
		foreach ($response['routes'][0]['legs'] as $i => $leg) {
			$start = $leg['start_address'] ?? 'Start';
			$end = $leg['end_address'] ?? 'End';
			$d = isset($leg['distance']['text']) ? $leg['distance']['text'] : ((isset($leg['distance']['value']) ? ($leg['distance']['value'] . ' m') : 'N/A'));
			echo '  ' . ($i + 1) . ". {$start} -> {$end}: {$d}\n";
		}
	}
} catch (Throwable $e) {
	fwrite(STDERR, 'Error: ' . $e->getMessage() . "\n");
	exit(1);
}

