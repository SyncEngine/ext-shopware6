<?php declare(strict_types=1);

namespace SyncEngine\Shopware;

use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use SyncEngine\Shopware\Service\ClientService;
use SyncEngine\Shopware\Service\TriggerService;

class SyncEngineConnector extends Plugin
{
    public function install(InstallContext $installContext): void
    {
        parent::install($installContext);

        /** @var SystemConfigService $config */
        $config = $this->container->get(SystemConfigService::class);
        $config->set(ClientService::CONFIG_API_HOST, '');
        $config->set(ClientService::CONFIG_API_TOKEN, '');
        $config->set(ClientService::CONFIG_API_AUTH_HEADER, '');
        $config->set(TriggerService::CONFIG_TRIGGERS_ENABLED, true);
    }

    public function uninstall(UninstallContext $uninstallContext): void
    {
        parent::uninstall($uninstallContext);

        if ($uninstallContext->keepUserData()) {
            return;
        }

        /** @var SystemConfigService $config */
        $config = $this->container->get(SystemConfigService::class);
        $config->delete(ClientService::CONFIG_API_HOST);
        $config->delete(ClientService::CONFIG_API_TOKEN);
        $config->delete(ClientService::CONFIG_API_AUTH_HEADER);
        $config->delete(TriggerService::CONFIG_TRIGGERS_ENABLED);
        $config->delete(TriggerService::CONFIG_TRIGGER_MAP);
        $config->delete(TriggerService::CONFIG_TRIGGER_MAP_TS);
    }
}