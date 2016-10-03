<?php

namespace Acquia\Club\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use vierbergenlars\SemVer\expression;
use vierbergenlars\SemVer\version;

class PullProjectCommand extends CommandBase
{

  const BLT_VERSION_CONSTRAINT = '^8.4.6';

  protected function configure()
  {
    $this
      ->setName('pull-project')
      ->setDescription('Pulls an existing project from Acquia Cloud.')
      ->setHelp("This command allows you to pull projects...")
    ;
  }

  protected function execute(InputInterface $input, OutputInterface $output)
  {

    $this->xDebugPrompt();
    $config = $this->getCloudApiConfig();
    $cloud_api_client = $this->getCloudApiClient($config['email'], $config['key']);

    $helper = $this->getHelper('question');
    $question = new ChoiceQuestion('<question>Which site would you like to pull?</question>', $this->getSitesList($cloud_api_client));
    $answers['site'] = $helper->ask($input, $output, $question);
    $site = $this->getSiteByLabel($cloud_api_client, $answers['site']);

    $this->checkDestinationDir($answers['site']);

    $environments = $this->getEnvironmentsList($cloud_api_client, $site);
    $question = new ChoiceQuestion('<question>Which environment would you like to pull from (if applicable)?</question>', (array) $environments);
    $answers['env'] = $helper->ask($input, $output, $question);

    // @todo Determine which branch is on the env.
    // @todo Determine if branch is using BLT.

    $dir_name = $answers['site'];
    $this->executeCommands([
      "git clone {$site->vcsUrl()} $dir_name",
    ]);

    $composer_lock = json_decode(file_get_contents($dir_name . '/composer.lock'), TRUE);
    $this->verifyBltVersion($composer_lock);

    $this->output->writeln("<info>Great. Now let's make some choices about how your project will be set up locally.");
    $question = new ConfirmationQuestion('<question>Do you want to create a VM?</question> ', true);
    $answers['vm'] = $helper->ask($input, $output, $question);

    if ($answers['vm']) {
      $question = new ConfirmationQuestion('<question>Do you want to download a database from Acquia Cloud?</question> ', true);
      $answers['download_db'] = $helper->ask($input, $output, $question);

      // @todo Change to a choice btw download and stage file proxy.
      $question = new ConfirmationQuestion('<question>Do you want to download the public and private file directories from Acquia Cloud?</question> ', true);
      $answers['download_files'] = $helper->ask($input, $output, $question);
    }

    $this->output->writeln("<info>Awesome. Let's pull down your project. This could take a while...");

    $this->executeCommands([
      'composer install',
      'composer blt-alias',
    ], $dir_name);

    if ($answers['vm']) {
      $remote_alias = $answers['site'] . $answers['env'];
      $this->executeCommands([
        "./vendor/bin/blt vm",
      ], $dir_name);

      if ($answers['download_db']) {
        $this->executeCommands([
          "./vendor/bin/blt setup:build",
          "./vendor/bin/blt local:sync -Ddrush.aliases.remote=$remote_alias",
        ], $dir_name);
      }
      else {
        $this->executeCommands([
          "./vendor/bin/blt local:setup",
        ], $dir_name);
      }

      if ($answers['download_files']) {
          $this->executeCommands([
            "drush rsync @$remote_alias:%files @self:%files"
          ], $dir_name . '/docroot');
      }

      $this->executeCommands([
        "./vendor/bin/drush @{$answers['machine_name']}.local uli",
      ], $dir_name);
    }
  }

  protected function verifyBltVersion($composer_lock) {
    foreach ($composer_lock['packages'] as $package) {
      if ($package['name'] == 'acquia/blt') {
        if ($package['version'] != '8.x-dev') {
          $semver = new version($package['version']);
          if (!$semver->satisfies(new expression(self::BLT_VERSION_CONSTRAINT))) {
            $constraint = self::BLT_VERSION_CONSTRAINT;
            $this->output->writeln("<error>This project's version of BLT does not satisfy the required version constraint of $constraint.");
            exit(1);
          }
        }
      }
    }
  }
}
