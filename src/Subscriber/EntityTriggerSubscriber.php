<?php declare(strict_types=1);

namespace SyncEngine\Shopware\Subscriber;

use Shopware\Core\Checkout\Customer\CustomerEvents;
use Shopware\Core\Checkout\Order\OrderEvents;
use Shopware\Core\Content\Product\ProductEvents;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteResult;
use SyncEngine\Shopware\Service\TriggerService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class EntityTriggerSubscriber implements EventSubscriberInterface
{
    private TriggerService $triggerService;

    public function __construct(TriggerService $triggerService)
    {
        $this->triggerService = $triggerService;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ProductEvents::PRODUCT_WRITTEN_EVENT => 'onProductWritten',
            OrderEvents::ORDER_WRITTEN_EVENT => 'onOrderWritten',
            CustomerEvents::CUSTOMER_WRITTEN_EVENT => 'onCustomerWritten',
        ];
    }

    public function onProductWritten(EntityWrittenEvent $event): void
    {
        foreach ($event->getWriteResults() as $result) {
            $id = $this->resolveId($result);
            if ($id === '') {
                continue;
            }

            $this->triggerService->handleProductEvent($this->resolveKind($result), $id, $event->getContext());
        }
    }

    public function onOrderWritten(EntityWrittenEvent $event): void
    {
        foreach ($event->getWriteResults() as $result) {
            $id = $this->resolveId($result);
            if ($id === '') {
                continue;
            }

            $this->triggerService->handleOrderEvent($this->resolveKind($result), $id, $event->getContext());
        }
    }

    public function onCustomerWritten(EntityWrittenEvent $event): void
    {
        foreach ($event->getWriteResults() as $result) {
            $id = $this->resolveId($result);
            if ($id === '') {
                continue;
            }

            $this->triggerService->handleCustomerEvent($this->resolveKind($result), $id, $event->getContext());
        }
    }

    private function resolveKind(EntityWriteResult $result): string
    {
        return match ($result->getOperation()) {
            EntityWriteResult::OPERATION_INSERT => 'new',
            EntityWriteResult::OPERATION_DELETE => 'deleted',
            default => 'updated',
        };
    }

    private function resolveId(EntityWriteResult $result): string
    {
        $primary = $result->getPrimaryKey();
        if (is_string($primary)) {
            return $primary;
        }

        if (is_array($primary) && isset($primary['id'])) {
            return (string) $primary['id'];
        }

        return '';
    }
}