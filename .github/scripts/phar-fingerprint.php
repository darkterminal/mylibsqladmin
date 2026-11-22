<?php

/**
 * Order-independent content fingerprint for a PHAR compiled by Box.
 *
 * Box 3.8.4 compiles by dumping buffered files into a fresh temp directory
 * and then calling Phar::buildFromDirectory(). The manifest entry order
 * follows the host filesystem's readdir() order, so byte-identical PHARs
 * built on different filesystems (local ext4 vs GitHub Actions overlayfs)
 * have different raw SHA-256 hashes even though every file matches.
 *
 * Sorting entries by relative pathname before hashing makes this digest
 * stable across build hosts while still covering every file's content and
 * the generated stub. It is deliberately kept outside libsql3/ so Box does
 * not bundle it into the PHAR it is verifying.
 */

$path = $argv[1] ?? null;

if ($path === null || !is_file($path)) {
    fwrite(STDERR, "usage: php phar-fingerprint.php <path-to-phar>\n");
    exit(1);
}

Phar::loadPhar($path, 'phar-fingerprint.phar');
$phar = new Phar('phar://phar-fingerprint.phar');

$entries = [];
foreach (new RecursiveIteratorIterator($phar) as $file) {
    $relative = substr($file->getPathname(), strlen($file->getPath()) + 1);
    $entries[$relative] = hash('sha256', file_get_contents($file->getPathname()));
}
ksort($entries, SORT_STRING);

$context = hash_init('sha256');
hash_update($context, hash('sha256', $phar->getStub()));
foreach ($entries as $relative => $hash) {
    hash_update($context, $relative . "\0" . $hash . "\n");
}

echo hash_final($context), "\n";
