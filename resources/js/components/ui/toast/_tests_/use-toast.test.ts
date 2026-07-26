import { nextTick } from 'vue';
import { describe, expect, it } from 'vitest';
import { toast, useToast } from '../use-toast';

describe('useToast', () => {
    it('reactively adds toasts', async () => {
        const { toasts } = useToast();

        toast({ title: 'Saved' });
        await nextTick();

        expect(toasts.value).toHaveLength(1);
        expect(toasts.value[0]?.title).toBe('Saved');
    });
});
