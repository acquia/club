<?php

namespace Acquia\BltValet\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;

class CreateProjectCommand extends CommandBase
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

    $this->output->writeln("<info>Let's start by entering some information about your project.</info>");

    $helper = $this->getHelper('question');
    $question = new Question('<question>Project human name:</question> ');
    $human_name = $helper->ask($input, $output, $question);

    $default_machine_name = self::convertStringMachineSafe($human_name);
    $question = new Question("<question>Project machine name:</question> <info>[$default_machine_name]</info>", $default_machine_name);
    $machine_name = $helper->ask($input, $output, $question);

    $question = new Question('<question>Project prefix:</question> ');
    $prefix = $helper->ask($input, $output, $question);

    $this->output->writeln("<info>Great. Now let's make some choices about how your project will be set up.");
    $question = new ConfirmationQuestion('<question>Do you want to create a VM?</question> ', true);
    $vm = $helper->ask($input, $output, $question);

//    $question = new ConfirmationQuestion('<question>Do you want to create an Acquia Cloud free tier site for this project?</question> ', false);
//    $create_acf_site = $helper->ask($input, $output, $question);
//
//    -> composer create-project blt-project:~8 [machine_name] --no-interaction
//    -> cd [machine_name]
//    -> modify project.yml
//    -> composer blt-alias
//    -> ./vendor/bin/blt vm
//    -> ./vendor/bin/blt local:setup
//    -> createAcfSite()
//
  }

  /**
   * @param $identifier
   * @param array $filter
   *
   * @return mixed
   */
  public static function convertStringMachineSafe($identifier, array $filter = array(
    ' ' => '_',
    '-' => '_',
    '/' => '_',
    '[' => '_',
    ']' => '',
  )) {
    $identifier = str_replace(array_keys($filter), array_values($filter), $identifier);

    // Valid characters in a CSS identifier are:
    // - the hyphen (U+002D)
    // - a-z (U+0030 - U+0039)
    // - A-Z (U+0041 - U+005A)
    // - the underscore (U+005F)
    // - 0-9 (U+0061 - U+007A)
    // - ISO 10646 characters U+00A1 and higher
    // We strip out any character not in the above list.
    $identifier = preg_replace('/[^\x{002D}\x{0030}-\x{0039}\x{0041}-\x{005A}\x{005F}\x{0061}-\x{007A}\x{00A1}-\x{FFFF}]/u', '', $identifier);
    // Identifiers cannot start with a digit, two hyphens, or a hyphen followed by a digit.
    $identifier = preg_replace(array(
      '/^[0-9]/',
      '/^(-[0-9])|^(--)/'
    ), array('_', '__'), $identifier);

    return strtolower($identifier);
  }
}
