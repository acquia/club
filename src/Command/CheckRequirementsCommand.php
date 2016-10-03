<?php

namespace Acquia\BltValet\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;
use Tivie\OS\Detector;

class CheckRequirementsCommand extends CommandBase
{

  /** @var Detector */
  protected $os;

  protected function configure()
  {
    $this
      ->setName('check-requirements')
      ->setDescription('Verifies that your machine meets the system requirements.')
      ->setHelp("This command checks your machine against system requirements.")
    ;
  }

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
    parent::initialize($input, $output);

    $this->os = $this->getOperatingSystem();
  }

  protected function execute(InputInterface $input, OutputInterface $output)
  {

    if (empty($_ENV)) {
      $formatter = $this->getHelper('formatter');
      $errorMessages = [
        "Your PHP installation is not configured to support environmental variables.",
        "Please ensure that you variable_order setting for PHP contains an \"E\".",
      ];
      $formattedBlock = $formatter->formatBlock($errorMessages, 'error');
      $output->writeln($formattedBlock);

      return 1;
    }

    // @todo Check versions!
    $required_binaries = [
      'composer',
      'vagrant',
      'ansible',
      'VirtualBox',
      'drush',
      'git',
    ];


    $missing_requirements = $this->getMissingRequirements($required_binaries);
    $this->installRequirements($missing_requirements);

    $this->output->writeln('');

    return 0;
  }

  protected function installRequirements($binaries) {
    foreach ($binaries as $binary) {
      $this->installRequirement($binary);
    }
  }

  protected function getMissingRequirements($binaries) {
    $missing = [];
    foreach ($binaries as $binary) {
      if (!$this->checkRequirement($binary)) {
        $missing[] = $binary;
        $this->output->writeln("<comment>$binary is not installed.</comment>");
      }
      else {
        $this->output->writeln("<info>$binary is already installed.</info>");
      }
    }

    return $missing;
  }

  protected function checkRequirement($binary) {
    return $this->commandExists($binary);
  }

  protected function commandExists($bin) {
    return $this->executeCommand("command -v $bin", null, false, false);
  }

  protected function installRequirement($requirement) {
    if (!$this->checkRequirement($requirement)) {
      $method_name = 'install' . ucfirst($requirement);
      if (method_exists($this, $method_name)) {
        $this->output->writeln("<comment>Attempting to install $requirement...</comment>");
        $this->{"install$requirement"}();
      }
    }
  }

  protected function installComposer() {
    switch ($this->os->getType() == MACOSX) {
      case 'OSX':
        $this->brewInstall('composer');
        break;
    }
  }

  protected function installAnsible() {
    switch ($this->os->getType() == MACOSX) {
      case 'OSX':
        $this->brewInstall('ansible');
        break;
    }
  }

  protected function installVirtualBox() {
    switch ($this->os->getType() == MACOSX) {
      case 'OSX':
        $this->brewInstall('virtualbox');
        break;
    }
  }

  protected function installVagrant() {
    switch ($this->os->getType() == MACOSX) {
      case 'OSX':
        $this->brewInstall('vagrant');
        break;
    }
  }

  protected function installDrush() {
    switch ($this->os->getType() == MACOSX) {
      case 'OSX':
        $this->brewInstall('drush');
        break;
    }
  }

  protected function installGit() {
    switch ($this->os->getType() == MACOSX) {
      case 'OSX':
        $this->brewInstall('git');
        break;
    }
  }

  protected function brewInstall($formula) {
    $this->checkRequirement('homebrew');
    // @todo check for brew cask.
    $success = $this->executeCommand("brew install $formula");
    $this->output->writeln("<info>Installed $formula successfully.</info>");

    return $success;
  }

  protected function installHomebrew() {
    $this->executeCommand('/usr/bin/ruby -e "$(curl -fsSL https://raw.githubusercontent.com/Homebrew/install/master/install)"');
  }

  protected function getOperatingSystem() {
    $os = new Detector();

    return $os;
  }

}
