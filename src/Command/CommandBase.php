<?php

namespace Acquia\BltValet\Command;

use Symfony\Component\Yaml\Yaml;
use Acquia\Cloud\Api\CloudApiClient;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

abstract class CommandBase extends Command
{
  protected $username;
  protected $private_key;
  protected $configDirectory = 'config';
  protected $configFileName = 'bltvaletconfig.yml';

  protected function askForCredentials(InputInterface $input, OutputInterface $output, $configFile) {
    $helper = $this->getHelper('question');
    $usernameQuestion = new Question('Please enter your Acquia cloud username: ', '');
    $this->username = $helper->ask($input, $output, $usernameQuestion);
    $privateKeyQuestion = new Question('Please enter your Acquia cloud private key: ', '');
    $this->private_key = $helper->ask($input, $output, $privateKeyQuestion);

    $configProperties = array(
      'username' => $this->username,
      'private_key' => $this->private_key,
    );
    file_put_contents($configFile, Yaml::dump($configProperties));
    $output->writeln("<info>blt-valet is configured.</info>");
  }


  protected function loadConfigureProperties(InputInterface $input, OutputInterface $output, $skipcheck = false) {
    $configFile = getcwd() . '/' . $this->configDirectory . '/' . $this->configFileName;
    // Check if the file exists
    if (file_exists($configFile)) {
      // Attempt to parse the properties
      try {
        $configProperties = Yaml::parse(file_get_contents($configFile));
        // If we actually have properties we can use them
        if ($configProperties['username'] && $configProperties['private_key']) {
          $this->username = $configProperties['username'];
          $this->private_key = $configProperties['private_key'];
          $output->writeln("<info>blt-valet is configured now.</info>");
        }
      } catch (ParseException $e) {
        printf("Unable to parse the YAML string: %s", $e->getMessage());
      }
    } else {
      if (!$skipcheck) {
        $output->writeln('<error>It appears no configuration is setup.</error>');
      }
      $output->writeln('<info>Running setup...</info>');
      $this->askForCredentials($input, $output, $configFile);
      // Lets re-run our command.
      $output->writeln("<info>Re-running command.</info>");
      $this->getCloudApiClient($input, $output);
    }
  }

  protected function getCloudApiClient(InputInterface $input, OutputInterface $output) {
    $this->loadConfigureProperties($input, $output);
    $cloudapi = CloudApiClient::factory(array(
      'username' => $this->username,
      'password' => $this->private_key,
    ));
    return $cloudapi;
  }
}
