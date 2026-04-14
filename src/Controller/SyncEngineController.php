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
        methods: ['GET'],
        defaults: ['auth_required' => false]
    )]
    public function status(): JsonResponse
    {
        $client = $this->clientService->getClient();
        $status = $client ? $client->status() : 'offline';

        return new JsonResponse([
            'success' => true,
            'status' => $status === '' ? 'offline' : $status,
        ]);
    }

    #[Route(
        path: '/api/_action/syncengine/refresh',
        name: 'api.action.syncengine.refresh',
        methods: ['POST'],
        defaults: ['auth_required' => false]
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
}