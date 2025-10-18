<?php
function isPrime(int $n): bool {
	if ($n < 2 || $n % 2 === 0) return false;
	if ($n === 2) return true;
	$limit = (int) sqrt($n);
	for ($i = 3; $i <= $limit; $i += 2) {
		if ($n % $i === 0) return false;
	}
	return true;
}

$primes = [];
for ($i = 1; $i <= 100; $i++) {
	if (isPrime($i)) {
		$primes[] = $i;
	}
}

echo implode(', ', $primes), "\n";
