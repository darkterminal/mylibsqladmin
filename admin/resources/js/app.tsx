import '../css/app.css';

import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';
import { route as routeFn } from 'ziggy-js';
import { initializeTheme } from './hooks/use-appearance';
import { OpenModalStateChangeProps, UserDatabaseTokenProps, type AppearanceStateChangeProps, type DatabaseStatsChangeProps } from './types';

declare global {
    const route: typeof routeFn;

    interface Window {
        addEventListener(
            type: 'appearance-changed',
            listener: (event: CustomEvent<AppearanceStateChangeProps>) => void,
            options?: boolean | AddEventListenerOptions
        ): void;
        addEventListener(
            type: 'stats-changed',
            listener: (event: CustomEvent<DatabaseStatsChangeProps>) => void,
            options?: boolean | AddEventListenerOptions
        ): void;
        addEventListener(
            type: 'open-modal-changed',
            listener: (event: CustomEvent<OpenModalStateChangeProps>) => void,
            options?: boolean | AddEventListenerOptions
        ): void;
        addEventListener(
            type: 'database-group-is-deleted',
            listener: (event: CustomEvent<{ id: number }>) => void,
            options?: boolean | AddEventListenerOptions
        ): void;
        addEventListener(
            type: 'token-is-deleted',
            listener: (event: CustomEvent<{ id: number }>) => void,
            options?: boolean | AddEventListenerOptions
        ): void;
        addEventListener(
            type: 'token-is-created',
            listener: (event: CustomEvent<{ id: number, newToken: UserDatabaseTokenProps }>) => void,
            options?: boolean | AddEventListenerOptions
        ): void;
        addEventListener(
            type: 'group-token-is-created',
            listener: (event: CustomEvent<{ id: number }>) => void,
            options?: boolean | AddEventListenerOptions
        ): void;
        addEventListener(
            type: 'token-created-from-group',
            listener: (event: CustomEvent<{ groupId: number, databaseId: number, newToken: UserDatabaseTokenProps }>) => void,
            options?: boolean | AddEventListenerOptions
        ): void;
    }
}

const appName = import.meta.env.VITE_APP_NAME || 'MylibSQLAdmin';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) => resolvePageComponent(`./pages/${name}.tsx`, import.meta.glob('./pages/**/*.tsx')),
    setup({ el, App, props }) {

        const csrfMetaTag = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]');
        if (csrfMetaTag) {
            // @ts-ignore
            props.initialPage.props.auth.csrfToken = csrfMetaTag.content;
        }

        const root = createRoot(el);
        root.render(<App {...props} />);
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on load...
initializeTheme();
