/* eslint-disable vue/one-component-per-file */
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { defineComponent, h } from 'vue';
import SensorTokenDialog from '../sensors/SensorTokenDialog.vue';

const toast = vi.fn();
vi.mock('@/components/ui/toast/use-toast', () => ({
    useToast: () => ({ toast }),
}));

const PassThrough = defineComponent({ template: '<div><slot /></div>' });
const InputStub = defineComponent({
    props: {
        modelValue: {
            type: String,
            required: true,
        },
    },
    setup(props, { attrs }) {
        return () => h('input', { ...attrs, value: props.modelValue });
    },
});

describe('Stage Safety SensorTokenDialog', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        Object.defineProperty(navigator, 'clipboard', {
            configurable: true,
            value: { writeText: vi.fn().mockResolvedValue(undefined) },
        });
    });

    it('shows, copies, and acknowledges the one-time token', async () => {
        const wrapper = mount(SensorTokenDialog, {
            props: { open: true, token: 'secret-token' },
            global: {
                stubs: {
                    Dialog: PassThrough,
                    DialogContent: PassThrough,
                    DialogDescription: PassThrough,
                    DialogFooter: PassThrough,
                    DialogHeader: PassThrough,
                    DialogTitle: PassThrough,
                    Input: InputStub,
                    Button: {
                        emits: ['click'],
                        template: '<button v-bind="$attrs" @click="$emit(\'click\')"><slot /></button>',
                    },
                    Copy: true,
                    KeyRound: true,
                },
            },
        });

        expect(wrapper.find('input').element.value).toBe('secret-token');
        await wrapper.find('[aria-label="Copy sensor API token"]').trigger('click');

        expect(navigator.clipboard.writeText).toHaveBeenCalledWith('secret-token');
        expect(toast).toHaveBeenCalledWith(expect.objectContaining({ title: 'Token copied' }));

        await wrapper.findAll('button').at(-1)?.trigger('click');
        expect(wrapper.emitted('acknowledged')).toHaveLength(1);
    });
});
