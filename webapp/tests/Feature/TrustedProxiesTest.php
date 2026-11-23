<?php

use App\Support\TrustedProxies;

it('always trusts the Cloudflare edge ranges', function () {
    $proxies = TrustedProxies::resolve();

    expect($proxies)
        ->toContain('173.245.48.0/20')
        ->toContain('2c0f:f248::/32')
        ->not->toContain('10.0.0.0/8');
});

it('trusts private ranges only when opted in', function () {
    $proxies = TrustedProxies::resolve(trustPrivate: true);

    expect($proxies)
        ->toContain('127.0.0.1/32')
        ->toContain('10.0.0.0/8')
        ->toContain('172.16.0.0/12')
        ->toContain('192.168.0.0/16');
});

it('appends extra proxies and drops duplicates', function () {
    $proxies = TrustedProxies::resolve(
        trustPrivate: true,
        extra: ' 10.0.0.0/8, 203.0.113.7 '
    );

    expect($proxies)
        ->toContain('203.0.113.7')
        ->and(count(array_keys($proxies, '10.0.0.0/8', true)))->toBe(1);
});

it('ignores blank extra proxies', function () {
    $proxies = TrustedProxies::resolve(extra: ' , , ');

    expect($proxies)->toHaveCount(count(TrustedProxies::resolve()));
});
