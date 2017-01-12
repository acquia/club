<?php

namespace Acquia\Club\Tests\Command\Fixtures;

use Acquia\Club\Command\CommandBase;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ExampleCommand extends CommandBase {

    /**
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     */
    public function setOutput(OutputInterface $output) {
        $this->output = $output;
    }

    /**
     * @param \Symfony\Component\Console\Input\InputInterface $input
     */
    public function setInput(InputInterface $input) {
        $this->input = $input;
    }

    /**
     * @param \Symfony\Component\Console\Helper\QuestionHelper $question_helper
     */
    public function setQuestionHelper(QuestionHelper $question_helper) {
        $this->questionHelper = $question_helper;
    }
}
