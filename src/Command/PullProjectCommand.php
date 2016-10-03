<?php

namespace Acquia\Club\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;

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
    $config = $this->getCloudApiConfig();
    $cloud_api_client = $this->getCloudApiClient($config['email'], $config['key']);

    $helper = $this->getHelper('question');
    $question = new ChoiceQuestion('<question>Which site would you like to pull?</question>', (array) $cloud_api_client->sites());
    $site = $helper->ask($input, $output, $question);

    $this->output->writeln("<info>Great. Now let's make some choices about how your project will be set up.");
    $question = new ConfirmationQuestion('<question>Do you want to create a VM?</question> ', true);
    $vm = $helper->ask($input, $output, $question);

//    @todo
//    - Do you want to download a database from Acquia Cloud?
//    -- y: provide list of environments to choose from
//    - Do you want to download files from Acquia Cloud?
//    -- y: provide list of environments to choose from
//    -> git clone [remote]
//    -> cd [machine_name]
//    -> composer blt-alias
//    -> ./vendor/bin/blt vm
//    -> ./vendor/bin/blt local:setup
//    -> sync db? ./vendor/bin/blt local:sync
//    -> sync files? drush rsync @remote:%files @local:%files
  }

}
