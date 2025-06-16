import { beforeEach, describe, expect, it, vi } from 'vitest';

// Create mock objects that will be available globally
const mockMediaQuery = {
    matches: false,
    media: '(prefers-color-scheme: dark)',
    onchange: null,
    addListener: vi.fn(),
    removeListener: vi.fn(),
    addEventListener: vi.fn(),
    removeEventListener: vi.fn(),
    dispatchEvent: vi.fn(),
};

const mockLocalStorage = {
    getItem: vi.fn(),
    setItem: vi.fn(),
    removeItem: vi.fn(),
    clear: vi.fn(),
};

const mockDocumentElement = {
    classList: {
        toggle: vi.fn(),
        add: vi.fn(),
        remove: vi.fn(),
        contains: vi.fn(),
    },
};

// Set up global mocks before any imports
Object.defineProperty(window, 'localStorage', {
    value: mockLocalStorage,
    writable: true,
});

Object.defineProperty(window, 'matchMedia', {
    writable: true,
    value: vi.fn().mockReturnValue(mockMediaQuery),
});

Object.defineProperty(document, 'documentElement', {
    value: mockDocumentElement,
    writable: true,
});

// Mock Vue's onMounted and ref to work properly in tests
vi.mock('vue', () => ({
    ref: vi.fn((value: any) => ({ value })),
    onMounted: vi.fn(),
    nextTick: vi.fn(() => Promise.resolve()),
}));

// Import after mocks are set up
import { onMounted } from 'vue';
import { handleSystemThemeChange, initializeTheme, updateTheme, useAppearance } from '../useAppearance';

const mockOnMounted = vi.mocked(onMounted);

describe('updateTheme', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('toggles dark class for dark/light/system themes', () => {
        updateTheme('dark');
        expect(mockDocumentElement.classList.toggle).toHaveBeenCalledWith('dark', true);
        updateTheme('light');
        expect(mockDocumentElement.classList.toggle).toHaveBeenCalledWith('dark', false);
        mockMediaQuery.matches = true;
        updateTheme('system');
        expect(mockDocumentElement.classList.toggle).toHaveBeenCalledWith('dark', true);
        mockMediaQuery.matches = false;
        updateTheme('system');
        expect(mockDocumentElement.classList.toggle).toHaveBeenCalledWith('dark', false);
    });
});

describe('initializeTheme', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        mockMediaQuery.matches = false;
    });

    it('uses saved appearance from localStorage or defaults to system', () => {
        mockLocalStorage.getItem.mockReturnValueOnce('dark');
        initializeTheme();
        expect(mockLocalStorage.getItem).toHaveBeenCalledWith('appearance');
        expect(mockDocumentElement.classList.toggle).toHaveBeenCalledWith('dark', true);

        mockLocalStorage.getItem.mockReturnValueOnce(null);
        initializeTheme();
        expect(mockDocumentElement.classList.toggle).toHaveBeenCalledWith('dark', false);
    });

    it('handles system theme changes and saved preferences', () => {
        mockLocalStorage.getItem.mockReturnValue('system');
        mockMediaQuery.matches = true;
        initializeTheme();
        expect(mockDocumentElement.classList.toggle).toHaveBeenCalledWith('dark', true);

        mockLocalStorage.getItem.mockReturnValue('dark');
        mockMediaQuery.matches = false;
        initializeTheme();
        expect(mockDocumentElement.classList.toggle).toHaveBeenCalledWith('dark', true);
    });
});

describe('handleSystemThemeChange', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        mockMediaQuery.matches = false;
    });

    it('updates theme based on system preference or saved appearance', () => {
        mockLocalStorage.getItem.mockReturnValue(null);
        mockMediaQuery.matches = true;
        handleSystemThemeChange();
        expect(mockDocumentElement.classList.toggle).toHaveBeenCalledWith('dark', true);

        mockLocalStorage.getItem.mockReturnValue('light');
        mockMediaQuery.matches = true;
        handleSystemThemeChange();
        expect(mockDocumentElement.classList.toggle).toHaveBeenCalledWith('dark', false);
    });
});

describe('useAppearance', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        mockMediaQuery.matches = false;
    });

    it('initializes with system or saved appearance', () => {
        mockLocalStorage.getItem.mockReturnValueOnce(null);
        expect(useAppearance().appearance.value).toBe('system');
        mockLocalStorage.getItem.mockReturnValueOnce('dark');
        let onMountedCallback;
        mockOnMounted.mockImplementation((cb) => {
            onMountedCallback = cb;
        });
        const { appearance } = useAppearance();
        if (onMountedCallback) onMountedCallback();
        expect(appearance.value).toBe('dark');
    });

    it('updates appearance and saves to localStorage', () => {
        mockLocalStorage.getItem.mockReturnValue(null);
        const { appearance, updateAppearance } = useAppearance();
        updateAppearance('dark');
        expect(appearance.value).toBe('dark');
        expect(mockLocalStorage.setItem).toHaveBeenCalledWith('appearance', 'dark');
        expect(mockDocumentElement.classList.toggle).toHaveBeenCalledWith('dark', true);
    });

    it('returns correct interface and calls initializeTheme on mount', () => {
        let onMountedCallback;
        mockOnMounted.mockImplementation((cb) => {
            onMountedCallback = cb;
        });
        const result = useAppearance();
        expect(result).toHaveProperty('appearance');
        expect(result).toHaveProperty('updateAppearance');
        expect(typeof result.updateAppearance).toBe('function');
        expect(result.appearance.value).toBe('system');
        if (onMountedCallback) onMountedCallback();
        expect(mockLocalStorage.getItem).toHaveBeenCalledWith('appearance');
    });
});
