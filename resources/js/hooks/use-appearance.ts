import { useEffect, useState } from 'react';

type Appearance = 'light' | 'dark' | 'system';

const getSystemTheme = (): 'light' | 'dark' => {
    return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
};

const applyTheme = (theme: 'light' | 'dark') => {
    const root = document.documentElement;
    root.classList.remove('light', 'dark');
    root.classList.add(theme);
};

export function useAppearance() {
    const [appearance, setAppearance] = useState<Appearance>(
        () => (localStorage.getItem('appearance') as Appearance) || 'system'
    );

    useEffect(() => {
        const stored = localStorage.getItem('appearance') as Appearance | null;
        const current = stored || 'system';
        setAppearance(current);

        if (current === 'system') {
            applyTheme(getSystemTheme());
        } else {
            applyTheme(current);
        }
    }, []);

    const updateAppearance = (newAppearance: Appearance) => {
        localStorage.setItem('appearance', newAppearance);
        setAppearance(newAppearance);

        if (newAppearance === 'system') {
            applyTheme(getSystemTheme());
        } else {
            applyTheme(newAppearance);
        }
    };

    return { appearance, updateAppearance };
}

export function initializeTheme() {
    const stored = localStorage.getItem('appearance') as Appearance | null;
    const current = stored || 'system';

    if (current === 'system') {
        applyTheme(getSystemTheme());
    } else {
        applyTheme(current);
    }
}
