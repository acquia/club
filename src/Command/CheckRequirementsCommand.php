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
    $required_binaries = [
      'composer',
      'vagrant',
      'ansible',
      'VirtualBox',
      'drush',
      'git',
    ];

    // @todo Show missing requirements before installing.
    
    $this->checkRequirements($required_binaries);
  }

  protected function checkRequirements($binaries) {
    foreach ($binaries as $binary) {
      $this->checkRequirement($binary);
    }
  }

  protected function checkRequirement($binary) {
    if (!$this->commandExists($binary)) {
      $this->installRequirement($binary);
    }
  }

  protected function commandExists($bin) {
    return $this->executeCommand("command -v $bin", null, false);
  }

  protected function installRequirement($requirement) {
    $method_name = 'install' . ucfirst($requirement);
    if (method_exists($this, $method_name)) {
      $this->{"install$requirement"}();
    }
  }

  protected function installComposer() {
    if ($this->os->getType() == 'MACOSX') {
      $this->checkRequirement('homebrew');
      $this->executeCommand('brew install composer');
    }
  }

  protected function installAnsible() {
    if ($this->os->getType() == 'MACOSX') {
      $this->checkRequirement('homebrew');
      $this->executeCommand('brew install ansible');
    }
  }

  protected function installVirtualBox() {
    if ($this->os->getType() == 'MACOSX') {
      $this->checkRequirement('homebrew');
      $this->executeCommand('brew install virtualbox');
    }
  }

  protected function installHomebrew() {
    $this->executeCommand('/usr/bin/ruby -e "$(curl -fsSL https://raw.githubusercontent.com/Homebrew/install/master/install)"');
  }

  protected function getOperatingSystem() {
    $os = new Detector();

    return $os;
  }

}
