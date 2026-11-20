<?php

declare(strict_types=1);

namespace Libsql3\Tests;

use Libsql3\App;
use Psy\Command\Command;

final class CommandSmokeTest extends Libsql3TestCase
{
    public function test_every_command_file_is_registered_and_named_consistently(): void
    {
        $files = glob(__DIR__ . '/../src/Cmd/*.php');
        $this->assertNotEmpty($files);

        $fromFiles = [];
        foreach ($files as $file) {
            $class = 'Libsql3\\Cmd\\' . basename($file, '.php');
            $this->assertTrue(class_exists($class), "$class has no matching class");

            $command = new $class();
            $this->assertInstanceOf(Command::class, $command);
            // Symfony 7.3 deprecated getDefaultName() and returns null for the
            // legacy $defaultName property, so read the property directly.
            $nameProperty = new \ReflectionProperty($class, 'defaultName');
            $this->assertSame(
                $nameProperty->getValue(),
                $command->getName(),
                "$class declares a name different from its static default"
            );

            $fromFiles[] = $class;
        }

        $app = new App();
        $fromRegistry = array_map('get_class', $app->getLibsqlCommands());

        // No hardcoded count: Plan 005/D-07 adds two commands and this must
        // still pass. Integrity is set equality, not a magic number.
        $this->assertEqualsCanonicalizing($fromFiles, $fromRegistry);
        $this->assertCount(count($fromRegistry), array_unique($fromRegistry));
    }
}
