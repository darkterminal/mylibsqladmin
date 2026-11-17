import { describe, expect, it } from 'vitest';
import { sdkOptions } from '@/lib/sdks';

describe('sdkOptions', () => {
    it('covers the expected SDKs and both tabs', () => {
        const official = sdkOptions.filter((s) => s.official).map((s) => s.id);
        const community = sdkOptions.filter((s) => !s.official).map((s) => s.id);

        expect(official).toContain('python');
        expect(official).toContain('go');
        expect(official).toContain('rust');
        expect(official).toContain('dart');
        expect(official).toContain('java');

        expect(community).toContain('php-extension');
        expect(community).toContain('laravel');
    });

    it('every SDK has a non-empty install command, snippet and docs link', () => {
        for (const sdk of sdkOptions) {
            expect(sdk.install.trim().length, sdk.id).toBeGreaterThan(0);
            expect(sdk.snippet.trim().length, sdk.id).toBeGreaterThan(0);
            expect(sdk.docs.startsWith('https://'), sdk.id).toBe(true);
            // docs must be absolute https URLs (original docs, not a shortcut)
            expect(sdk.documentation, sdk.id).toBe(sdk.docs);
        }
    });

    it('no snippet embeds a real token (placeholders only)', () => {
        for (const sdk of sdkOptions) {
            expect(sdk.snippet, sdk.id).not.toMatch(/token\s*=\s*["'][A-Za-z0-9_-]{16,}/);
            expect(sdk.snippet, sdk.id).not.toMatch(/[A-Za-z0-9_-]{40,}/);
        }
    });

    it('snippets never hard-code a real database host', () => {
        for (const sdk of sdkOptions) {
            // Turso/libSQL-style database URLs are not allowed —
            // placeholders and env vars only (docs hosts like
            // docs.turso.tech are fine)
            expect(sdk.snippet, sdk.id).not.toMatch(/libsql:\/\/[a-z0-9-]+/i);
            expect(sdk.snippet, sdk.id).not.toMatch(/https?:\/\/[a-z0-9-]+\.(turso\.io|libsql\.net)/i);
        }
    });
});
