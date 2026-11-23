<?php

declare(strict_types=1);

namespace Libsql3;

/**
 * Single source of truth for the CLI version.
 *
 * D-14: aligned with the platform release tag. This file is bundled in the
 * PHAR, so the version must not be read from composer.json at runtime.
 */
final class Version
{
    public const CURRENT = '1.6.3';
}
