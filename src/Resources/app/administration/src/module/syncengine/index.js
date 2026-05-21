import './page/syncengine-trigger-map';
import syncengineSettingsIcon from './components/syncengine-settings-icon';
import syncengineSettingsIconMono from './components/syncengine-settings-icon-mono';

const { Application, Module, Component } = Shopware;

Component.register('syncengine-settings-icon', syncengineSettingsIcon);
Component.register('syncengine-settings-icon-mono', syncengineSettingsIconMono);

const ApiService = Shopware.Classes.ApiService;

class SyncEngineApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'syncengine') {
        super(httpClient, loginService, apiEndpoint);
    }

    getTriggerMap() {
        return this.httpClient
            .get('_action/syncengine/trigger-map', {
                headers: this.getBasicHeaders(),
            })
            .then((response) => ApiService.handleResponse(response));
    }

    getEndpoints() {
        return this.httpClient
            .get('_action/syncengine/endpoints', {
                headers: this.getBasicHeaders(),
            })
            .then((response) => ApiService.handleResponse(response));
    }

    refreshTriggerMap() {
        return this.httpClient
            .post('_action/syncengine/refresh', {}, {
                headers: this.getBasicHeaders(),
            })
            .then((response) => ApiService.handleResponse(response));
    }

    getEndpointStatus(endpoint, refresh = false) {
        return this.httpClient
            .post('_action/syncengine/endpoint-status', {
                endpoint,
                refresh,
            }, {
                headers: this.getBasicHeaders(),
            })
            .then((response) => ApiService.handleResponse(response));
    }

    executeEndpoint(endpoint) {
        return this.httpClient
            .post('_action/syncengine/endpoint-execute', {
                endpoint,
            }, {
                headers: this.getBasicHeaders(),
            })
            .then((response) => ApiService.handleResponse(response));
    }
}

Application.addServiceProvider('syncEngineApiService', (container) => {
    const initContainer = Application.getContainer('init');

    return new SyncEngineApiService(initContainer.httpClient, container.loginService);
});

Module.register('syncengine-connector', {
    type: 'plugin',
    name: 'SyncEngineTriggerMap',
    title: 'syncengine-trigger-map.general.mainMenuItemGeneral',
    description: 'syncengine-trigger-map.general.descriptionTextModule',
    color: '#11196d',
    icon: 'regular-sync',

    routes: {
        index: {
            component: 'syncengine-trigger-map-page',
            path: 'index',
        },
    },

    settingsItem: {
        group: 'plugins',
        to: 'syncengine.connector.index',
        iconComponent: 'syncengine-settings-icon',
        backgroundEnabled: true,
        label: 'syncengine-trigger-map.general.mainMenuItemGeneral',
    },

    snippets: {
        'en-GB': {
            'syncengine-trigger-map': {
                general: {
                    mainMenuItemGeneral: 'SyncEngine Connector',
                    descriptionTextModule: 'Inspect endpoints and refresh mapped trigger connections',
                },
            },
        },
        'de-DE': {
            'syncengine-trigger-map': {
                general: {
                    mainMenuItemGeneral: 'SyncEngine Connector',
                    descriptionTextModule: 'Endpunkte und Trigger-Zuordnungen anzeigen und aktualisieren',
                },
            },
        },
    },
});
