<?php

namespace Acquia\Club\Command;

use Symfony\Component\Console\Helper\ProgressBar;
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

    /**
     * Initializes the command just after the input has been validated.
     *
     * This is mainly useful when a lot of commands extends one main command
     * where some things need to be initialized based on the input arguments and options.
     *
     * @param InputInterface  $input  An InputInterface instance
     * @param OutputInterface $output An OutputInterface instance
     */
    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        parent::initialize($input, $output);
        $this->cloudApiConfig = $this->loadCloudApiConfig();
        $this->setCloudApiClient($this->cloudApiConfig['email'], $this->cloudApiConfig['key']);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {

        $this->checkXdebug();

        $cloud_api_client = $this->getCloudApiClient();
        $answers['ac']['site'] = $this->askWhichCloudSite($cloud_api_client);
        $this->checkDestinationDir($answers['ac']['site']);
        $site = $this->getSiteByLabel($cloud_api_client, $answers['ac']['site']);
        $answers['ac']['env'] = $this->askWhichCloudEnvironment($cloud_api_client, $site);

        // @todo Determine which branch is on the env and pull that branch.

        $dir_name = $answers['ac']['site'];
        $this->executeCommands([
        "git clone {$site->vcsUrl()} $dir_name",
        ]);

        if (file_exists($dir_name . '/composer.lock')) {
            $composer_lock = json_decode(file_get_contents($dir_name . '/composer.lock'), true);
            $this->verifyBltVersion($composer_lock);
        }
        else {
            $this->output->writeln("<error>No composer.lock file was found in the repository. Is this BLT project?");
            exit(1);
        }

        $this->output->writeln(
            "<info>Great. Now let's make some choices about how your project will be set up locally."
        );
        $question = new ConfirmationQuestion('<question>Do you want to create a VM?</question> ', true);
        $answers['vm'] = $this->questionHelper->ask($input, $output, $question);

        if ($answers['vm']) {
            $question = new ConfirmationQuestion(
                '<question>Do you want to download a database from Acquia Cloud?</question> ',
                true
            );
            $answers['download_db'] = $this->questionHelper->ask($input, $output, $question);

            // @todo Change to a choice btw download and stage file proxy.
            $question = new ConfirmationQuestion(
                '<question>Do you want to download the public and private file directories from Acquia Cloud?</question> ',
                true
            );
            $answers['download_files'] = $this->questionHelper->ask($input, $output, $question);
        }

        $this->output->writeln(
            "<info>Awesome. Let's pull down your project. This could take a while..."
        );

        $this->executeCommands([
        'composer install',
        'composer blt-alias',
        ], $dir_name);

        if ($answers['vm']) {
            $remote_alias = $answers['ac']['site'] . '.' . $answers['ac']['env'];
            $this->executeCommands([
            "./vendor/bin/blt vm",
            ], $dir_name);

            if ($answers['download_db']) {
                $this->executeCommands([
                "./vendor/bin/blt setup:build",
                "./vendor/bin/blt local:sync -Ddrush.aliases.remote=$remote_alias",
                ], $dir_name);
            } else {
                $this->executeCommands([
                "./vendor/bin/blt local:setup",
                ], $dir_name);
            }

            if ($answers['download_files']) {
                $this->executeCommands([
                  "drush rsync @$remote_alias:%files @self:%files"
                ], $dir_name . '/docroot');
            }

            // @todo Derive the local alias from project.local.yml's drush.aliases.local.
            $local_alias = "@{$answers['machine_name']}.local";
            $this->executeCommands([
                "./vendor/bin/drush $local_alias uli",
            ], $dir_name);
        }

        $this->output->writeln("<info>Your project was cloned to $dir_name.</info>");
        if ($answers['vm']) {
            $this->output->writeln("<info>A virtual machine was created. You can login to your site by running:</info>");
            $this->output->writeln("<comment>drush @{$answers['machine_name']}.local</comment>");
        }
    }

    protected function verifyBltVersion($composer_lock)
    {
        foreach ($composer_lock['packages'] as $package) {
            if ($package['name'] == 'acquia/blt') {
                if ($package['version'] == '8.x-dev') {
                    return true;
                }

                $semver = new version($package['version']);
                if (!$semver->satisfies(new expression(self::BLT_VERSION_CONSTRAINT))) {
                    $constraint = self::BLT_VERSION_CONSTRAINT;
                    $this->output->writeln(
                        "<error>This project's version of BLT does not satisfy the required version constraint of $constraint."
                    );
                    exit(1);
                }

                return true;
            }
        }

        $this->output->writeln("<error>acquia/blt was not found in this project's composer.lock file.");
        exit(1);
    }
}
