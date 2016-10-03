<?php

namespace Acquia\Club\Command;

use Acquia\Club\Loader\JsonFileLoader;
use Symfony\Component\Config\Loader\DelegatingLoader;
use Symfony\Component\Config\Loader\LoaderResolver;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Question\ConfirmationQuestion;
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
 * @package Acquia\Club\Command
 */
abstract class CommandBase extends Command
{
  /**
   * @var string
   */
  protected $drushAliasDir;
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

  /** @var QuestionHelper */
  protected $questionHelper;

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
    $this->questionHelper = $this->getHelper('question');
    $this->fs = new Filesystem();
    $this->cloudConfDir = $_SERVER['HOME'] . '/.acquia';
    $this->drushAliasDir = $_SERVER['HOME'] . '/.drush';
    $this->cloudConfFileName = 'cloudapi.conf';
    $this->cloudConfFilePath = $this->cloudConfDir . '/' . $this->cloudConfFileName;
    $this->cloudApiConfig = $this->loadCloudApiConfig();
  }

  /**
   * @return string
   */
  protected function xDebugPrompt() {
    if (extension_loaded('xdebug')) {
      $this->output->writeln("<comment>You have xDebug enabled. This will make everything very slow. You should really disable it.</comment>");
      $question = new ConfirmationQuestion('<comment>Do you want to continue?</comment> ', true);
      $continue = $this->questionHelper->ask($this->input, $this->output, $question);

      if (!$continue) {
        exit(1);
      }
    }
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

  protected function checkDestinationDir($dir_name) {
    $destination_dir = getcwd() . '/' . $dir_name;
    if ($this->fs->exists($destination_dir)) {
      $this->output->writeln("<comment>Uh oh. The destination directory already exists.</comment>");
      $question = new ConfirmationQuestion("<comment>Delete $destination_dir?</comment> ", false);
      $delete_dir = $this->questionHelper->ask($this->input, $this->output, $question);
      if ($delete_dir) {
        $this->fs->remove($destination_dir);
      }
      else {
        $this->output->writeln("<comment>Please choose a different machine name for your project, or change directories.</comment>");
        exit(1);
      }
    }
  }

  /**
   * @param \Acquia\Cloud\Api\CloudApiClient $cloud_api_client
   * @param $site_id
   *
   * @return \Acquia\Cloud\Api\Response\Site
   */
  protected function getSite(CloudApiClient $cloud_api_client, $site_id) {
    return $cloud_api_client->site($site_id);
  }

  /**
   * @param \Acquia\Cloud\Api\CloudApiClient $cloud_api_client
   *
   * @return array
   */
  protected function getSites(CloudApiClient $cloud_api_client) {
    $sites = $cloud_api_client->sites();
    $sites_filtered = [];

    foreach ($sites as $key => $site) {
      $label = $this->getSiteLabel($site);
      if ($label !== '*') {
        $sites_filtered[(string) $site] = $site;
      }
    }

    return $sites_filtered;

  }

  /**
   * @param $site
   *
   * @return mixed
   */
  protected function getSiteLabel($site) {
    $site_slug = (string) $site;
    $site_split = explode(':', $site_slug);

    return $site_split[1];
  }

  /**
   * @param \Acquia\Cloud\Api\CloudApiClient $cloud_api_client
   *
   * @return array
   */
  protected function getSitesList(CloudApiClient $cloud_api_client) {
    $site_list = [];
    $sites = $this->getSites($cloud_api_client);
    foreach ($sites as $site) {
      $site_list[] = $this->getSiteLabel($site);
    }
    sort($site_list, SORT_NATURAL | SORT_FLAG_CASE);

    return $site_list;
  }

  /**
   * @param \Acquia\Cloud\Api\CloudApiClient $cloud_api_client
   * @param $label
   *
   * @return \Acquia\Cloud\Api\Response\Site|null
   */
  protected function getSiteByLabel(CloudApiClient $cloud_api_client, $label) {
    $sites = $this->getSites($cloud_api_client);
    foreach ($sites as $site_id) {
      if ($this->getSiteLabel($site_id) == $label) {
        $site = $this->getSite($cloud_api_client, $site_id);
        return $site;
      }
    }

    return NULL;
  }

  /**
   * @param \Acquia\Cloud\Api\CloudApiClient $cloud_api_client
   * @param $site
   *
   * @return array
   */
  protected function getEnvironmentsList(CloudApiClient $cloud_api_client, $site) {
    $environments = $cloud_api_client->environments($site);
    $environments_list = [];
    foreach ($environments as $environment) {
      $environments_list[] = $environment->name();
    }

    return $environments_list;
  }

  /**
   * @param string $command
   *
   * @return bool
   */
  protected function executeCommand($command, $cwd = null, $display_output = true, $mustRun = true) {
    $timeout = 2000;
    $env = [
      'COMPOSER_PROCESS_TIMEOUT' => $timeout
    ] + $_ENV;
    $process = new Process($command, $cwd, $env, null, $timeout);
    //$process->setTty(true);
    $method = $mustRun ? 'mustRun' : 'run';
    $process->$method(function ($type, $buffer) use (&$display_output) {
      if ($display_output) {
        print $buffer;
      }
    });

    if ($display_output) {
      return $process->isSuccessful();
    }
    else {
      return $process->getOutput();
    }
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
