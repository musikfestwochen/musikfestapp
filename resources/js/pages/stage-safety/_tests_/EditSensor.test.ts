import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { nextTick } from 'vue';
import EditSensor from '../EditSensor.vue';

const mocks = vi.hoisted(() => ({
    confirm: vi.fn(),
    post: vi.fn(),
    request: { processing: false, post: vi.fn() },
}));

vi.mock('@/composables/useConfirmDialog', () => ({
    useConfirmDialog: () => ({ confirm: mocks.confirm }),
}));

vi.mock('@/composables/usePermissions', () => ({
    usePermissions: () => ({ can: () => true }),
}));

vi.mock('@inertiajs/vue3', () => ({
    Head: { template: '<div />' },
    Link: { template: '<a><slot /></a>' },
    router: { reload: vi.fn() },
    useForm: (data: object) => ({ ...data, errors: {}, processing: false, put: vi.fn() }),
    useHttp: () => mocks.request,
}));

const organization = { id: 1, slug: 'mfw', name: 'MFW', created_at: '', updated_at: '' };
const sensor = {
    id: 1,
    organization_id: 1,
    manufacturer: 'Broadweigh',
    model: 'BW-WSS',
    identifier: 'ABC123',
    name: 'Main Stage',
    location: 'Roof',
    stale_after_seconds: 300,
    archived_at: null,
    created_at: '',
    updated_at: '',
    has_active_token: true,
};

describe('Stage Safety sensor editing', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        mocks.request.processing = false;
        mocks.request.post = mocks.post;
        vi.stubGlobal(
            'route',
            vi.fn((name: string) => name),
        );
    });

    it('prevents concurrent token regeneration confirmation flows', async () => {
        let resolveConfirmation!: (confirmed: boolean) => void;
        mocks.confirm.mockImplementation(
            () =>
                new Promise((resolve) => {
                    resolveConfirmation = resolve;
                }),
        );
        mocks.post.mockResolvedValue({ token: 'secret' });
        const wrapper = mount(EditSensor, {
            props: { organization, sensor, sensorTypes: [] },
            global: {
                mocks: { route: (name: string) => name },
                stubs: {
                    Layout: { template: '<main><slot /></main>' },
                    Heading: true,
                    SensorForm: true,
                    SensorTokenDialog: true,
                    ConfirmActionButton: true,
                },
            },
        });
        const button = wrapper.findAll('button').find((candidate) => candidate.text().includes('Replace Token'))!;

        button.element.click();
        button.element.click();
        await nextTick();

        expect(mocks.confirm).toHaveBeenCalledOnce();
        expect(button.attributes('disabled')).toBeDefined();

        resolveConfirmation(true);
        await flushPromises();

        expect(mocks.post).toHaveBeenCalledOnce();
        expect(button.attributes('disabled')).toBeUndefined();
    });
});
