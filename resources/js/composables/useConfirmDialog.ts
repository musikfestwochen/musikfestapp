import { computed, ref } from 'vue';

export type ConfirmDialogVariant = 'default' | 'destructive';

export interface ConfirmDialogOptions {
    title: string;
    description?: string;
    confirmText?: string;
    cancelText?: string;
    variant?: ConfirmDialogVariant;
    // optional metadata for consumers
    meta?: Record<string, unknown>;
}

interface PendingDialog {
    id: number;
    options: ConfirmDialogOptions;
    resolve: (value: boolean) => void;
    reject: (reason?: unknown) => void;
}

const queue = ref<PendingDialog[]>([]);
const current = ref<PendingDialog | null>(null);
const isOpen = ref(false);
let nextId = 1;

function showNext() {
    if (current.value || queue.value.length === 0) return;
    current.value = queue.value.shift() || null;
    if (current.value) isOpen.value = true;
}

export function useConfirmDialog() {
    function confirm(options: ConfirmDialogOptions): Promise<boolean> {
        return new Promise<boolean>((resolve, reject) => {
            queue.value.push({
                id: nextId++,
                options: {
                    confirmText: 'Continue',
                    cancelText: 'Cancel',
                    variant: 'default',
                    ...options,
                },
                resolve,
                reject,
            });
            showNext();
        });
    }

    function onConfirm() {
        if (!current.value) return;
        current.value.resolve(true);
        close();
    }

    function onCancel() {
        if (!current.value) return;
        current.value.resolve(false);
        close();
    }

    function close() {
        isOpen.value = false;
        current.value = null;
        // allow transition to complete before showing next
        queueMicrotask(showNext);
    }

    return {
        isOpen: computed(() => isOpen.value),
        current: computed(() => current.value?.options ?? null),
        confirm,
        onConfirm,
        onCancel,
        close,
    };
}
