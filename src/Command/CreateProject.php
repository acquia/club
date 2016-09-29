<?php

namespace Acquia\BltValet\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CreateProject extends Command
{
  protected function configure()
  {
    $this
      ->setName('create-project')
      ->setDescription('Creates a new project.')
      ->setHelp("This command allows you to create projects...")
    ;
  }

  protected function execute(InputInterface $input, OutputInterface $output)
  {
    $output->writeln('Whoa there!');
  }
}
