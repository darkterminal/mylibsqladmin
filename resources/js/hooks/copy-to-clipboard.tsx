import { useCallback, useState } from "react";

const useCopyToClipboard = () => {
    const [copiedText, setCopiedText] = useState<Record<string, boolean>>({});

    const copyToClipboard = useCallback(async (value: string, identifier: string, type: string) => {
        try {
            await navigator.clipboard.writeText(value);
            const key = `${identifier}-${type}`;
            setCopiedText(prev => ({ ...prev, [key]: true }));
            setTimeout(() => {
                setCopiedText(prev => ({ ...prev, [key]: false }));
            }, 2000);
        } catch (err) {
            console.error('Failed to copy text: ', err);
        }
    }, []);

    return { copiedText, copyToClipboard };
};

export default useCopyToClipboard;
