<?php

declare(strict_types=1);

// Walk every transaction in a date range. listAll() fetches pages lazily.

require __DIR__ . '/../vendor/autoload.php';

use Nomba\Nomba;

$nomba = new Nomba();

$count = 0;
foreach ($nomba->transactions->listAll(['dateFrom' => '2026-01-01', 'dateTo' => '2026-01-31', 'limit' => 50]) as $transaction) {
    echo "{$transaction['timeCreated']} {$transaction['type']} {$transaction['amount']} {$transaction['status']}\n";
    ++$count;
}
echo "{$count} transactions\n";

// One page at a time, if you want to store the cursor yourself:
$page = $nomba->transactions->list(['limit' => 50]);
echo 'Next cursor: ' . ($page['cursor'] ?? '(last page)') . "\n";
