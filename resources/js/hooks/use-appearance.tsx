/**
 * AvanaHR is light-only — dark mode has been removed. These exports are kept as
 * light-fixed stubs so consumers (e.g. the Sonner toaster) keep a stable API.
 */
export type ResolvedAppearance = 'light';
export type Appearance = 'light';

export type UseAppearanceReturn = {
    readonly appearance: Appearance;
    readonly resolvedAppearance: ResolvedAppearance;
    readonly updateAppearance: (mode: Appearance) => void;
};

export function initializeTheme(): void {
    if (typeof document === 'undefined') {
        return;
    }

    document.documentElement.classList.remove('dark');
    document.documentElement.style.colorScheme = 'light';
}

export function useAppearance(): UseAppearanceReturn {
    return {
        appearance: 'light',
        resolvedAppearance: 'light',
        updateAppearance: () => {},
    } as const;
}
