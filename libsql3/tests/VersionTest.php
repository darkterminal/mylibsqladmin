<?php

declare(strict_types=1);

namespace Libsql3\Tests;

use Libsql3\App;
use Libsql3\Version;

final class VersionTest extends Libsql3TestCase
{
    public function test_app_uses_the_single_version_source(): void
    {
        $this->assertNotSame('1.0.0', Version::CURRENT);
        $this->assertSame(Version::CURRENT, (new App())->getVersion());
    }
}
