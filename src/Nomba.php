<?php

declare(strict_types=1);

namespace Nomba;

use Nomba\Auth\TokenManager;
use Nomba\Exception\ConfigurationException;
use Nomba\Http\ApiClient;
use Nomba\Http\Transport;
use Nomba\Resource\Accounts;
use Nomba\Resource\Auth;
use Nomba\Resource\Bills;
use Nomba\Resource\Checkout;
use Nomba\Resource\DirectDebits;
use Nomba\Resource\GlobalCollection;
use Nomba\Resource\GlobalPayout;
use Nomba\Resource\Terminals;
use Nomba\Resource\Transactions;
use Nomba\Resource\Transfers;
use Nomba\Resource\VirtualAccounts;
use Nomba\Resource\Webhooks;

/**
 * Nomba API client.
 *
 *     $nomba = new Nomba\Nomba([
 *         'clientId' => '...', 'clientSecret' => '...', 'accountId' => '...',
 *     ]);
 *     $balance = $nomba->accounts->getBalance();
 *
 * Credentials default to the NOMBA_CLIENT_ID, NOMBA_CLIENT_SECRET and
 * NOMBA_ACCOUNT_ID environment variables. The environment defaults to sandbox.
 * No network call is made until the first API call.
 *
 * @phpstan-import-type NombaOptions from Config
 */
final class Nomba
{
    public const VERSION = '0.1.0';

    public readonly Auth $auth;
    public readonly Accounts $accounts;
    public readonly VirtualAccounts $virtualAccounts;
    public readonly Checkout $checkout;
    public readonly Transfers $transfers;
    public readonly Transactions $transactions;
    public readonly Terminals $terminals;
    public readonly DirectDebits $directDebits;
    public readonly Bills $bills;
    public readonly GlobalPayout $globalPayout;
    public readonly GlobalCollection $globalCollection;
    public readonly Webhooks $webhooks;

    private readonly Config $config;

    /**
     * @param NombaOptions $options
     *
     * @throws ConfigurationException
     */
    public function __construct(#[\SensitiveParameter] array $options = [])
    {
        $this->config = Config::fromOptions($options);
        $transport = new Transport($this->config);
        $client = new ApiClient($this->config, $transport, new TokenManager($this->config, $transport));

        $this->auth = new Auth($client);
        $this->accounts = new Accounts($client);
        $this->virtualAccounts = new VirtualAccounts($client);
        $this->checkout = new Checkout($client);
        $this->transfers = new Transfers($client);
        $this->transactions = new Transactions($client);
        $this->terminals = new Terminals($client);
        $this->directDebits = new DirectDebits($client);
        $this->bills = new Bills($client);
        $this->globalPayout = new GlobalPayout($client);
        $this->globalCollection = new GlobalCollection($client);
        $this->webhooks = new Webhooks();
    }

    public function getConfig(): Config
    {
        return $this->config;
    }

    /**
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return ['version' => self::VERSION, 'config' => $this->config];
    }
}
