<?php

namespace App\Support;

/**
 * Resolves the reverse proxies whose X-Forwarded-* headers Laravel trusts.
 *
 * Cloudflare edge ranges are always included. Private ranges are opt-in for
 * deployments behind an internal reverse proxy (Coolify Traefik, nginx, or a
 * local Docker bridge). TRUSTED_PROXIES adds arbitrary extra IPs or CIDRs.
 */
final class TrustedProxies
{
    /** @var list<string> Cloudflare IPv4 and IPv6 edge ranges. */
    private const CLOUDFLARE = [
        '173.245.48.0/20',
        '103.21.244.0/22',
        '103.22.200.0/22',
        '103.31.4.0/22',
        '141.101.64.0/18',
        '108.162.192.0/18',
        '190.93.240.0/20',
        '188.114.96.0/20',
        '197.234.240.0/22',
        '198.41.128.0/17',
        '162.158.0.0/15',
        '104.16.0.0/13',
        '104.24.0.0/14',
        '172.64.0.0/13',
        '131.0.72.0/22',
        '2400:cb00::/32',
        '2606:4700::/32',
        '2803:f800::/32',
        '2405:b500::/32',
        '2405:8100::/32',
        '2a06:98c0::/29',
        '2c0f:f248::/32',
    ];

    /** @var list<string> RFC1918 plus loopback, used by internal proxies. */
    private const PRIVATE = [
        '127.0.0.1/32',
        '10.0.0.0/8',
        '172.16.0.0/12',
        '192.168.0.0/16',
    ];

    /**
     * @return list<string>
     */
    public static function resolve(bool $trustPrivate = false, ?string $extra = null): array
    {
        $proxies = self::CLOUDFLARE;

        if ($trustPrivate) {
            $proxies = array_merge($proxies, self::PRIVATE);
        }

        if ($extra !== null && $extra !== '') {
            $proxies = array_merge(
                $proxies,
                array_filter(array_map('trim', explode(',', $extra)))
            );
        }

        return array_values(array_unique($proxies));
    }
}
