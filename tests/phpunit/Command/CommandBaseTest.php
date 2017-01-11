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

    /**
     * @param bool $xdebug_enabled
     * @param \PHPUnit_Framework_MockObject_Matcher_InvokedCount $call_writeln
     *
     * @dataProvider providerTestCheckXdebugOutput
     */
    public function testCheckXdebugOutput($xdebug_enabled, $call_writeln)
    {
        // Turn xdebug on/off.
        $command = new ExampleCommand('example-command');
        $local_environment_facade = $this->createMock(LocalEnvironmentFacade::class);
        $local_environment_facade->method('isPhpExtensionLoaded')->willReturn($xdebug_enabled);
        $command->setLocalEnvironmentFacade($local_environment_facade);
        /** @var OutputInterface $output */
        $output = $this->createMock(OutputInterface::class);
        $output->expects($call_writeln)->method('writeln');
        $command->setOutput($output);
        /** @var InputInterface $input */
        $input = $this->createMock(InputInterface::class);
        $command->setInput($input);

        $question_helper = $this->createMock(QuestionHelper::class);
        $command->setQuestionHelper($question_helper);

        // Call method checkXdebug().
        $command->checkXdebug();

        // Assert.

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

}
