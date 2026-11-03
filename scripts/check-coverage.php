<?php

declare(strict_types=1);

/*
 * Fail when line coverage in a PHPUnit Clover report is below a threshold.
 *
 *     php scripts/check-coverage.php build/clover.xml 90
 *
 * Uses the project-level <metrics statements=".." coveredstatements=".."/>,
 * which PHPUnit fills with executable lines and covered lines of src/.
 */

$file = $argv[1] ?? 'build/clover.xml';
$threshold = (float) ($argv[2] ?? 90);

$xml = is_file($file) ? simplexml_load_file($file) : false;
if ($xml === false) {
    fwrite(\STDERR, "Could not read Clover report {$file}\n");
    exit(2);
}

$metrics = $xml->project->metrics ?? null;
if ($metrics === null) {
    fwrite(\STDERR, "No project metrics in {$file}\n");
    exit(2);
}

$lines = (int) $metrics['statements'];
$covered = (int) $metrics['coveredstatements'];
$percent = $lines === 0 ? 0.0 : $covered / $lines * 100;

printf("Line coverage: %.2f%% (%d/%d lines), required %.2f%%\n", $percent, $covered, $lines, $threshold);
if ($percent < $threshold) {
    fwrite(\STDERR, "Coverage is below the required {$threshold}%.\n");
    exit(1);
}
