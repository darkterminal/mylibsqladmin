<?php

/**
 * The default local sqlite/libsql connection must be primed with a busy
 * timeout on its first query — web requests and the queue worker share the
 * file database, and without it any concurrent write surfaces as
 * `database is locked` instead of a short wait.
 */

use App\Models\User;

test('the default connection gets a busy timeout on first query', function () {
    // Any query triggers the lazy priming hook registered at boot.
    User::first();

    $timeout = DB::select('PRAGMA busy_timeout');

    expect($timeout[0]->timeout)->toBe(5000);
});
