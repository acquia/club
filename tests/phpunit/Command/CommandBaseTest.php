<?php

namespace Acquia\Club\Tests\Command;

use Acquia\Club\Command\LocalEnvironmentFacade;
use Acquia\Club\Tests\Command\Fixtures\ExampleCommand;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class CommandBaseTest
 * @package Acquia\Club\Tests\Command
 */
class CommandBaseTest extends \PHPUnit_Framework_TestCase
{

    /** @var InputInterface|\PHPUnit_Framework_MockObject_MockObject $input */
    protected $input;

    /** @var OutputInterface|\PHPUnit_Framework_MockObject_MockObject $output */
    protected $output;

    /** @var LocalEnvironmentFacade|\PHPUnit_Framework_MockObject_MockObject */
    protected $local_environment_facade;

    /** @var QuestionHelper|\PHPUnit_Framework_MockObject_MockObject */
    protected $question_helper;

    public function setUp()
    {
        $this->local_environment_facade = $this->createMock(LocalEnvironmentFacade::class);
        $this->input = $this->createMock(InputInterface::class);
        $this->output = $this->createMock(OutputInterface::class);
        $this->question_helper = $this->createMock(QuestionHelper::class);
    }

    /**
     * @param bool $xdebug_enabled
     * @param \PHPUnit_Framework_MockObject_Matcher_InvokedCount $call_writeln
     *
     * @dataProvider providerTestCheckXdebugOutput
     */
    public function testCheckXdebugOutput($xdebug_enabled, $call_writeln)
    {
        // Set expectations.
        $this->local_environment_facade
            ->method('isPhpExtensionLoaded')
            ->willReturn($xdebug_enabled);
        $this->output
            ->expects($call_writeln)
            ->method('writeln');

        // Create command.
        $command = $this->createExampleCommand();

        // Call method checkXdebug().
        $command->checkXdebug();

        // @todo Verify that askContinue() was/was not called.
    }

    /**
     * @return array
     */
    public function providerTestCheckXdebugOutput() {
        return [
          [TRUE, $this->once()],
          [FALSE, $this->never()]
        ];
    }

    /**
     * @return \Acquia\Club\Tests\Command\Fixtures\ExampleCommand
     */
    public function createExampleCommand() {
        $command = new ExampleCommand($this->local_environment_facade);
        $command->setInput($this->input);
        $command->setOutput($this->output);
        $command->setQuestionHelper($this->question_helper);

        return $command;
    }

}
