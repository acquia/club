<?php

namespace Acquia\Club\Command;

use Acquia\Club\Configuration\ProjectConfiguration;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Filesystem\Exception\FileNotFoundException;
use Symfony\Component\Yaml\Yaml;

/**
 * @var int
 */
const MACOSX = 33;

/**
 * Class CreateProjectCommand
 *
 * @package Acquia\Club\Command
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
            ->addOption('recipe', 'r', InputOption::VALUE_OPTIONAL)
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
        $this->checkXdebug();
        $this->checkCwd();

        $recipe_filename = $input->getOption('recipe');
        if ($recipe_filename) {
            $answers = $this->loadConfigFile($recipe_filename);
            $this->checkDestinationDir($answers['machine_name']);
        } else {
            $answers = $this->askForAnswers();
        }

        $this->output->writeln("<comment>You have entered the following values:</comment>");
        $this->printArrayAsTable($answers);
        $question = new ConfirmationQuestion('<question>Create new project now?</question> ', true);
        $create = $this->questionHelper->ask($input, $output, $question);

        if ($create) {
            $this->createProject($answers);
        }

        $cwd = getcwd() . '/' . $answers['machine_name'];
        if ($answers['vm']) {
            $this->createVm($answers);
        }

        if (!empty($answers['ci']['provider'])) {
            $this->executeCommands([
                "./vendor/bin/blt ci:{$answers['ci']['provider']}:init",
            ], $cwd);
        }

        $question = new ConfirmationQuestion('<question>Do you want to push this to an Acquia Cloud subscription?</question> <info>[yes]</info> ', true);
        $ac = $this->questionHelper->ask($this->input, $this->output, $question);
        if ($ac) {
            $this->cloudApiConfig = $this->loadCloudApiConfig();
            $this->setCloudApiClient($this->cloudApiConfig['email'], $this->cloudApiClient['key']);
            $cloud_api_client = $this->getCloudApiClient();
            $answers['ac']['site'] = $this->askWhichCloudSite($cloud_api_client);
            $site = $this->getSiteByLabel($cloud_api_client, $answers['ac']['site']);
            $answers['ac']['env'] = $this->askWhichCloudEnvironment($cloud_api_client, $site);

            $this->executeCommands([
                "git push {$site->vcsUrl()}",
            ], $cwd);

            if ($answers['ci']['provider'] == 'pipelines') {
                $question = new ConfirmationQuestion('<question>Start a pipelines build now?</question> <info>[yes]</info> ', true);
                $this->output->writeln("<info>You must have pipelines already enabled.</info>");
                $pipelines_start = $this->questionHelper->ask($this->input, $this->output, $question);
                if ($pipelines_start) {
                    $this->executeCommands([
                        "pipelines start",
                    ], $cwd);
                    $this->output->writeln("<comment>Starting a pipelines build to generate a deployment artifact on cloud.</comment>");
                }
            }

            // @todo Deploy branch and install.
            // @todo drush uli remote.
            // $question = new ConfirmationQuestion("<question>Do you want to install Drupal on Acquia Cloud's {$answers['env']}?</question> <info>[yes]</info> ", true);
            // $answers['ac']['install'] = $this->questionHelper->ask($this->input, $this->output, $question);
        }

        $this->output->writeln("<info>Your project was created in $cwd.</info>");
        if ($answers['vm']) {
            $this->output->writeln("<info>A virtual machine was created. You can login to your site by running:</info>");
            $this->output->writeln("<comment>drush @{$answers['machine_name']}.local</comment>");
        }
    }

    /**
     * @param string $filename
     *
     * @return array
     */
    protected function loadConfigFile($filename)
    {
        if (!file_exists($filename)) {
            throw new FileNotFoundException($filename);
        }

        $recipe = Yaml::parse(
            file_get_contents($filename)
        );
        $configs = [ $recipe ];
        $processor = new Processor();
        $configuration_tree = new ProjectConfiguration();
        $processed_configuration = $processor->processConfiguration(
            $configuration_tree,
            $configs
        );

        return $processed_configuration;
    }

    /**
     * @return array
     */
    protected function askForAnswers()
    {
        $this->output->writeln("<info>Let's start by entering some information about your project.</info>");

        $question = new Question('<question>Project title (human readable):</question> ');
        $this->requireQuestion($question);
        $answers['human_name'] = $this->questionHelper->ask($this->input, $this->output, $question);

        $default_machine_name = self::convertStringToMachineSafe($answers['human_name']);
        $question = new Question("<question>Project machine name:</question> <info>[$default_machine_name]</info> ", $default_machine_name);
        $answers['machine_name'] = $this->questionHelper->ask($this->input, $this->output, $question);

        $this->checkDestinationDir($answers['machine_name']);

        $default_prefix = self::convertStringToPrefix($answers['human_name']);
        $question = new Question("<question>Project prefix:</question> <info>[$default_prefix]</info>", $default_prefix);
        $answers['prefix'] = $this->questionHelper->ask($this->input, $this->output, $question);

        $this->output->writeln("<info>Great. Now let's make some choices about how your project will be set up.</info>");
        $question = new ConfirmationQuestion('<question>Do you want to create a VM?</question> <info>[yes]</info> ', true);
        $answers['vm'] = $this->questionHelper->ask($this->input, $this->output, $question);

        $question = new ConfirmationQuestion('<question>Do you want to use Continuous Integration?</question> <info>[yes]</info> ', true);
        $ci = $this->questionHelper->ask($this->input, $this->output, $question);
        if ($ci) {
            $provider_options = [
                'pipelines' => 'Acquia Pipelines',
                'travis' => 'Travis CI',
            ];
            $question = new ChoiceQuestion('<question>Choose a Continuous Integration provider: </question> <info>[pipelines]</info>', $provider_options, [1]);
            $answers['ci']['provider'] = $this->questionHelper->ask($this->input, $this->output, $question);
        }

        // $question = new ConfirmationQuestion('<question>Do you want to create an Acquia Cloud free tier site for this project?</question> ', false);
        // $create_acf_site = $helper->ask($input, $output, $question);

        $question = new ConfirmationQuestion('<question>Do you want to add default ingredients?</question> <info>[yes]</info> ', true);
        $ingredients = $this->questionHelper->ask($this->input, $this->output, $question);
        if ($ingredients) {
            $done_value = 'done';
            $available_ingredients_config = Yaml::parse(file_get_contents(__DIR__ . '/../../Resources/available_ingredients.yml'));
            $available_ingredients = $available_ingredients_config['available_ingredients'];
            array_unshift($available_ingredients, $done_value);
            $i = 0;
            $answers['features'] = [];
            do {
                $i++;
                $available_ingredients = array_diff($available_ingredients, $answers['features']);
                $question = new ChoiceQuestion("<question>Choose an ingredient: </question> <info>Choose $done_value empty to finish.</info>", $available_ingredients);
                $answers['features'][$i] = $this->questionHelper->ask($this->input, $this->output, $question);
                $this->output->writeln("<info>" . $answers['features'][$i] . " added</info>");
            } while($answers['features'][$i] != $done_value);
            if (($key = array_search($done_value, $answers['features'])) !== false) {
                unset($answers['features'][$key]);
            }

        }

        return $answers;
    }

    /**
     * @param array $answers
     */
    protected function createProject($answers)
    {
        $this->output->writeln("<info>Awesome. Let's create your project. This could take a while...");

        $this->executeCommands([
            "composer create-project acquia/blt-project:~8 {$answers['machine_name']} --no-interaction",
        ]);
        $this->updateProjectYml($answers);

        if (!empty($answers['features'])) {
            $cwd = getcwd() . '/' . $answers['machine_name'];
            foreach ($answers['features'] as $feature) {
                $this->executeCommand("composer require acquia-pso/$feature --no-update", $cwd);
            }
            $this->executeCommand("composer update", $cwd);
        }

        $this->output->writeln("<info>Your project has been created locally.</info>");
    }

    protected function updateProjectYml($answers)
    {
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
    }

    protected function createVm($answers)
    {
        $cwd = getcwd() . '/' . $answers['machine_name'];
        $this->executeCommands([
            "./vendor/bin/blt vm",
            "./vendor/bin/blt local:setup",
            "./vendor/bin/drush @{$answers['machine_name']}.local uli",
        ], $cwd);
    }

  /**
   * @param $string
   *
   * @return mixed
   */
    public static function convertStringToPrefix($string)
    {
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
    ))
    {
        $identifier = str_replace(array_keys($filter), array_values($filter), $identifier);

        // Valid characters are:
        // - a-z (U+0030 - U+0039)
        // - A-Z (U+0041 - U+005A)
        // - the underscore (U+005F)
        // - 0-9 (U+0061 - U+007A)
        // - ISO 10646 characters U+00A1 and higher
        // We strip out any character not in the above list.
        $identifier = preg_replace('/[^\x{0030}-\x{0039}\x{0041}-\x{005A}\x{005F}\x{0061}-\x{007A}\x{00A1}-\x{FFFF}]/u', '', $identifier);
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
    protected function requireQuestion(Question $question)
    {
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
    protected function printArrayAsTable($array)
    {
        $flattened_array = $this->flattenArray($array);
        $rowGenerator = function () use ($flattened_array) {
            $rows = [];
            foreach ($flattened_array as $key => $value) {
                if ($value == '1') {
                    $value = 'yes';
                } elseif ($value == '0') {
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

    /**
     * Flattens multi-dimensional array into two-dimensional array with dot-notated keys.
     * @param $array
     *
     * @return array
     */
    protected function flattenArray($array)
    {
        $iterator = new \RecursiveIteratorIterator(new \RecursiveArrayIterator($array));
        $result = [];
        foreach ($iterator as $leaf_value) {
            $keys = array();
            foreach (range(0, $iterator->getDepth()) as $depth) {
                $keys[] = $iterator->getSubIterator($depth)->key();
            }
            $result[ join('.', $keys) ] = $leaf_value;
        }

        return $result;
    }
}
