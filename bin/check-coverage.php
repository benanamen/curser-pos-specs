<?php

declare(strict_types=1);

/**
 * Exit with 0 if line coverage (from Clover XML) is >= threshold percent, else 1.
 * Usage: php bin/check-coverage.php <coverage.xml> <min-percent>
 */

if ($argc < 3) {
    fwrite(STDERR, "Usage: php bin/check-coverage.php <coverage.xml> <min-percent>\n");
    exit(1);
}

$coverageFile = $argv[1];
$minPercent = (float) $argv[2];

if (!is_file($coverageFile)) {
    fwrite(STDERR, "Coverage file not found: {$coverageFile}\n");
    exit(1);
}

$xml = @simplexml_load_file($coverageFile);
if ($xml === false) {
    fwrite(STDERR, "Invalid XML: {$coverageFile}\n");
    exit(1);
}

$project = $xml->project ?? $xml;
$metrics = $project->metrics ?? null;
if ($metrics === null) {
    fwrite(STDERR, "No metrics in Clover file.\n");
    exit(1);
}

$statements = (int) ($metrics['statements'] ?? 0);
$covered = (int) ($metrics['coveredstatements'] ?? 0);

if ($statements === 0) {
    $percent = 100.0;
} else {
    $percent = ($covered / $statements) * 100.0;
}

if ($percent >= $minPercent) {
    echo sprintf("Line coverage: %.2f%% (required: %.2f%%) - OK\n", $percent, $minPercent);
    exit(0);
}

fwrite(STDERR, sprintf("Line coverage: %.2f%% is below required %.2f%%\n", $percent, $minPercent));
exit(1);
