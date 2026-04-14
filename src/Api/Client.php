<?php declare(strict_types=1);

namespace SyncEngine\Shopware\Api;

use RuntimeException;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpClient\HttpClient;

class Client
{
    private const CACHE_STATUS_KEY = 'SyncEngineConnector.config.apiStatusCache';
    private const CACHE_STATUS_TS_KEY = 'SyncEngineConnector.config.apiStatusCacheTs';
    private const CACHE_ENDPOINTS_KEY = 'SyncEngineConnector.config.apiEndpointsCache';
    private const CACHE_ENDPOINTS_TS_KEY = 'SyncEngineConnector.config.apiEndpointsCacheTs';

    private string $host;
    private string $token;
    private array $options;
    private string $root;
    private SystemConfigService $config;

    public function __construct(string $host, string $token, SystemConfigService $config, array $options = [])
    {
        $this->host = rtrim(trim($host), '/');
        $this->token = trim($token);
        $this->config = $config;
        $this->options = $options;
        if (!array_key_exists('version', $this->options)) {
            $this->options['version'] = 1;
        }

        $this->root = $this->host . '/api/';
    }

    public function clearCache(): void
    {
        $this->config->delete(self::CACHE_STATUS_KEY);
        $this->config->delete(self::CACHE_STATUS_TS_KEY);
        $this->config->delete(self::CACHE_ENDPOINTS_KEY);
        $this->config->delete(self::CACHE_ENDPOINTS_TS_KEY);
    }

    public function status(): string
    {
        $cached = (string) ($this->config->get(self::CACHE_STATUS_KEY) ?? '');
        $cachedTs = (int) ($this->config->get(self::CACHE_STATUS_TS_KEY) ?? 0);

        if ($cached !== '' && (time() - $cachedTs) < 300) {
            return $cached;
        }

        try {
            $result = $this->request('status', 'GET', ['version' => false]);
        } catch (\Throwable $e) {
            return $e->getMessage();
        }

        $status = strtolower((string) ($result['status'] ?? ''));
        if ($status !== '') {
            $this->config->set(self::CACHE_STATUS_KEY, $status);
            $this->config->set(self::CACHE_STATUS_TS_KEY, time());
        }

        return $status;
    }

    public function isOnline(): bool
    {
        return $this->status() === 'online';
    }

    public function listAutomations(): array
    {
        return $this->request('rest/v1/automation', 'GET', ['version' => false]);
    }

    public function listConnections(): array
    {
        return $this->request('rest/v1/connection', 'GET', ['version' => false]);
    }

    public function listEndpoints(): array
    {
        $cached = (string) ($this->config->get(self::CACHE_ENDPOINTS_KEY) ?? '');
        $cachedTs = (int) ($this->config->get(self::CACHE_ENDPOINTS_TS_KEY) ?? 0);

        if ($cached !== '' && (time() - $cachedTs) < 300) {
            $decoded = json_decode($cached, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        $result = $this->request('endpoint', 'GET', ['version' => false]);
        if ($result !== []) {
            $this->config->set(self::CACHE_ENDPOINTS_KEY, json_encode($result));
            $this->config->set(self::CACHE_ENDPOINTS_TS_KEY, time());
        }

        return $result;
    }

    public function triggerEndpoint(string $endpoint, array $payload = [], string $action = 'execute'): array
    {
        $endpoint = trim($endpoint, '/');
        $action = trim($action, '/');

        if ($endpoint === '' || $action === '') {
            return ['success' => false, 'error' => 'Invalid endpoint action request.'];
        }

        try {
            $result = $this->request(
                'endpoint/' . $endpoint . '/' . $action,
                'POST',
                [
                    'version' => false,
                    'body' => json_encode($payload),
                    'headers' => [
                        'Content-Type' => 'application/json',
                    ],
                ]
            );
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }

        return is_array($result) ? $result : ['success' => true, 'data' => $result];
    }

    public function request(string $endpoint, string $method = 'GET', array $options = []): array
    {
        $options = array_merge($this->options, $options);
        $headers = (array) ($options['headers'] ?? []);

        $authHeader = trim((string) ($options['auth_header'] ?? ''));
        if ($authHeader === '') {
            $headers['Authorization'] = 'Bearer ' . $this->token;
        } else {
            $headers[$authHeader] = $this->token;
        }

        $url = $this->root;
        if (array_key_exists('version', $options) && $options['version'] !== false) {
            $url .= 'v' . (int) $options['version'] . '/';
        }

        $url .= ltrim($endpoint, '/');

        $requestOptions = [
            'headers' => $headers,
            'timeout' => 30,
        ];

        $query = (array) ($options['query'] ?? []);
        if ($query !== []) {
            $requestOptions['query'] = $query;
        }

        if (array_key_exists('body', $options) && strtoupper($method) !== 'GET') {
            $requestOptions['body'] = $options['body'];
        }

        $response = HttpClient::create()->request(strtoupper($method), $url, $requestOptions);
        $code = $response->getStatusCode();
        $content = $response->getContent(false);
        $decoded = json_decode((string) $content, true);

        if ($code !== 200) {
            $this->clearCache();
            $message = 'HTTP ' . $code;
            if (is_array($decoded) && isset($decoded['message'])) {
                $message .= ': ' . (string) $decoded['message'];
            }

            throw new RuntimeException($message . ' [' . $url . ']');
        }

        if (!is_array($decoded)) {
            return [];
        }

        return $decoded;
    }
}