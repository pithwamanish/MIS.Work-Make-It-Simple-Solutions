<?php
declare(strict_types=1);

// Compute the sum of the series: 0/1 + 1/2 + 2/3 + ... for a given number of terms
function computeSeriesSum(int $numberOfTerms): float {
    if ($numberOfTerms < 1) {
        return 0.0;
    }

    $sum = 0.0;
    for ($numerator = 0; $numerator < $numberOfTerms; $numerator++) {
        $sum += $numerator / ($numerator + 1);
    }

    return $sum;
}

// Input: number of terms via CLI arg or GET param `n` (default 6)
$terms = 6;
if (PHP_SAPI === 'cli') {
    if (isset($argv[1]) && is_numeric($argv[1])) {
        $terms = max(1, (int)$argv[1]);
    }
} else {
    if (isset($_GET['n']) && is_numeric($_GET['n'])) {
        $terms = max(1, (int)$_GET['n']);
    }
}

$sum = computeSeriesSum($terms);

// Output the series and its sum
echo "Series: ";
for ($i = 0; $i < $terms; $i++) {
    echo $i . "/" . ($i + 1);
    if ($i + 1 < $terms) {
        echo " + ";
    }
}
echo PHP_EOL;
echo "Sum of $terms terms = " . number_format($sum, 6) . PHP_EOL;

?>

