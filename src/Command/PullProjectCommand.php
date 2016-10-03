<?php

namespace Acquia\Club\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class PullProjectCommand extends CommandBase
{
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

    if (!$this->xDebugPrompt()) {
      return 1;
    }

    $config = $this->getCloudApiConfig();
    $cloud_api_client = $this->getCloudApiClient($config['email'], $config['key']);

    $helper = $this->getHelper('question');
    $question = new ChoiceQuestion('<question>Which site would you like to pull?</question>', $this->getSitesList($cloud_api_client));
    $answers['site'] = $helper->ask($input, $output, $question);
    $site = $this->getSiteByLabel($cloud_api_client, $answers['site']);
    $environments = $this->getEnvironmentsList($cloud_api_client, $site);
    $question = new ChoiceQuestion('<question>Which environment would you like to pull from (if applicable)?</question>', (array) $environments);
    $answers['env'] = $helper->ask($input, $output, $question);

    $this->output->writeln("<info>Great. Now let's make some choices about how your project will be set up locally.");
    $question = new ConfirmationQuestion('<question>Do you want to create a VM?</question> ', true);
    $answers['vm'] = $helper->ask($input, $output, $question);

    if ($answers['vm']) {
      $question = new ConfirmationQuestion('<question>Do you want to download a database from Acquia Cloud?</question> ', true);
      $answers['download_db'] = $helper->ask($input, $output, $question);

      $question = new ConfirmationQuestion('<question>Do you want to download the public and private file directories from Acquia Cloud?</question> ', true);
      $answers['download_files'] = $helper->ask($input, $output, $question);
    }

    $dir_name = $answers['site'];
    $this->executeCommands([
      "git clone {$site->vcsUrl()} $dir_name",
    ]);

    $this->executeCommands([
      'composer install',
      'composer blt-alias',
    ], $dir_name);

    if ($answers['vm']) {
      $remote_alias = $answers['site'] . $answers['env'];
      $this->executeCommands([
        "./vendor/bin/blt vm",
        "./vendor/bin/blt local:setup",
        "./vendor/bin/blt local:sync -Ddrush.aliases.remote=$remote_alias",
        "./vendor/bin/drush @{$answers['machine_name']}.local uli",
      ], $dir_name);
      $this->output->writeln();
    }
    else {
      $this->output->writeln();
    }

//    @todo
//    -> sync db ./vendor/bin/blt local:sync
//    -> sync files? drush rsync @remote:%files @local:%files
  }

}
