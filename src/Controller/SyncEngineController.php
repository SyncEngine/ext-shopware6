<?php declare(strict_types=1);

namespace SyncEngine\Shopware\Controller;

use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use SyncEngine\Shopware\Service\ClientService;
use SyncEngine\Shopware\Service\RefreshTrustService;
use SyncEngine\Shopware\Service\TriggerService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => ['api']])]
class SyncEngineController extends AbstractController
{
    private const THROTTLE_UNTRUSTED_SECONDS = 10;
    private const THROTTLE_TRUSTED_SECONDS = 1;

    private ClientService $clientService;
    private TriggerService $triggerService;
    private RefreshTrustService $refreshTrustService;
    private SystemConfigService $config;

    public function __construct(
        ClientService $clientService,
        TriggerService $triggerService,
        RefreshTrustService $refreshTrustService,
        SystemConfigService $config
    ) {
        $this->clientService = $clientService;
        $this->triggerService = $triggerService;
        $this->refreshTrustService = $refreshTrustService;
        $this->config = $config;
    }

    #[Route(
        path: '/api/_action/syncengine/status',
        name: 'api.action.syncengine.status',
        methods: ['GET']
    )]
    public function status(): JsonResponse
    {
        return new JsonResponse([
            'success' => true,
            'status' => 'online',
        ]);
    }

    #[Route(
        path: '/api/_action/syncengine/refresh',
        name: 'api.action.syncengine.refresh',
        methods: ['POST']
    )]
    public function refresh(Request $request): JsonResponse
    {
        $connectionRef = strtolower(trim((string) $request->headers->get('X-SyncEngine-Connection', '')));
        $trusted = $connectionRef !== '' && $this->refreshTrustService->isTrustedConnectionRef($connectionRef);

        $ttl = $trusted ? self::THROTTLE_TRUSTED_SECONDS : self::THROTTLE_UNTRUSTED_SECONDS;
        $cacheKey = TriggerService::CONFIG_REFRESH_THROTTLE_PREFIX . ($trusted ? 'trusted' : 'untrusted');
        $last = (int) ($this->config->get($cacheKey) ?? 0);

        if ($ttl > 0 && $last > 0 && (time() - $last) < $ttl) {
            return new JsonResponse([
                'success' => true,
                'refreshed' => false,
                'reason' => 'throttled',
                'trusted' => $trusted,
            ]);
        }

        $this->config->set($cacheKey, time());
        $this->triggerService->clearTriggerEndpointMapCache();

        return new JsonResponse([
            'success' => true,
            'refreshed' => true,
            'trusted' => $trusted,
            'timestamp' => time(),
        ]);
    }

    #[Route(
        path: '/api/_action/syncengine/trigger-map',
        name: 'api.action.syncengine.trigger_map',
        methods: ['GET']
    )]
    public function triggerMap(): JsonResponse
    {
        return new JsonResponse([
            'success' => true,
            'updatedAt' => (int) ($this->config->get(TriggerService::CONFIG_TRIGGER_MAP_TS) ?? 0),
            'triggerMap' => $this->triggerService->getTriggerEndpointMap(),
        ]);
    }

    #[Route(
        path: '/api/_action/syncengine/endpoints',
        name: 'api.action.syncengine.endpoints',
        methods: ['GET']
    )]
    public function endpoints(): JsonResponse
    {
        $client = $this->clientService->getClient();
        if (!$client) {
            return new JsonResponse(['success' => false, 'endpoints' => [], 'error' => 'SyncEngine client not configured.']);
        }

        $raw = $client->listEndpoints();

        $endpoints = [];
        foreach ($raw as $item) {
            if (!is_array($item)) {
                continue;
            }
            $key = trim((string) ($item['endpoint'] ?? $item['ref'] ?? ''));
            $label = trim((string) ($item['label'] ?? $item['name'] ?? $key));
            if ($key !== '') {
                $endpoints[] = ['value' => $key, 'label' => $label !== '' ? $label : $key];
            }
        }

        return new JsonResponse(['success' => true, 'endpoints' => $endpoints]);
    }

    #[Route(
        path: '/api/_action/syncengine/endpoint-status',
        name: 'api.action.syncengine.endpoint_status',
        methods: ['POST']
    )]
    public function endpointStatus(Request $request): JsonResponse
    {
        $client = $this->clientService->getClient();
        if (!$client) {
            return new JsonResponse([
                'success' => false,
                'error' => 'SyncEngine client not configured.',
            ]);
        }

        $payload = json_decode((string) $request->getContent(), true);
        $payload = is_array($payload) ? $payload : [];

        $endpoint = trim((string) ($payload['endpoint'] ?? ''));
        $refresh = (bool) ($payload['refresh'] ?? false);

        if ($endpoint === '') {
            return new JsonResponse([
                'success' => false,
                'error' => 'Endpoint not specified.',
            ]);
        }

        $result = $client->getEndpointStatus($endpoint, $refresh);
        $success = (bool) ($result['success'] ?? true);

        if (!$success) {
            return new JsonResponse([
                'success' => false,
                'endpoint' => $endpoint,
                'error' => (string) ($result['error'] ?? 'Failed to load endpoint status.'),
            ]);
        }

        return new JsonResponse([
            'success' => true,
            'endpoint' => $endpoint,
            'status' => strtolower((string) ($result['status'] ?? 'unknown')),
            'can_execute' => (bool) ($result['can_execute'] ?? false),
            'can_schedule' => (bool) ($result['can_schedule'] ?? false),
            'running' => is_array($result['running'] ?? null) ? $result['running'] : [],
            'scheduled' => is_array($result['scheduled'] ?? null) ? $result['scheduled'] : [],
            'queued' => is_array($result['queued'] ?? null) ? $result['queued'] : [],
        ]);
    }

    #[Route(
        path: '/api/_action/syncengine/endpoint-execute',
        name: 'api.action.syncengine.endpoint_execute',
        methods: ['POST']
    )]
    public function endpointExecute(Request $request): JsonResponse
    {
        $client = $this->clientService->getClient();
        if (!$client) {
            return new JsonResponse([
                'success' => false,
                'error' => 'SyncEngine client not configured.',
            ]);
        }

        $payload = json_decode((string) $request->getContent(), true);
        $payload = is_array($payload) ? $payload : [];

        $endpoint = trim((string) ($payload['endpoint'] ?? ''));

        if ($endpoint === '') {
            return new JsonResponse([
                'success' => false,
                'error' => 'Endpoint not specified.',
            ]);
        }

        $result = $client->executeEndpoint($endpoint);
        $success = (bool) ($result['success'] ?? true);
        if (!$success) {
            return new JsonResponse([
                'success' => false,
                'endpoint' => $endpoint,
                'error' => (string) ($result['error'] ?? 'Endpoint execution failed.'),
            ]);
        }

        return new JsonResponse([
            'success' => true,
            'endpoint' => $endpoint,
            'result' => $result,
        ]);
    }
}