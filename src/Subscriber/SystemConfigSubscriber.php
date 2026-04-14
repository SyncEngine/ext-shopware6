<?php declare(strict_types=1);

namespace SyncEngine\Shopware\Subscriber;

use Shopware\Core\System\SystemConfig\Event\SystemConfigChangedEvent;
use SyncEngine\Shopware\Service\ClientService;
use SyncEngine\Shopware\Service\TriggerService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class SystemConfigSubscriber implements EventSubscriberInterface
{
    /**
     * Only react to user-managed settings changes.
     * Internal cache/throttle keys must not trigger more cache writes/deletes.
     */
    private const WATCHED_CONFIG_KEYS = [
        ClientService::CONFIG_API_HOST,
        ClientService::CONFIG_API_TOKEN,
        ClientService::CONFIG_API_AUTH_HEADER,
        TriggerService::CONFIG_TRIGGERS_ENABLED,
    ];

    private ClientService $clientService;
    private TriggerService $triggerService;

    public function __construct(ClientService $clientService, TriggerService $triggerService)
    {
        $this->clientService = $clientService;
        $this->triggerService = $triggerService;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            SystemConfigChangedEvent::class => 'onSystemConfigChanged',
        ];
    }

    public function onSystemConfigChanged(SystemConfigChangedEvent $event): void
    {
        $key = $event->getKey();

        if (!in_array($key, self::WATCHED_CONFIG_KEYS, true)) {
            return;
        }

        $this->clientService->clearApiCache();
        $this->triggerService->clearTriggerEndpointMapCache();
    }
}