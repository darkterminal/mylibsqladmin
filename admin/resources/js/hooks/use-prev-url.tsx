import { useEffect, useState } from 'react';

export default function usePrevUrl(defaultPath = '/') {
    const [prevUrl, setPrevUrl] = useState(() => {
        if (typeof window !== 'undefined') {
            return localStorage.getItem('prevUrl') || defaultPath;
        }
        return defaultPath;
    });

    useEffect(() => {
        const handleStorageChange = (event: StorageEvent) => {
            if (event.key === 'prevUrl') {
                setPrevUrl(event.newValue || defaultPath);
            }
        };

        window.addEventListener('storage', handleStorageChange);
        return () => window.removeEventListener('storage', handleStorageChange);
    }, [defaultPath]);

    return prevUrl;
}
