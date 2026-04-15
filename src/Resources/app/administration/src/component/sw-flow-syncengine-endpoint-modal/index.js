import template from './sw-flow-syncengine-endpoint-modal.html.twig';

const { Component } = Shopware;

Component.register('sw-flow-syncengine-endpoint-modal', {
    template,

    mixins: [
        'notification',
    ],

    inject: [
        'syncEngineApiService',
    ],

    props: {
        sequence: {
            type: Object,
            required: true,
        },
    },

    data() {
        const config = this.sequence?.config || {};

        return {
            endpoint: config.endpoint || '',
            triggerEventName: config.trigger || '',
            custom: this.normalizeCustom(config.custom),
            endpointOptions: [],
            isLoadingEndpoints: true,
        };
    },

    created() {
        this.loadEndpoints();
    },

    methods: {
        loadEndpoints() {
            this.isLoadingEndpoints = true;
            this.syncEngineApiService.getEndpoints()
                .then((response) => {
                    this.endpointOptions = (response?.endpoints || []).map((ep) => ({
                        value: ep.value,
                        label: ep.label || ep.value,
                    }));
                })
                .catch(() => {
                    this.createNotificationError({
                        title: 'Could not load endpoints',
                        message: 'Make sure SyncEngine is connected and configured.',
                    });
                })
                .finally(() => {
                    this.isLoadingEndpoints = false;
                });
        },

        normalizeCustom(custom) {
            if (!custom) {
                return '';
            }

            if (typeof custom === 'string') {
                return custom;
            }

            try {
                return JSON.stringify(custom, null, 2);
            } catch (e) {
                return '';
            }
        },

        onClose() {
            this.$emit('modal-close');
        },

        onSave() {
            const endpoint = (this.endpoint || '').trim();
            if (!endpoint) {
                this.createNotificationError({
                    title: 'Missing endpoint',
                    message: 'Please select an endpoint.',
                });
                return;
            }

            const custom = (this.custom || '').trim();
            if (custom !== '') {
                try {
                    JSON.parse(custom);
                } catch (e) {
                    this.createNotificationError({
                        title: 'Invalid JSON',
                        message: 'Custom payload must be valid JSON.',
                    });
                    return;
                }
            }

            this.$emit('process-finish', {
                ...this.sequence,
                config: {
                    endpoint,
                    trigger: (this.triggerEventName || '').trim(),
                    custom,
                },
            });
        },
    },
});
