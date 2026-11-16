<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecureHeaders
{
    /**
     * Content Security Policy. Production gets a strict, same-origin policy.
     * Non-production environments also allow the Vite dev server (read from
     * the public/hot marker) so assets and the HMR websocket work — only
     * for local development.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('Content-Security-Policy', $this->csp());
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');

        return $response;
    }

    /**
     * @return list<string>
     */
    protected function devServerOrigins(): array
    {
        $hot = public_path('hot');

        if (!file_exists($hot)) {
            return [];
        }

        $contents = trim((string) file_get_contents($hot));

        if ($contents === '') {
            return [];
        }

        // public/hot holds e.g. "http://127.0.0.1:5175" — one origin per line.
        $origins = [];

        foreach (preg_split('/\s+/', $contents) as $line) {
            $parts = parse_url(trim($line));

            if (!is_array($parts) || empty($parts['host']) || empty($parts['scheme'])) {
                continue;
            }

            $origin = "{$parts['scheme']}://{$parts['host']}";

            if (!empty($parts['port'])) {
                $origin .= ":{$parts['port']}";
            }

            $origins[] = $origin;
        }

        return array_values(array_unique($origins));
    }

    protected function csp(): string
    {
        $scriptSrc = "'self' 'unsafe-inline'";
        $styleSrc = "'self' 'unsafe-inline' https://fonts.bunny.net";
        $fontSrc = "'self' https://fonts.bunny.net";
        $connectSrc = "'self'";

        if (!app()->environment('production')) {
            foreach ($this->devServerOrigins() as $origin) {
                $scriptSrc .= " {$origin}";
                $styleSrc .= " {$origin}";
                $fontSrc .= " {$origin}";
                $connectSrc .= " {$origin} ws://{$this->hostPort($origin)}";
            }
        }

        return "default-src 'self'; script-src {$scriptSrc}; "
            . "style-src {$styleSrc}; "
            . "font-src {$fontSrc}; "
            . "img-src 'self' data:; connect-src {$connectSrc}; "
            . "frame-src 'self' https://libsqlstudio.com; "
            . "form-action 'self'; frame-ancestors 'self'; base-uri 'self'";
    }

    protected function hostPort(string $origin): string
    {
        $parts = parse_url($origin);

        return ($parts['host'] ?? 'localhost') . (isset($parts['port']) ? ":{$parts['port']}" : '');
    }
}
