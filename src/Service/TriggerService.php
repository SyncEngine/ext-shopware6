<?php declare(strict_types=1);

namespace SyncEngine\Shopware\Service;

use Shopware\Core\Framework\Context;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class TriggerService
{
    public const CONFIG_TRIGGERS_ENABLED = 'SyncEngineConnector.config.triggersEnabled';
    public const CONFIG_TRIGGER_MAP = 'SyncEngineConnector.config.triggerEndpointMap';
    public const CONFIG_TRIGGER_MAP_TS = 'SyncEngineConnector.config.triggerEndpointMapTs';
    public const CONFIG_REFRESH_THROTTLE_PREFIX = 'SyncEngineConnector.config.refreshThrottle.';

    private const TRIGGER_MAP_TTL = 300;
    private const SHOPWARE_WEBSERVICE_CLASS = 'SyncEngine/ShopwareAdminV1:ShopwareAdminV1';

    public const TRIGGER_NEW_PRODUCT = 'new_product';
    public const TRIGGER_UPDATED_PRODUCT = 'updated_product';
    public const TRIGGER_DELETED_PRODUCT = 'deleted_product';

    public const TRIGGER_NEW_ORDER = 'new_order';
    public const TRIGGER_UPDATED_ORDER = 'updated_order';
    public const TRIGGER_DELETED_ORDER = 'deleted_order';

    public const TRIGGER_NEW_CUSTOMER = 'new_customer';
    public const TRIGGER_UPDATED_CUSTOMER = 'updated_customer';
    public const TRIGGER_DELETED_CUSTOMER = 'deleted_customer';

    private SystemConfigService $config;
    private ClientService $clientService;
    private EndpointDispatcherService $endpointDispatcherService;
    private ShopwarePayloadService $payloadService;
    private RefreshTrustService $refreshTrustService;

    public function __construct(
        SystemConfigService $config,
        ClientService $clientService,
        EndpointDispatcherService $endpointDispatcherService,
        ShopwarePayloadService $payloadService,
        RefreshTrustService $refreshTrustService
    ) {
        $this->config = $config;
        $this->clientService = $clientService;
        $this->endpointDispatcherService = $endpointDispatcherService;
        $this->payloadService = $payloadService;
        $this->refreshTrustService = $refreshTrustService;
    }

    public function isDispatchEnabled(): bool
    {
        return (bool) ($this->config->get(self::CONFIG_TRIGGERS_ENABLED) ?? true);
    }

    public function clearTriggerEndpointMapCache(): void
    {
        $this->config->delete(self::CONFIG_TRIGGER_MAP);
        $this->config->delete(self::CONFIG_TRIGGER_MAP_TS);
    }

    public function getTriggerEndpointMap(bool $refresh = false): array
    {
        if (!$refresh) {
            $cached = (string) ($this->config->get(self::CONFIG_TRIGGER_MAP) ?? '');
            $cachedTs = (int) ($this->config->get(self::CONFIG_TRIGGER_MAP_TS) ?? 0);
            if ($cached !== '' && (time() - $cachedTs) < self::TRIGGER_MAP_TTL) {
                $decoded = json_decode($cached, true);
                if (is_array($decoded)) {
                    return $decoded;
                }
            }
        }

        $map = [];
        foreach ($this->getTriggerEvents() as $event) {
            $map[$event] = [];
        }

        $client = $this->clientService->getClient();
        if (!$client) {
            return $map;
        }

        $automations = $client->listAutomations();
        if (!is_array($automations)) {
            return $map;
        }

        $localConnectionIds = $this->getLocalConnectionIds($client);
        if ($localConnectionIds === []) {
            return $map;
        }

        $classMap = $this->getBlueprintClassMap();

        foreach ($automations as $automation) {
            if (!is_array($automation)) {
                continue;
            }

            $endpoint = trim((string) ($automation['endpoint'] ?? ''));
            if ($endpoint === '') {
                continue;
            }

            $blueprint = (array) ($automation['config']['_blueprint'] ?? []);
            $blueprintClass = (string) ($blueprint['_class'] ?? '');

            $connectionRaw = $blueprint['connection'] ?? 0;
            $blueprintConnection = is_array($connectionRaw)
                ? (int) ($connectionRaw['id'] ?? $connectionRaw['connection'] ?? 0)
                : (int) $connectionRaw;

            if (!isset($classMap[$blueprintClass]) || $blueprintConnection <= 0) {
                continue;
            }

            if (!in_array($blueprintConnection, $localConnectionIds, true)) {
                continue;
            }

            $map[$classMap[$blueprintClass]][] = $endpoint;
        }

        foreach ($map as $event => $endpoints) {
            $map[$event] = array_values(array_unique($endpoints));
        }

        $this->config->set(self::CONFIG_TRIGGER_MAP, json_encode($map));
        $this->config->set(self::CONFIG_TRIGGER_MAP_TS, time());

        return $map;
    }

    public function getEndpointsForTrigger(string $trigger): array
    {
        $map = $this->getTriggerEndpointMap();
        return (array) ($map[$trigger] ?? []);
    }

    public function handleProductEvent(string $kind, string $id, Context $context): void
    {
        $map = [
            'new' => [self::TRIGGER_NEW_PRODUCT, 'shopware_new_product'],
            'updated' => [self::TRIGGER_UPDATED_PRODUCT, 'shopware_updated_product'],
            'deleted' => [self::TRIGGER_DELETED_PRODUCT, 'shopware_deleted_product'],
        ];

        if (!isset($map[$kind])) {
            return;
        }

        $data = $kind === 'deleted' ? ['id' => $id] : $this->payloadService->getProductData($id, $context);
        $this->dispatchEntityEvent($map[$kind][0], $map[$kind][1], $id, $data, ['entity' => 'product']);
    }

    public function handleOrderEvent(string $kind, string $id, Context $context): void
    {
        $map = [
            'new' => [self::TRIGGER_NEW_ORDER, 'shopware_new_order'],
            'updated' => [self::TRIGGER_UPDATED_ORDER, 'shopware_updated_order'],
            'deleted' => [self::TRIGGER_DELETED_ORDER, 'shopware_deleted_order'],
        ];

        if (!isset($map[$kind])) {
            return;
        }

        $data = $kind === 'deleted' ? ['id' => $id] : $this->payloadService->getOrderData($id, $context);
        $this->dispatchEntityEvent($map[$kind][0], $map[$kind][1], $id, $data, ['entity' => 'order']);
    }

    public function handleCustomerEvent(string $kind, string $id, Context $context): void
    {
        $map = [
            'new' => [self::TRIGGER_NEW_CUSTOMER, 'shopware_new_customer'],
            'updated' => [self::TRIGGER_UPDATED_CUSTOMER, 'shopware_updated_customer'],
            'deleted' => [self::TRIGGER_DELETED_CUSTOMER, 'shopware_deleted_customer'],
        ];

        if (!isset($map[$kind])) {
            return;
        }

        $data = $kind === 'deleted' ? ['id' => $id] : $this->payloadService->getCustomerData($id, $context);
        $this->dispatchEntityEvent($map[$kind][0], $map[$kind][1], $id, $data, ['entity' => 'customer']);
    }

    private function dispatchEntityEvent(string $trigger, string $event, string $id, array $data, array $context = []): void
    {
        if (!$this->isDispatchEnabled() || $id === '') {
            return;
        }

        $endpoints = $this->getEndpointsForTrigger($trigger);
        if ($endpoints === []) {
            return;
        }

        $this->endpointDispatcherService->triggerEndpoints(
            $endpoints,
            [
                'id' => $id,
                'event' => $event,
                'data' => $data,
                'request' => ['id' => $id],
            ],
            [
                'source' => 'shopware',
                'trigger' => $trigger,
                'context' => $context,
            ]
        );
    }

    private function getTriggerEvents(): array
    {
        return [
            self::TRIGGER_NEW_PRODUCT,
            self::TRIGGER_UPDATED_PRODUCT,
            self::TRIGGER_DELETED_PRODUCT,
            self::TRIGGER_NEW_ORDER,
            self::TRIGGER_UPDATED_ORDER,
            self::TRIGGER_DELETED_ORDER,
            self::TRIGGER_NEW_CUSTOMER,
            self::TRIGGER_UPDATED_CUSTOMER,
            self::TRIGGER_DELETED_CUSTOMER,
        ];
    }

    private function getBlueprintClassMap(): array
    {
        return [
            'SyncEngine/ShopwareAdminV1:NewProduct' => self::TRIGGER_NEW_PRODUCT,
            'SyncEngine/ShopwareAdminV1:UpdatedProduct' => self::TRIGGER_UPDATED_PRODUCT,
            'SyncEngine/ShopwareAdminV1:DeletedProduct' => self::TRIGGER_DELETED_PRODUCT,
            'SyncEngine/ShopwareAdminV1:NewOrder' => self::TRIGGER_NEW_ORDER,
            'SyncEngine/ShopwareAdminV1:UpdatedOrder' => self::TRIGGER_UPDATED_ORDER,
            'SyncEngine/ShopwareAdminV1:DeletedOrder' => self::TRIGGER_DELETED_ORDER,
            'SyncEngine/ShopwareAdminV1:NewCustomer' => self::TRIGGER_NEW_CUSTOMER,
            'SyncEngine/ShopwareAdminV1:UpdatedCustomer' => self::TRIGGER_UPDATED_CUSTOMER,
            'SyncEngine/ShopwareAdminV1:DeletedCustomer' => self::TRIGGER_DELETED_CUSTOMER,
        ];
    }

    private function getLocalConnectionIds($client): array
    {
        $connections = $client->listConnections();
        if (!is_array($connections)) {
            return [];
        }

        $localHosts = $this->getLocalStoreHosts();
        if ($localHosts === []) {
            return [];
        }

        $ids = [];
        $refs = [];

        foreach ($connections as $connection) {
            if (!is_array($connection)) {
                continue;
            }

            $id = (int) ($connection['id'] ?? 0);
            $config = (array) ($connection['config'] ?? []);
            $webservice = (array) ($config['webservice'] ?? []);
            $class = (string) ($webservice['_class'] ?? '');

            if ($class !== self::SHOPWARE_WEBSERVICE_CLASS) {
                continue;
            }

            $host = $this->normalizeStoreHost((string) ($webservice['host'] ?? ''));
            if ($id > 0 && $host !== '' && in_array($host, $localHosts, true)) {
                $ids[] = $id;
                $ref = trim((string) ($connection['ref'] ?? ''));
                if ($ref !== '') {
                    $refs[] = $ref;
                }
            }
        }

        if ($refs !== []) {
            $this->refreshTrustService->rememberConnectionRefs($refs);
        }

        return array_values(array_unique($ids));
    }

    private function getLocalStoreHosts(): array
    {
        $hosts = [];

        $envUrl = trim((string) ($_ENV['APP_URL'] ?? $_SERVER['APP_URL'] ?? ''));
        if ($envUrl !== '') {
            $normalized = $this->normalizeStoreHost($envUrl);
            if ($normalized !== '') {
                $hosts[] = $normalized;
            }
        }

        $requestHost = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));
        if ($requestHost !== '') {
            $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
            $normalized = $this->normalizeStoreHost(($https ? 'https://' : 'http://') . $requestHost);
            if ($normalized !== '') {
                $hosts[] = $normalized;
            }
        }

        return array_values(array_unique($hosts));
    }

    private function normalizeStoreHost(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }

        $parts = parse_url($url);
        if (!is_array($parts) || empty($parts['host'])) {
            return '';
        }

        $host = strtolower((string) $parts['host']);
        $port = isset($parts['port']) ? ':' . (int) $parts['port'] : '';
        $path = trim((string) ($parts['path'] ?? ''), '/');

        $path = preg_replace('#/api$#i', '', $path ?? '');
        $path = preg_replace('#/store-api$#i', '', $path ?? '');
        $path = trim((string) $path, '/');

        return $host . $port . ($path !== '' ? '/' . $path : '');
    }
}