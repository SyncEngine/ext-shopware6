<?php declare(strict_types=1);

namespace SyncEngine\Shopware\Service;

class EndpointDispatcherService
{
    private ClientService $clientService;

    public function __construct(ClientService $clientService)
    {
        $this->clientService = $clientService;
    }

    public function triggerEndpoints(array $endpoints, array $payload = [], array $meta = []): array
    {
        $client = $this->clientService->getClient();
        if (!$client) {
            return [];
        }

        $results = [];
        foreach (array_values(array_filter(array_map('strval', $endpoints))) as $endpoint) {
            $results[$endpoint] = $client->triggerEndpoint($endpoint, $payload);
        }

        return $results;
    }
}