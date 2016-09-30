<?php

namespace Acquia\BltValet\Command;

use Acquia\BltValet\Loader\JsonFileLoader;
use Symfony\Component\Config\Loader\DelegatingLoader;
use Symfony\Component\Config\Loader\LoaderResolver;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;
use Acquia\Cloud\Api\CloudApiClient;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

/**
 * Class CommandBase
 *
 * @package Acquia\BltValet\Command
 */
abstract class CommandBase extends Command
{
  /**
   * @var string
   */
  protected $cloudConfDir;
  /**
   * @var string
   */
  protected $cloudConfFileName;
  /**
   * @var string
   */
  protected $cloudConfFilePath;
  /**
   * @var array
   */
  private $cloudApiConfig;

  /** @var Filesystem */
  protected $fs;

  /**
   * @var InputInterface
   */
  protected $input;
  /**
   * @var OutputInterface
   */
  protected $output;

  /**
   * Initializes the command just after the input has been validated.
   *
   * This is mainly useful when a lot of commands extends one main command
   * where some things need to be initialized based on the input arguments and options.
   *
   * @param InputInterface  $input  An InputInterface instance
   * @param OutputInterface $output An OutputInterface instance
   */
  protected function initialize(InputInterface $input, OutputInterface $output) {
    $this->input = $input;
    $this->output = $output;
    $this->fs = new Filesystem();
    $this->cloudConfDir = $_SERVER['HOME'] . '/.acquia';
    $this->cloudConfFileName = 'cloudapi.conf';
    $this->cloudConfFilePath = $this->cloudConfDir . '/' . $this->cloudConfFileName;
    $this->cloudApiConfig = $this->loadCloudApiConfig();
  }

  /**
   * @return array
   */
  protected function loadCloudApiConfig() {
    if (!$config = $this->loadCloudApiConfigFile()) {
      $config = $this->askForCloudApiCredentials();
    }

    return $config;
  }

  /**
   * @return array
   */
  protected function loadCloudApiConfigFile() {
    $config_dirs = [
      $_SERVER['HOME'] . '/.acquia',
    ];
    $locator = new FileLocator($config_dirs);

    try {
      $file = $locator->locate('cloudapi.conf', null, true);
      $loaderResolver = new LoaderResolver(array(new JsonFileLoader($locator)));
      $delegatingLoader = new DelegatingLoader($loaderResolver);
      $config = $delegatingLoader->load($file);

      return $config;
    }
    catch (\Exception $e) {
      return [];
    }

  }

  /**
   *
   */
  protected function askForCloudApiCredentials() {
    $helper = $this->getHelper('question');
    $usernameQuestion = new Question('<question>Please enter your Acquia cloud email address:</question> ', '');
    $privateKeyQuestion = new Question('<question>Please enter your Acquia cloud private key:</question> ', '');
    $privateKeyQuestion->setHidden(true);

    do {
      $email = $helper->ask($this->input, $this->output, $usernameQuestion);
      $key = $helper->ask($this->input, $this->output, $privateKeyQuestion);
      $cloud_api_client = $this->getCloudApiClient($email, $key);
    }
    while (!$cloud_api_client);

    $config = array(
      'email' => $email,
      'key' => $key,
    );

    $this->writeCloudApiConfig($config);
  }

  /**
   * @param $config
   */
  protected function writeCloudApiConfig($config) {
    file_put_contents($this->cloudConfFilePath, json_encode($config));
    $this->output->writeln("<info>Credentials were written to {$this->cloudConfFilePath}.</info>");
  }

  /**
   * @return mixed
   */
  protected function getCloudApiConfig() {
    return $this->cloudApiConfig;
  }

  /**
   * @param $username
   * @param $password
   *
   * @return \Acquia\Cloud\Api\CloudApiClient|null
   */
  protected function getCloudApiClient($username, $password) {
    try {
      $cloudapi = CloudApiClient::factory(array(
        'username' => $username,
        'password' => $password,
      ));

      // We must call some method on the client to test authentication.
      $cloudapi->sites();

      return $cloudapi;
    }
    catch (\Exception $e) {
      $this->output->writeln("<error>Failed to authenticate with Acquia Cloud API.</error>");
      return NULL;
    }
  }

  /**
   * @param $cloudApiClient
   */
  protected function listSites($cloudApiClient) {
    $sites = $cloudApiClient->sites();
    foreach ($sites as $site) {
      $this->output->writeln($site->name());
    }
  }

  /**
   * @param string $command
   *
   * @return bool
   */
  protected function executeCommand($command, $cwd = null) {
    $timeout = 2000;
    $env = [
      'COMPOSER_PROCESS_TIMEOUT' => $timeout
    ] + $_ENV;
    $process = new Process($command, $cwd, $env, null, $timeout);
    $process->setTty(true);
    $process->mustRun(function ($type, $buffer) {
      print $buffer;
    });

    return $process->isSuccessful();
  }
  /**
   * @param $command
   *
   * @return bool
   */
  protected function executeCommands($commands = [], $cwd = null) {
    foreach ($commands as $command) {
      $this->executeCommand($command, $cwd);
    }
  }
}
