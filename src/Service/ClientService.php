<?php declare(strict_types=1);

namespace SyncEngine\Shopware\Service;

use Shopware\Core\System\SystemConfig\SystemConfigService;
use SyncEngine\Shopware\Api\Client;

class ClientService
{
    public const CONFIG_API_HOST = 'SyncEngineConnector.config.apiHost';
    public const CONFIG_API_TOKEN = 'SyncEngineConnector.config.apiToken';
    public const CONFIG_API_AUTH_HEADER = 'SyncEngineConnector.config.apiAuthHeader';

    private SystemConfigService $config;

    public function __construct(SystemConfigService $config)
    {
        $this->config = $config;
    }

    public function getClient(): ?Client
    {
        $host = trim((string) ($this->config->get(self::CONFIG_API_HOST) ?? ''));
        $token = trim((string) ($this->config->get(self::CONFIG_API_TOKEN) ?? ''));

        if ($host === '' || $token === '') {
            return null;
        }

        return new Client(
            $host,
            $token,
            $this->config,
            [
                'version' => 1,
                'auth_header' => trim((string) ($this->config->get(self::CONFIG_API_AUTH_HEADER) ?? '')),
            ]
        );
    }

    public function clearApiCache(): void
    {
        $client = $this->getClient();
        if ($client) {
            $client->clearCache();
        }
    }
}