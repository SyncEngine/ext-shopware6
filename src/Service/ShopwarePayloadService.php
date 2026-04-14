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
        if (!$entity instanceof ProductEntity) {
            return ['id' => $id];
        }

        $data = $this->normalizeValue($entity);
        $data['id'] = $id;
        return $data;
    }

    public function getOrderData(string $id, Context $context): array
    {
        if ($id === '') {
            return [];
        }

        $entity = $this->orderRepository->search(new Criteria([$id]), $context)->first();
        if (!$entity instanceof OrderEntity) {
            return ['id' => $id];
        }

        $data = $this->normalizeValue($entity);
        $data['id'] = $id;
        return $data;
    }

    public function getCustomerData(string $id, Context $context): array
    {
        if ($id === '') {
            return [];
        }

        $entity = $this->customerRepository->search(new Criteria([$id]), $context)->first();
        if (!$entity instanceof CustomerEntity) {
            return ['id' => $id];
        }

        $data = $this->normalizeValue($entity);
        $data['id'] = $id;
        return $data;
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
}