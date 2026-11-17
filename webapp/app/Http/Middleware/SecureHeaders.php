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

        if (!app()->environment('production')) {
            foreach ($this->devServerOrigins() as $origin) {
                $scriptSrc .= " {$origin}";
                $styleSrc .= " {$origin}";
                $fontSrc .= " {$origin}";
            }
        }

        $connectSrc = $this->connectSrc();

        return "default-src 'self'; script-src {$scriptSrc}; "
            . "style-src {$styleSrc}; "
            . "font-src {$fontSrc}; "
            . "img-src 'self' data:; connect-src {$connectSrc}; "
            . "frame-src 'self' https://libsqlstudio.com; "
            . "form-action 'self'; frame-ancestors 'self'; base-uri 'self'";
    }

    /**
     * Non-production: 'self' plus the Vite dev server, and the local sqld
     * proxy used by Database Studio's parent page. Production: 'self' plus
     * a single-level wildcard for the app's parent domain, so subdomain
     * sqld endpoints (https://<db>.<domain>) stay reachable — restricted to
     * simple TLDs on purpose (multi-part suffixes like .co.uk get nothing).
     */
    protected function connectSrc(): string
    {
        if (!app()->environment('production')) {
            $connectSrc = "'self'";

            foreach ($this->devServerOrigins() as $origin) {
                $connectSrc .= " {$origin} ws://{$this->hostPort($origin)}";
            }

            $connectSrc .= " http://*.localhost:8080";

            return $connectSrc;
        }

        $connectSrc = "'self'";

        $host = parse_url(config('app.url'), PHP_URL_HOST);
        if (!is_string($host)) {
            return $connectSrc;
        }

        $host = strtolower(trim($host));
        $parts = array_values(array_filter(explode('.', $host), fn ($part) => $part !== ''));
        $tld = end($parts);

        if (!is_string($tld) || strlen($tld) < 2 || in_array($tld, ['uk', 'au', 'nz', 'za', 'ke', 'in', 'my', 'sg', 'ph', 'id', 'jp', 'br', 'mx'], true)) {
            return $connectSrc;
        }

        if (filter_var($host, FILTER_VALIDATE_IP) !== false || $host === 'localhost') {
            return $connectSrc;
        }

        $parent = count($parts) >= 3 ? implode('.', array_slice($parts, 1)) : $host;
        if (substr_count($parent, '.') < 1) {
            return $connectSrc;
        }

        return $connectSrc . " https://*.{$parent}";
    }

    protected function hostPort(string $origin): string
    {
        $parts = parse_url($origin);

        return ($parts['host'] ?? 'localhost') . (isset($parts['port']) ? ":{$parts['port']}" : '');
    }
}
