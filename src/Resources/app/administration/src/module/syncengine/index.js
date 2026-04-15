import './page/syncengine-trigger-map';

const { Application, Module } = Shopware;
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
}

Application.addServiceProvider('syncEngineApiService', (container) => {
    const initContainer = Application.getContainer('init');

    return new SyncEngineApiService(initContainer.httpClient, container.loginService);
});

Module.register('syncengine-trigger-map', {
    type: 'plugin',
    name: 'SyncEngineTriggerMap',
    title: 'syncengine-trigger-map.general.mainMenuItemGeneral',
    description: 'syncengine-trigger-map.general.descriptionTextModule',
    color: '#1f7a8c',
    icon: 'regular-chart-line',

    routes: {
        index: {
            component: 'syncengine-trigger-map-page',
            path: 'index',
        },
    },

    settingsItem: {
        group: 'plugins',
        to: 'syncengine.trigger.map.index',
        icon: 'regular-chart-line',
        label: 'syncengine-trigger-map.general.mainMenuItemGeneral',
    },

    snippets: {
        'en-GB': {
            'syncengine-trigger-map': {
                general: {
                    mainMenuItemGeneral: 'SyncEngine Trigger Map',
                    descriptionTextModule: 'Inspect and refresh mapped trigger endpoints',
                },
            },
        },
        'de-DE': {
            'syncengine-trigger-map': {
                general: {
                    mainMenuItemGeneral: 'SyncEngine Trigger-Map',
                    descriptionTextModule: 'Zuordnungen und Trigger-Endpunkte anzeigen und aktualisieren',
                },
            },
        },
    },
});
