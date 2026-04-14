<?php declare(strict_types=1);

namespace SyncEngine\Shopware\Service;

use JsonSerializable;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;

class ShopwarePayloadService
{
    private EntityRepository $productRepository;
    private EntityRepository $orderRepository;
    private EntityRepository $customerRepository;

    public function __construct(
        EntityRepository $productRepository,
        EntityRepository $orderRepository,
        EntityRepository $customerRepository
    ) {
        $this->productRepository = $productRepository;
        $this->orderRepository = $orderRepository;
        $this->customerRepository = $customerRepository;
    }

    public function getProductData(string $id, Context $context): array
    {
        if ($id === '') {
            return [];
        }

        $entity = $this->productRepository->search(new Criteria([$id]), $context)->first();
        return $this->buildEntityPayload('product', $id, $entity instanceof ProductEntity ? $entity : null);
    }

    public function getOrderData(string $id, Context $context): array
    {
        if ($id === '') {
            return [];
        }

        $entity = $this->orderRepository->search(new Criteria([$id]), $context)->first();
        return $this->buildEntityPayload('order', $id, $entity instanceof OrderEntity ? $entity : null);
    }

    public function getCustomerData(string $id, Context $context): array
    {
        if ($id === '') {
            return [];
        }

        $entity = $this->customerRepository->search(new Criteria([$id]), $context)->first();
        return $this->buildEntityPayload('customer', $id, $entity instanceof CustomerEntity ? $entity : null);
    }

    public function getDeletedEntityData(string $type, string $id): array
    {
        return [
            'data' => [
                'id' => $id,
                'type' => $type,
                'attributes' => [],
                'relationships' => [],
                'meta' => null,
            ],
            'included' => [],
        ];
    }

    public function normalizeValue($value, int $depth = 0)
    {
        if ($depth >= 4) {
            if (is_scalar($value) || $value === null) {
                return $value;
            }

            return is_object($value) ? get_class($value) : gettype($value);
        }

        if (is_scalar($value) || $value === null) {
            return $value;
        }

        if ($value instanceof JsonSerializable) {
            return $this->normalizeValue($value->jsonSerialize(), $depth + 1);
        }

        if (is_array($value)) {
            $normalized = [];
            foreach ($value as $key => $item) {
                $normalized[$key] = $this->normalizeValue($item, $depth + 1);
            }

            return $normalized;
        }

        if (is_object($value)) {
            if (method_exists($value, 'getVars')) {
                return $this->normalizeValue($value->getVars(), $depth + 1);
            }

            return $this->normalizeValue(get_object_vars($value), $depth + 1);
        }

        return gettype($value);
    }

    private function buildEntityPayload(string $type, string $id, ?object $entity): array
    {
        if ($entity === null) {
            return $this->getDeletedEntityData($type, $id);
        }

        $normalized = $this->normalizeValue($entity);
        if (!is_array($normalized)) {
            return $this->getDeletedEntityData($type, $id);
        }

        $attributes = [];
        $relationships = [];

        foreach ($normalized as $key => $value) {
            if (!is_string($key) || $key === 'id' || $key === 'apiAlias') {
                continue;
            }

            if ($this->isRelationshipCandidate($value)) {
                $relationships[$key] = [
                    'data' => $this->toRelationshipData($value),
                ];
                continue;
            }

            $attributes[$key] = $value;
        }

        return [
            'data' => [
                'id' => $id,
                'type' => $type,
                'attributes' => $attributes,
                'relationships' => $relationships,
                'meta' => null,
            ],
            'included' => [],
            '_handler' => 'syncengine',
        ];
    }

    private function isRelationshipCandidate($value): bool
    {
        if (!is_array($value) || $value === []) {
            return false;
        }

        if (isset($value['id']) || isset($value['apiAlias'])) {
            return true;
        }

        $first = reset($value);
        return is_array($first) && (isset($first['id']) || isset($first['apiAlias']));
    }

    private function toRelationshipData($value)
    {
        if (!is_array($value)) {
            return null;
        }

        if (isset($value['id'])) {
            return [
                'id' => (string) $value['id'],
                'type' => (string) ($value['apiAlias'] ?? 'entity'),
            ];
        }

        $data = [];
        foreach ($value as $item) {
            if (!is_array($item) || !isset($item['id'])) {
                continue;
            }

            $data[] = [
                'id' => (string) $item['id'],
                'type' => (string) ($item['apiAlias'] ?? 'entity'),
            ];
        }

        return $data;
    }
}