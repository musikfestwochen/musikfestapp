import { describe, expect, it } from 'vitest';
import { ref } from 'vue';
import { cn, valueUpdater } from '../utils';

describe('cn', () => {
    it('merges class names and removes duplicates', () => {
        expect(cn('foo', 'bar')).toBe('foo bar');
        expect(cn('foo', false, 'bar', undefined)).toBe('foo bar');
        // The current implementation removes all duplicate class names, not just Tailwind classes
        expect(cn('foo', 'foo', 'bar')).toBe('foo bar');
    });

    it('merges Tailwind classes correctly', () => {
        expect(cn('p-2', 'p-4')).toBe('p-4');
        expect(cn('text-red-500', 'text-blue-500')).toBe('text-blue-500');
    });
});

describe('valueUpdater', () => {
    it('sets ref value directly if value is provided', () => {
        const r = ref(1);
        valueUpdater(2, r);
        expect(r.value).toBe(2);
    });

    it('updates ref value using updater function', () => {
        const r = ref(1);
        valueUpdater((v: number) => v + 5, r);
        expect(r.value).toBe(6);
    });
});
