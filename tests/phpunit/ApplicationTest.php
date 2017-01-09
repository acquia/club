<?php

namespace Acquia\Club\Tests\Command;

use Acquia\Club\Tests\TestBase;

class ApplicationTest extends TestBase
{

    /**
     * Tests that all expected commands are available in the application.
     *
     * @dataProvider getValueProvider
     */
    public function testApplication($expected)
    {
        $bin = realpath(__DIR__ . '/../../bin/club');
        $output = shell_exec("$bin list");
        $this->assertContains($expected, $output);
    }

    /**
     * Provides values to testApplication().
     *
     * @return array
     *   An array of values to test.
     */
    public function getValueProvider()
    {
        return [
            ['create-project'],
            ['pull-project'],
            ['ac-aliases'],
        ];
    }
}
