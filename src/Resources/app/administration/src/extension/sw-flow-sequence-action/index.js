import { ACTION, MODAL } from '../../constant/syncengine-flow-action.constant';
import { SYNCENGINE_FLOW_ICON_RAW } from '../../constant/syncengine-icon';

const { Component } = Shopware;

Component.override('sw-flow-sequence-action', {
    computed: {
        modalName() {
            if (this.selectedAction === ACTION.TRIGGER_SYNCENGINE_ENDPOINT) {
                return MODAL.TRIGGER_SYNCENGINE_ENDPOINT;
            }

            return this.$super('modalName');
        },
    },

    methods: {
        getActionTitle(actionName) {
            if (actionName === ACTION.TRIGGER_SYNCENGINE_ENDPOINT) {
                return {
                    value: actionName,
                    iconRaw: SYNCENGINE_FLOW_ICON_RAW,
                    label: 'Trigger SyncEngine Endpoint',
                    group: 'general',
                };
            }

            return this.$super('getActionTitle', actionName);
        },

        getActionDescriptions(sequence) {
            if (sequence.actionName !== ACTION.TRIGGER_SYNCENGINE_ENDPOINT) {
                return this.$super('getActionDescriptions', sequence);
            }

            const endpoint = sequence?.config?.endpoint || '-';
            const trigger = sequence?.config?.trigger || 'auto';

            return `Endpoint: ${endpoint} | Trigger: ${trigger}`;
        },
    },
});
