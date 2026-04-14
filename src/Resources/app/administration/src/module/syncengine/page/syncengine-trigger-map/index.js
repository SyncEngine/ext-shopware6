import template from './syncengine-trigger-map.html.twig';

const { Component, Filter } = Shopware;

Component.register('syncengine-trigger-map-page', {
    template,

    inject: ['syncEngineApiService'],

    data() {
        return {
            isLoading: false,
            isRefreshing: false,
            error: '',
            updatedAt: 0,
            rows: [],
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
    },

    created() {
        this.loadMap();
    },

    methods: {
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
                }));
            } catch (e) {
                this.error = e?.message || 'Failed to load trigger map';
            } finally {
                this.isLoading = false;
            }
        },

        async onRefresh() {
            this.isRefreshing = true;
            this.error = '';

            try {
                await this.syncEngineApiService.refreshTriggerMap();
                await this.loadMap();
            } catch (e) {
                this.error = e?.message || 'Failed to refresh trigger map';
            } finally {
                this.isRefreshing = false;
            }
        },
    },
});
