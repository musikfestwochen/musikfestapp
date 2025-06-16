import { describe, expect, it } from 'vitest';
import { getInitials, useInitials } from '../useInitials';

describe('getInitials', () => {
    it('returns empty string for undefined, empty, or whitespace input', () => {
        expect(getInitials(undefined)).toBe('');
        expect(getInitials('')).toBe('');
        expect(getInitials('   ')).toBe('');
        expect(getInitials('\t\n')).toBe('');
    });

    it('returns single initial for single name (with or without whitespace)', () => {
        expect(getInitials('John')).toBe('J');
        expect(getInitials('alice')).toBe('A');
        expect(getInitials('  John  ')).toBe('J');
        expect(getInitials('\tAlice\n')).toBe('A');
    });

    it('returns first and last initials for two or more names', () => {
        expect(getInitials('John Doe')).toBe('JD');
        expect(getInitials('Alice Smith')).toBe('AS');
        expect(getInitials('bob jones')).toBe('BJ');
        expect(getInitials('John Michael Doe')).toBe('JD');
        expect(getInitials('Mary Jane Watson Smith')).toBe('MS');
        expect(getInitials('Jean-Claude Van Damme')).toBe('JD');
    });

    it('handles multiple spaces and special characters', () => {
        expect(getInitials('John   Doe')).toBe('JD');
        expect(getInitials('Alice     Bob     Charlie')).toBe('AC');
        expect(getInitials("John O'Connor")).toBe('JO');
        expect(getInitials('Marie-Claire Dubois')).toBe('MD');
        expect(getInitials('José García')).toBe('JG');
    });

    it('converts initials to uppercase', () => {
        expect(getInitials('john doe')).toBe('JD');
        expect(getInitials('alice smith')).toBe('AS');
        expect(getInitials('mary jane watson')).toBe('MW');
    });

    it('handles single character names and names with numbers', () => {
        expect(getInitials('A')).toBe('A');
        expect(getInitials('A B')).toBe('AB');
        expect(getInitials('A B C')).toBe('AC');
        expect(getInitials('John2 Doe3')).toBe('JD');
        expect(getInitials('Alice123')).toBe('A');
    });
});

describe('useInitials', () => {
    it('should return an object with getInitials function', () => {
        const { getInitials: getInitialsFromComposable } = useInitials();
        expect(typeof getInitialsFromComposable).toBe('function');
    });

    it('should return the same getInitials function', () => {
        const { getInitials: getInitialsFromComposable } = useInitials();
        expect(getInitialsFromComposable).toBe(getInitials);
    });

    it('should work the same as direct function call', () => {
        const { getInitials: getInitialsFromComposable } = useInitials();

        const testCases = ['John Doe', 'Alice Smith', 'Mary Jane Watson', 'Bob', '', undefined];

        testCases.forEach((testCase) => {
            expect(getInitialsFromComposable(testCase)).toBe(getInitials(testCase));
        });
    });
});
