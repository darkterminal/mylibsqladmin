import { router, usePage } from '@inertiajs/react';
import { useEffect } from 'react';

type NavigationTrackerProps = Record<string, unknown>;

export default function NavigationTracker(_: NavigationTrackerProps) {
    const { url: currentUrl } = usePage();
    const lastUrl = typeof window !== 'undefined'
        ? localStorage.getItem('prevUrl')
        : null;

    useEffect(() => {
        // Store initial navigation
        if (!lastUrl && currentUrl !== window.location.pathname) {
            localStorage.setItem('prevUrl', window.location.pathname);
        }
    }, []);

    useEffect(() => {
        const handleBeforeNavigate = (event: CustomEvent) => {
            // Store current URL as last navigation before new page loads
            localStorage.setItem('prevUrl', currentUrl);
        };

        // Listen to beforeNavigate event
        router.on('before', handleBeforeNavigate);

        return () => {
            router.on('navigate', handleBeforeNavigate);
        };
    }, [currentUrl]); // Dependency on current URL

    return null;
}
