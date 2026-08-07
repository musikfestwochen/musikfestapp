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

const directDateTimeFormattingPatterns = [
    /\.toLocale(?:String|DateString|TimeString)\s*\(/,
    /new\s+Intl\.(?:DateTimeFormat|RelativeTimeFormat)\s*\(/,
];

function usesDirectDateTimeFormatting(source: string): boolean {
    return directDateTimeFormattingPatterns.some((pattern) => pattern.test(source));
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

    it('uses Musikfestapp as the page title suffix without duplicate page titles', () => {
        expect(browserSources['./app.ts']).toContain('`${title} - ${page.props.name}`');

        const duplicateTitles = Object.entries(browserSources)
            .filter(([, source]) => /<Head\b[^>]*\btitle=["']Musikfestapp["']/.test(source))
            .map(([path]) => path);

        expect(duplicateTitles).toEqual([]);
    });

    it('centralizes user-visible date and time formatting', () => {
        const offenders = Object.entries(browserSources)
            .filter(
                ([path, source]) =>
                    path !== './architecture.test.ts' && path !== './utils/dateTimeHelpers.ts' && usesDirectDateTimeFormatting(source),
            )
            .map(([path]) => path);

        expect(offenders).toEqual([]);
    });

    it('detects direct date and time formatting', () => {
        expect(usesDirectDateTimeFormatting('date.toLocaleString()')).toBe(true);
        expect(usesDirectDateTimeFormatting("new Intl.DateTimeFormat('en-US')")).toBe(true);
        expect(usesDirectDateTimeFormatting("new Intl.RelativeTimeFormat('de')")).toBe(true);
    });
});
