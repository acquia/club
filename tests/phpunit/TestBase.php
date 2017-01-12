<?php

namespace Acquia\Club\Tests;

use Symfony\Component\Console\Application;
use Acquia\Club\Command\LocalEnvironmentFacade;

/**
 * Class BltTestBase.
 *
 * Base class for all tests that are executed for BLT itself.
 */
abstract class TestBase extends \PHPUnit_Framework_TestCase
{

    /** @var Application */
    protected $application;
    /** @var LocalEnvironmentFacade|\PHPUnit_Framework_MockObject_MockObject */
    protected $local_environment_facade;

    /**
     * {@inheritdoc}
     *
     * @see https://symfony.com/doc/current/console.html#testing-commands
     */
    public function setUp()
    {
        parent::setUp();

        $this->application = new Application();
        $this->local_environment_facade = $this->createMock(LocalEnvironmentFacade::class);
    }
}
