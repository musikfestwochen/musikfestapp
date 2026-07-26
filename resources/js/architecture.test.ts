import { describe, expect, it } from 'vitest';

const browserSources = import.meta.glob('./**/*.{ts,vue}', { eager: true, import: 'default', query: '?raw' }) as Record<string, string>;
const axiosImportPatterns = [
    /^\s*import\s+(?:(?:type\s+)?[^;]*?\s+from\s+)?['"]axios['"]/m,
    /\bimport\s*\(\s*['"]axios['"]\s*\)/,
    /\brequire\s*\(\s*['"]axios['"]\s*\)/,
];

function importsAxios(source: string): boolean {
    return axiosImportPatterns.some((pattern) => pattern.test(source));
}

describe('frontend architecture', () => {
    it('uses Inertia HTTP instead of importing Axios directly', () => {
        const offenders = Object.entries(browserSources)
            .filter(([, source]) => importsAxios(source))
            .map(([path]) => path);

        expect(offenders).toEqual([]);
    });

    it('detects supported Axios imports without matching comments', () => {
        expect(importsAxios(['import axios ', "from 'axios'"].join(''))).toBe(true);
        expect(importsAxios(['import(', "'axios'", ')'].join(''))).toBe(true);
        expect(importsAxios(['require(', "'axios'", ')'].join(''))).toBe(true);
        expect(importsAxios(['// import axios ', "from 'axios'"].join(''))).toBe(false);
    });
});
