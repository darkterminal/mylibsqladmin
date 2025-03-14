import '../css/app.css';

import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';
import { route as routeFn } from 'ziggy-js';
import { initializeTheme } from './hooks/use-appearance';

declare global {
    const route: typeof routeFn;

    interface Window {
        addEventListener(
            type: 'appearance-changed',
            listener: (event: CustomEvent<{ appearance: 'light' | 'dark' | 'system' }>) => void,
            options?: boolean | AddEventListenerOptions
        ): void;
        addEventListener(
            type: 'stats-changed',
            listener: (event: CustomEvent<{ type: 'query' | 'transaction', statement: string, databaseName: string }>) => void,
            options?: boolean | AddEventListenerOptions
        ): void;
        addEventListener(
            type: 'open-modal-changed',
            listener: (event: CustomEvent<{ isModalOpen: boolean, parentDatabase: string }>) => void,
            options?: boolean | AddEventListenerOptions
        ): void;
    }
}

const appName = import.meta.env.VITE_APP_NAME || 'MylibSQLAdmin';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) => resolvePageComponent(`./pages/${name}.tsx`, import.meta.glob('./pages/**/*.tsx')),
    setup({ el, App, props }) {
        const root = createRoot(el);

        root.render(<App {...props} />);
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on load...
initializeTheme();
