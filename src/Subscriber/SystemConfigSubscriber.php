<?php declare(strict_types=1);

namespace SyncEngine\Shopware\Subscriber;

use Shopware\Core\System\SystemConfig\Event\SystemConfigChangedEvent;
use SyncEngine\Shopware\Service\ClientService;
use SyncEngine\Shopware\Service\TriggerService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class SystemConfigSubscriber implements EventSubscriberInterface
{
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
        if (!str_starts_with($event->getKey(), 'SyncEngineConnector.config.')) {
            return;
        }

        $this->clientService->clearApiCache();
        $this->triggerService->clearTriggerEndpointMapCache();
    }
}