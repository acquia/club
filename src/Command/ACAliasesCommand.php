<?php

namespace Acquia\BltValet\Command;

use Acquia\Cloud\Api\CloudApiClient;
use Acquia\Cloud\Api\Response\SiteNames;
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

  /** @var CloudApiClient */
  protected $cloudApiClient;

  protected function configure()
  {
    $this
      ->setName('ac-aliases')
      ->setDescription('Updates your local drush aliases for Acquia Cloud subscriptions.')
      ->setHelp("This command will download ")
    ;
  }

  protected function execute(InputInterface $input, OutputInterface $output)
  {
    $config = $this->getCloudApiConfig();
    $this->cloudApiClient = $this->getCloudApiClient($config['email'], $config['key']);

    $this->output->writeln("<info>Gathering sites list from Acquia Cloud.</info>");
    $sites = (array) $this->cloudApiClient->sites();
    $sitesCount = count($sites);

    $progress = new ProgressBar($output, $sitesCount);
    $progress->setFormat("<info><fg=white;bg=blue>%current%/%max% [%bar%] %percent:3s%% \n %message%</>");
    $progress->setMessage('Starting Aliases sync...');
    $this->output->writeln("<info>Found " . $sitesCount . " subscription(s). Gathering information about each.</info>");
    foreach ($sites as $site) {
      $progress->setMessage('Syncing: ' . $site);
      try {
        $this->getSiteAliases($site, $progress);
      }
      catch (\Exception $e) {
        $output->writeln("<error>Could not fetch alias data for $site.</error>");
      }
      $progress->advance();
    }
    $progress->setMessage("Syncing: complete. \n");
    $progress->finish();
    $this->output->writeln("<info>Aliases were written to, type 'drush sa' to see them.");
  }

  /**
   * @param $site SiteNames[]
   */
  protected function getSiteAliases($site) {
    // Skip AC trex sites because the api breaks on them.
    $skip_site = false;
    if (strpos($site, 'trex') !== false) {
      $skip_site = true;
    }
    if (!$skip_site) {
      // gather our environments.
      $environments = $this->cloudApiClient->environments($site);
      // Lets split the site name in the format ac-realm:ac-site
      $site_split = explode(':', $site);
      $siteRealm = $site_split[0];
      $site_id = $site_split[1];

      // Loop over all environments.
      foreach($environments as $env) {
        // Build our variables incase API changes.
        $envName = $env->name();
        $uri = $env->defaultDomain();
        $remoteHost = $env->sshHost();
        $remoteUser = $env['unix_username'];
        $docroot = '/var/www/html/' . $site_id . '.' . $envName . '/docroot';

        $aliases[$envName] = array(
          'env-name' => $envName,
          'root' => $docroot,
          'ac-site' => $site_id,
          'ac-env' => $envName,
          'ac-realm' => $siteRealm,
          'uri' => $uri,
          'remote-host' => $remoteHost,
          'remote-user' => $remoteUser,
        );
      }

      $this->writeSiteAliases($site_id, $aliases);
    }
  }

  protected function writeSiteAliases($site_id, $aliases) {
    // Load twig template
    $loader = new Twig_Loader_Filesystem(__DIR__ . '/../../Resources/templates');
    $twig = new Twig_Environment($loader);
    // Render our aliases.
    $aliasesRender = $twig->render('aliases.php.twig', array('aliases' => $aliases));
    $aliasesFileName = $this->drushAliasDir . '/' . $site_id . '.aliases.drushrc.php';
    // Write to file.
    file_put_contents($aliasesFileName, $aliasesRender);
  }

}
