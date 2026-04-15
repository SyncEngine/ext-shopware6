import template from './sw-flow-syncengine-endpoint-modal.html.twig';

const { Component } = Shopware;

Component.register('sw-flow-syncengine-endpoint-modal', {
    template,

    mixins: [
        'notification',
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
            trigger: config.trigger || '',
            custom: this.normalizeCustom(config.custom),
        };
    },

    methods: {
        normalizeCustom(custom) {
            if (!custom) {
                return '{}';
            }

            if (typeof custom === 'string') {
                return custom;
            }

            try {
                return JSON.stringify(custom, null, 2);
            } catch (e) {
                return '{}';
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
                    message: 'Endpoint is required for Trigger SyncEngine Endpoint.',
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
                    trigger: (this.trigger || '').trim(),
                    custom,
                },
            });
        },
    },
});
