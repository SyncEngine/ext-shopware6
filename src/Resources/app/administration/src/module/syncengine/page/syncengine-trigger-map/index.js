import template from './syncengine-trigger-map.html.twig';
import './syncengine-trigger-map.scss';

const { Component, Filter } = Shopware;

Component.register('syncengine-trigger-map-page', {
    template,

    inject: ['syncEngineApiService'],

    data() {
        return {
            isLoading: false,
            isEndpointsLoading: false,
            isRefreshing: false,
            error: '',
            updatedAt: 0,
            rows: [],
            endpointRows: [],
        };
    },

    computed: {
        updatedAtLabel() {
            if (!this.updatedAt) {
                return 'never';
            }

            const date = new Date(this.updatedAt * 1000);
            return Filter.getByName('date')(date);
        },

        columns() {
            return [
                {
                    property: 'trigger',
                    label: 'Trigger',
                    primary: true,
                    allowResize: true,
                },
                {
                    property: 'endpointCount',
                    label: 'Endpoint count',
                    allowResize: true,
                    align: 'right',
                },
                {
                    property: 'endpointList',
                    label: 'Endpoints',
                    allowResize: true,
                },
            ];
        },

        endpointColumns() {
            return [
                {
                    property: 'name',
                    label: 'Name',
                    primary: true,
                    allowResize: true,
                },
                {
                    property: 'endpoint',
                    label: 'Endpoint',
                    allowResize: true,
                },
                {
                    property: 'statusLabel',
                    label: 'Status',
                    allowResize: true,
                },
                {
                    property: 'traceLabel',
                    label: 'Trace',
                    allowResize: true,
                },
                {
                    property: 'actions',
                    label: 'Actions',
                    width: '100px',
                    allowResize: false,
                    align: 'right',
                },
            ];
        },
    },

    created() {
        this.loadInitialData();
    },

    methods: {
        async loadInitialData() {
            await Promise.all([
                this.loadMap(),
                this.loadEndpoints(),
            ]);
        },

        async loadMap() {
            this.error = '';
            this.isLoading = true;

            try {
                const result = await this.syncEngineApiService.getTriggerMap();
                const map = result.triggerMap || {};

                this.updatedAt = Number(result.updatedAt || 0);
                this.rows = Object.entries(map).map(([trigger, endpoints]) => ({
                    trigger,
                    endpoints: Array.isArray(endpoints) ? endpoints : [],
                    endpointCount: Array.isArray(endpoints) ? endpoints.length : 0,
                    endpointList: Array.isArray(endpoints) ? endpoints.join(', ') : '',
                }));
            } catch (e) {
                this.error = e?.message || 'Failed to load trigger map';
            } finally {
                this.isLoading = false;
            }
        },

        async loadEndpoints() {
            this.error = '';
            this.isEndpointsLoading = true;

            try {
                const result = await this.syncEngineApiService.getEndpoints();
                const endpoints = Array.isArray(result.endpoints) ? result.endpoints : [];

                this.endpointRows = endpoints
                    .map((item) => {
                        const endpoint = String(item?.value || '').trim();
                        const name = String(item?.label || endpoint).trim() || endpoint;

                        if (!endpoint) {
                            return null;
                        }

                        return {
                            id: endpoint,
                            name,
                            endpoint,
                            statusLabel: 'not loaded',
                            traceLabel: '-',
                            statusLoaded: false,
                            statusLoading: false,
                            executeLoading: false,
                        };
                    })
                    .filter((item) => item !== null);
            } catch (e) {
                this.error = e?.message || 'Failed to load endpoints';
            } finally {
                this.isEndpointsLoading = false;
            }
        },

        async onLoadEndpointStatus(item) {
            if (!item || !item.endpoint || item.statusLoading) {
                return;
            }

            item.statusLoading = true;

            try {
                const result = await this.syncEngineApiService.getEndpointStatus(item.endpoint, item.statusLoaded);
                const runningCount = Array.isArray(result.running) ? result.running.length : 0;
                const scheduledCount = Array.isArray(result.scheduled) ? result.scheduled.length : 0;
                const queuedCount = Array.isArray(result.queued) ? result.queued.length : 0;

                item.statusLabel = String(result.status || 'unknown').toLowerCase();
                item.traceLabel = `running: ${runningCount} | scheduled: ${scheduledCount} | queued: ${queuedCount}`;
                item.statusLoaded = true;
            } catch (e) {
                item.statusLabel = e?.message || 'Failed to load status';
                item.traceLabel = '-';
            } finally {
                item.statusLoading = false;
            }
        },

        async onExecuteEndpoint(item) {
            if (!item || !item.endpoint || item.executeLoading) {
                return;
            }

            item.executeLoading = true;

            try {
                await this.syncEngineApiService.executeEndpoint(item.endpoint);
            } catch (e) {
                item.statusLabel = e?.message || 'Endpoint execution failed';
            } finally {
                item.executeLoading = false;
            }
        },

        async onRefresh() {
            this.isRefreshing = true;
            this.error = '';

            try {
                await this.syncEngineApiService.refreshTriggerMap();
                await Promise.all([
                    this.loadMap(),
                    this.loadEndpoints(),
                ]);
            } catch (e) {
                this.error = e?.message || 'Failed to refresh trigger map';
            } finally {
                this.isRefreshing = false;
            }
        },
    },
});
