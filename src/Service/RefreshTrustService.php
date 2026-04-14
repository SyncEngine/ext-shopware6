<?php declare(strict_types=1);

namespace SyncEngine\Shopware\Service;

use Shopware\Core\System\SystemConfig\SystemConfigService;

class RefreshTrustService
{
    private const CONFIG_CONNECTION_REFS = 'SyncEngineConnector.config.knownConnectionRefs';

    private SystemConfigService $config;

    public function __construct(SystemConfigService $config)
    {
        $this->config = $config;
    }

    public function rememberConnectionRefs(array $refs): void
    {
        $refs = $this->normalizeRefs($refs);
        if ($refs === []) {
            return;
        }

        $existing = $this->getConnectionRefs();
        $merged = array_values(array_unique(array_merge($existing, $refs)));
        sort($merged);

        if ($merged !== $existing) {
            $this->config->set(self::CONFIG_CONNECTION_REFS, json_encode($merged));
        }
    }

    public function isTrustedConnectionRef(string $ref): bool
    {
        $ref = $this->normalizeRef($ref);
        if ($ref === '') {
            return false;
        }

        return in_array($ref, $this->getConnectionRefs(), true);
    }

    private function getConnectionRefs(): array
    {
        $raw = (string) ($this->config->get(self::CONFIG_CONNECTION_REFS) ?? '[]');
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [];
        }

        $refs = $this->normalizeRefs($decoded);
        sort($refs);

        return $refs;
    }

    private function normalizeRefs(array $refs): array
    {
        $normalized = array_map(fn ($ref) => $this->normalizeRef((string) $ref), $refs);
        return array_values(array_filter(array_unique($normalized)));
    }

    private function normalizeRef(string $ref): string
    {
        return strtolower(trim($ref));
    }
}