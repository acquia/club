<?php

namespace Acquia\BltValet\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class GetSites extends CommandBase
{
  protected function configure()
  {
    $this
      ->setName('get-sites')
      ->setDescription('Displays a list of Acquia Cloud sites.')
      ->setHelp("This command only gets a list of Acquia Cloud Sites from your account.")
    ;
  }

  protected function execute(InputInterface $input, OutputInterface $output)
  {
    $cloudApiClient = $this->getCloudApiClient($input, $output);
    $sites = $cloudApiClient->sites();
    foreach ($sites as $site) {
      $output->writeln($site->name());
    }
  }
}
