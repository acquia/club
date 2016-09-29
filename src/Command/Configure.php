<?php

namespace Acquia\BltValet\Command;

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

class Configure extends CommandBase
{
  protected function configure()
  {
    $this
      ->setName('configure')
      ->setDescription('Configures BltValet for Acquia cloud.')
      ->setHelp("This command will BltValet for Acquia cloud.")
    ;
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    // Attempt to load our configuration properties, or simply ask for them
    // True means skip the error message saying there is no configuration
    $this->loadConfigureProperties($input, $output, true);
  }
}
