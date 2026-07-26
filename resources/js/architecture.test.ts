import { describe, expect, it } from 'vitest';

const browserSources = import.meta.glob('./**/*.{ts,vue}', { eager: true, import: 'default', query: '?raw' }) as Record<string, string>;

describe('frontend architecture', () => {
    it('uses Inertia HTTP instead of importing Axios directly', () => {
        const offenders = Object.entries(browserSources)
            .filter(([, source]) => /(?:from\s*|import\s*)['"]axios['"]/.test(source))
            .map(([path]) => path);

        expect(offenders).toEqual([]);
    });
});
