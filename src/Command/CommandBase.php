<?php

namespace Acquia\BltValet\Command;

use Acquia\Cloud\Api\CloudApiClient;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

abstract class CommandBase extends Command
{

  protected function getCloudApiClient($username, $password) {
    $cloudapi = CloudApiClient::factory(array(
      'username' => $username,
      'password' => $password,
    ));

    return $cloudapi;
  }
}
