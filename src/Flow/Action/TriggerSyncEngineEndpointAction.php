<?php declare(strict_types=1);

namespace SyncEngine\Shopware\Flow\Action;

use Shopware\Core\Content\Flow\Dispatching\Action\FlowAction;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use SyncEngine\Shopware\Service\EndpointDispatcherService;

class TriggerSyncEngineEndpointAction extends FlowAction
{
    private const ACTION_NAME = 'action.syncengine.trigger_endpoint';

    private EndpointDispatcherService $endpointDispatcherService;

    public function __construct(EndpointDispatcherService $endpointDispatcherService)
    {
        $this->endpointDispatcherService = $endpointDispatcherService;
    }

    public static function getName(): string
    {
        return self::ACTION_NAME;
    }

    public function requirements(): array
    {
        return [];
    }

    public function handleFlow(StorableFlow $flow): void
    {
        $config = (array) $flow->getConfig();
        $endpoint = trim((string) ($config['endpoint'] ?? ''));
        if ($endpoint === '') {
            return;
        }

        $trigger = trim((string) ($config['trigger'] ?? ''));
        if ($trigger === '') {
            $trigger = 'shopware_flow_' . str_replace('.', '_', strtolower((string) $flow->getName()));
        }

        $custom = $this->resolveCustomData($config['custom'] ?? []);
        $entity = $this->resolveEntity($flow);
        $id = (string) ($entity['id'] ?? '');

        $payload = [
            'id' => $id,
            'event' => $trigger,
            'data' => [
                'flow' => [
                    'name' => (string) $flow->getName(),
                    'trigger' => $trigger,
                ],
                'entity' => $entity,
                'custom' => $custom,
            ],
            'request' => [
                'trigger' => $trigger,
                'source' => 'flow_builder',
            ],
        ];

        $this->endpointDispatcherService->triggerEndpoints(
            [$endpoint],
            $payload,
            [
                'source' => 'shopware',
                'trigger' => $trigger,
                'context' => ['flow' => (string) $flow->getName()],
            ]
        );
    }

    private function resolveEntity(StorableFlow $flow): array
    {
        $candidates = [
            ['type' => 'order', 'idStore' => 'orderId', 'dataKey' => 'order'],
            ['type' => 'customer', 'idStore' => 'customerId', 'dataKey' => 'customer'],
            ['type' => 'product', 'idStore' => 'productId', 'dataKey' => 'product'],
        ];

        foreach ($candidates as $candidate) {
            if (!$flow->hasStore($candidate['idStore'])) {
                continue;
            }

            $id = (string) $flow->getStore($candidate['idStore']);
            if ($id === '') {
                continue;
            }

            return [
                'type' => $candidate['type'],
                'id' => $id,
                'payload' => $this->sanitizeValue($flow->getData($candidate['dataKey'])),
            ];
        }

        return [
            'type' => '',
            'id' => '',
            'payload' => [],
        ];
    }

    private function resolveCustomData($custom): array
    {
        if (is_array($custom)) {
            return $this->sanitizeValue($custom);
        }

        if (!is_string($custom)) {
            return [];
        }

        $custom = trim($custom);
        if ($custom === '') {
            return [];
        }

        $decoded = json_decode($custom, true);
        return is_array($decoded) ? $this->sanitizeValue($decoded) : [];
    }

    private function sanitizeValue($value, int $depth = 0)
    {
        if ($depth > 4) {
            return null;
        }

        if ($value === null || is_scalar($value)) {
            return $value;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format(DATE_ATOM);
        }

        if ($value instanceof \JsonSerializable) {
            return $this->sanitizeValue($value->jsonSerialize(), $depth + 1);
        }

        if (is_object($value)) {
            if (method_exists($value, 'jsonSerialize')) {
                return $this->sanitizeValue($value->jsonSerialize(), $depth + 1);
            }

            if (method_exists($value, 'getVars')) {
                return $this->sanitizeValue($value->getVars(), $depth + 1);
            }

            return ['_class' => get_class($value)];
        }

        if (is_array($value)) {
            $clean = [];
            $count = 0;
            foreach ($value as $key => $item) {
                if (++$count > 100) {
                    break;
                }
                $clean[(string) $key] = $this->sanitizeValue($item, $depth + 1);
            }
            return $clean;
        }

        return null;
    }
}
