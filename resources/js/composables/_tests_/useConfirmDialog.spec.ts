import { beforeEach, describe, expect, it, vi } from 'vitest';

// We'll re-import the module before each test to reset module-scoped state
type UseConfirmDialog = typeof import('../useConfirmDialog').useConfirmDialog;
let useConfirmDialog: UseConfirmDialog;

async function reloadComposable() {
    vi.resetModules();
    const mod = await import('../useConfirmDialog');
    useConfirmDialog = mod.useConfirmDialog;
}

// Utility: flush microtasks (including the queueMicrotask used in close())
async function flushMicrotasks(times = 1) {
    for (let i = 0; i < times; i++) {
        await Promise.resolve();
    }
}

beforeEach(async () => {
    await reloadComposable();
});

describe('useConfirmDialog', () => {
    it('exposes initial state (isOpen=false, current=null)', () => {
        const dialog = useConfirmDialog();
        expect(dialog.isOpen.value).toBe(false);
        expect(dialog.current.value).toBeNull();
    });

    it('merges default options when calling confirm', async () => {
        const dialog = useConfirmDialog();

        const p = dialog.confirm({ title: 'Delete item' });
        // should open immediately because queue was empty
        expect(dialog.isOpen.value).toBe(true);
        expect(dialog.current.value).not.toBeNull();

        const current = dialog.current.value!;
        expect(current.title).toBe('Delete item');
        expect(current.confirmText).toBe('Continue');
        expect(current.cancelText).toBe('Cancel');
        expect(current.variant).toBe('default');

        // clean up to avoid pending promise
        dialog.onCancel();
        await expect(p).resolves.toBe(false);
    });

    it('resolves true on onConfirm and closes', async () => {
        const dialog = useConfirmDialog();

        const p = dialog.confirm({ title: 'Proceed?' });
        expect(dialog.isOpen.value).toBe(true);

        dialog.onConfirm();
        await expect(p).resolves.toBe(true);

        // dialog should be closed now
        expect(dialog.isOpen.value).toBe(false);
        expect(dialog.current.value).toBeNull();
    });

    it('resolves false on onCancel and closes', async () => {
        const dialog = useConfirmDialog();

        const p = dialog.confirm({ title: 'Proceed?' });
        expect(dialog.isOpen.value).toBe(true);

        dialog.onCancel();
        await expect(p).resolves.toBe(false);

        expect(dialog.isOpen.value).toBe(false);
        expect(dialog.current.value).toBeNull();
    });

    it('queues multiple confirmations and opens next after microtask', async () => {
        const dialog = useConfirmDialog();

        const first = dialog.confirm({ title: 'First' });
        const second = dialog.confirm({ title: 'Second', confirmText: 'Yes' });

        // First should be active
        expect(dialog.isOpen.value).toBe(true);
        expect(dialog.current.value?.title).toBe('First');

        // Confirm the first
        dialog.onConfirm();
        await expect(first).resolves.toBe(true);

        // close() schedules showing next on microtask queue
        await flushMicrotasks();

        // Now second should be active
        expect(dialog.isOpen.value).toBe(true);
        expect(dialog.current.value?.title).toBe('Second');
        expect(dialog.current.value?.confirmText).toBe('Yes');

        // Cancel the second
        dialog.onCancel();
        await expect(second).resolves.toBe(false);

        // After closing, no more dialogs
        expect(dialog.isOpen.value).toBe(false);
        expect(dialog.current.value).toBeNull();
    });

    it('passes through meta information', async () => {
        const dialog = useConfirmDialog();

        const meta = { foo: 1, bar: 'baz' };
        const p = dialog.confirm({ title: 'Meta test', meta });

        expect(dialog.current.value?.meta).toEqual(meta);

        // Clean up
        dialog.onCancel();
        await expect(p).resolves.toBe(false);
    });
});
