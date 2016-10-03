<?php

namespace Acquia\BltValet\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Helper\ProgressBar;
use Twig_Environment;
use Twig_Loader_Filesystem;

class ACAliasesCommand extends CommandBase
{
  protected function configure()
  {
    $this
      ->setName('ac-aliases')
      ->setDescription('Updates your aliases for Acquia cloud Subscriptions.')
      ->setHelp("This command updates your aliases yo...")
    ;
  }

  protected function execute(InputInterface $input, OutputInterface $output)
  {
    $config = $this->getCloudApiConfig();
    $cloud_api_client = $this->getCloudApiClient($config['email'], $config['key']);

    $this->output->writeln("<info>Gathering sites list from Acquia Cloud.</info>");
    $sites = (array) $cloud_api_client->sites();
    $sitesCount = count($sites);

    $progress = new ProgressBar($output, $sitesCount);
    $progress->setFormat("<info><fg=white;bg=blue>%current%/%max% [%bar%] %percent:3s%% \n %message%</>");
    $progress->setMessage('Starting Aliases sync...');
    $this->output->writeln("<info>Found " . $sitesCount . " subscription(s). Gathering information about each.</info>");
    foreach ($sites as $site) {
      //Skip AC trex sites because the api breaks on them.
      $skip_site = false;
      if (strpos($site, 'trex') !== false) {
        $skip_site = true;
      }
      if (!$skip_site) {
        // gather our environments.
        $environments = $cloud_api_client->environments($site);
        // Lets split the site name in the format ac-realm:ac-site
        $siteSplit = explode(':', $site);
        $siteRealm = $siteSplit[0];
        $siteId = $siteSplit[1];
        $progress->setMessage('Syncing: ' . $siteId);
        //Loop over all environments
        foreach($environments as $env) {
          // Build our variables incase API changes
          $envName = $env->name();
          $uri = $env->defaultDomain();
          $remoteHost = $env->sshHost();
          $remoteUser = $env['unix_username'];
          $docroot = '/var/www/html/' . $siteId . '.' . $envName . '/docroot';

          $aliases[$envName] = array(
            'env-name' => $envName,
            'root' => $docroot,
            'ac-site' => $siteId,
            'ac-env' => $envName,
            'ac-realm' => $siteRealm,
            'uri' => $uri,
            'remote-host' => $remoteHost,
            'remote-user' => $remoteUser,
          );
        }
        // Load twig template
        $loader = new Twig_Loader_Filesystem(__DIR__ . '/../../Resources/templates');
        $twig = new Twig_Environment($loader);
        // Render our aliases.
        $aliasesRender = $twig->render('aliases.php.twig', array('aliases' => $aliases));
        $aliasesFileName = $this->drushAliasDir . '/' . $siteId . '.aliases.drushrc.php';
        // Write to file.
        file_put_contents($aliasesFileName, $aliasesRender);
      }
      $progress->advance();
    }
    $progress->setMessage("Syncing: complete. \n");
    $progress->finish();
    $this->output->writeln("<info>Aliases were written to, type 'drush sa' to see them.");
  }

}
