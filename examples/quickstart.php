<?php

declare(strict_types=1);

// Run: NOMBA_CLIENT_ID=... NOMBA_CLIENT_SECRET=... NOMBA_ACCOUNT_ID=... php examples/quickstart.php

require __DIR__ . '/../vendor/autoload.php';

use Nomba\Exception\NombaException;
use Nomba\Nomba;

// Reads NOMBA_CLIENT_ID, NOMBA_CLIENT_SECRET and NOMBA_ACCOUNT_ID. Defaults to the sandbox.
$nomba = new Nomba();

try {
    $parent = $nomba->accounts->getParent();
    $balance = $nomba->accounts->getBalance();

    echo "Account: {$parent['accountName']} ({$parent['accountId']})\n";
    echo "Balance: {$balance['amount']} {$balance['currency']}\n";

    foreach ($nomba->transfers->listBanks() as $bank) {
        echo ($bank['code'] ?? '?') . ' ' . ($bank['name'] ?? '?') . "\n";
    }
} catch (NombaException $e) {
    fwrite(\STDERR, $e->getMessage() . "\n");
    exit(1);
}
