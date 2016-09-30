<?php

namespace Acquia\BltValet\Command;

use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Yaml\Yaml;

/**
 * Class CreateProjectCommand
 *
 * @package Acquia\BltValet\Command
 */
class CreateProjectCommand extends CommandBase
{
  /**
   *
   */
  protected function configure()
  {
    $this
      ->setName('create-project')
      ->setDescription('Creates a new project.')
      ->setHelp("This command allows you to create projects...")
    ;
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *
   * @return bool
   */
  protected function execute(InputInterface $input, OutputInterface $output)
  {
    $helper = $this->getHelper('question');
    $answers = [];

    if (extension_loaded('xdebug')) {
      $this->output->writeln("<comment>You have xDebug enabled. This will make everything very slow. You should really disable it.</comment>");
      $question = new ConfirmationQuestion('<comment>Do you want to continue?</comment> ', true);

      $continue = $helper->ask($input, $output, $question);

      if (!$continue) {
        return FALSE;
      }
    }

    if ($this->fs->exists('.git')) {
      $formatter = $this->getHelper('formatter');
      $errorMessages = [
        "It looks like you're currently inside of a git repository.",
        "You can't create a new project inside of a repository.",
        'Please change directories and try again.',
      ];
      $formattedBlock = $formatter->formatBlock($errorMessages, 'error');
      $output->writeln($formattedBlock);

      return FALSE;
    }

    $this->checkSystemRequirements();

    $this->output->writeln("<info>Let's start by entering some information about your project.</info>");

    $question = new Question('<question>Project title (human readable):</question> ');
    $this->requireQuestion($question);
    $answers['human_name'] = $helper->ask($input, $output, $question);

    $default_machine_name = self::convertStringToMachineSafe($answers['human_name']);
    $question = new Question("<question>Project machine name:</question> <info>[$default_machine_name]</info> ", $default_machine_name);
    $answers['machine_name'] = $helper->ask($input, $output, $question);

    $destination_dir = getcwd() . '/' . $answers['machine_name'];
    if ($this->fs->exists($destination_dir)) {
      $this->output->writeln("<comment>Uh oh. The destination directory already exists.</comment>");
      $question = new ConfirmationQuestion("<comment>Delete $destination_dir?</comment> ", false);
      $delete_dir = $helper->ask($input, $output, $question);
      if ($delete_dir) {
        $this->fs->remove($destination_dir);
      }
      else {
        $output->writeln("<comment>Please choose a different machine name for your project, or change directories.</comment>");
        return FALSE;
      }
    }

    $default_prefix = self::convertStringToPrefix($answers['human_name']);
    $question = new Question("<question>Project prefix:</question> <info>[$default_prefix]</info>", $default_prefix);
    $answers['prefix'] = $helper->ask($input, $output, $question);

    $this->output->writeln("<info>Great. Now let's make some choices about how your project will be set up.</info>");
    $question = new ConfirmationQuestion('<question>Do you want to create a VM?</question> <info>[yes]</info> ', true);
    $answers['vm'] = $helper->ask($input, $output, $question);

    // $question = new ConfirmationQuestion('<question>Do you want to create an Acquia Cloud free tier site for this project?</question> ', false);
    // $create_acf_site = $helper->ask($input, $output, $question);

    $this->output->writeln("<comment>You have entered the following values:</comment>");
    $this->printArrayAsTable($answers);
    $question = new ConfirmationQuestion('<question>Create new project now?</question> ', true);
    $create = $helper->ask($input, $output, $question);

    if ($create) {
      $this->output->writeln("<info>Awesome. Let's create your project. This could take a while...");

      $this->executeCommands([
        "composer create-project acquia/blt-project:~8 {$answers['machine_name']} --no-interaction",
      ]);

      $cwd = getcwd() . '/' . $answers['machine_name'];
      $config_file = $cwd . '/project.yml';
      $config = Yaml::parse(file_get_contents($config_file));
      $config['project']['prefix'] = $answers['prefix'];
      $config['project']['machine_name'] = $answers['machine_name'];
      $config['project']['human_name'] = $answers['human_name'];
      // Hostname cannot contain underscores.
      $machine_name_safe = str_replace('_', '-', $answers['machine_name']);
      $config['project']['local']['hostname'] = str_replace('${project.machine_name}', $machine_name_safe, $config['project']['local']['hostname']);
      $this->fs->dumpFile($config_file, Yaml::dump($config));

      if ($answers['vm']) {
        $this->executeCommands([
          "./vendor/bin/blt vm",
          "./vendor/bin/blt local:setup",
          "./vendor/bin/drush @{$answers['machine_name']}.local uli",
        ], $cwd);
        $this->output->writeln();
      }
      else {
        $this->output->writeln();
      }
    }
  }

  /**
   * @return bool
   * @throws \Symfony\Component\Console\Exception\ExceptionInterface
   */
  protected function checkSystemRequirements() {
    $this->output->writeln("Checking your machine against system requirements...");
    $command = $this->getApplication()->find('check-requirements');
    $returnCode = $command->run($this->input, $this->output);
    if ($returnCode == 0) {
      $this->output->writeln("Looks good.");
    }
    else {
      $this->output->writeln("Your machine does not meet the system requirements.");

      return FALSE;
    }
  }

  /**
   * @param $string
   *
   * @return mixed
   */
  public static function convertStringToPrefix($string) {
    $words = explode(' ', $string);
    $prefix = '';
    foreach ($words as $word) {
      $prefix .= substr($word, 0, 1);
    }

    return strtoupper($prefix);
  }

  /**
   * @param $identifier
   * @param array $filter
   *
   * @return mixed
   */
  public static function convertStringToMachineSafe($identifier, array $filter = array(
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

  /**
   * @param \Symfony\Component\Console\Question\Question $question
   */
  protected function requireQuestion(Question $question) {
    $question->setValidator(function ($value) {
      if (trim($value) == '') {
        throw new \Exception('You must enter a value.');
      }
      return $value;
    });
  }

  /**
   * @param $array
   */
  protected function printArrayAsTable($array) {
    $rowGenerator = function() use ($array) {
      $rows = [];
      foreach ($array as $key => $value) {
        if ($value == '1') {
          $value = 'yes';
        }
        elseif ($value == '0') {
          $value = 'no';
        }
        $rows[] = [$key, $value];
      }
      return $rows;
    };

    $table = new Table($this->output);
    $table->setHeaders(array('Property', 'Value'))
      ->setRows($rowGenerator())
      ->render();
  }
}
