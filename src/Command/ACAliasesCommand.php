<?php

namespace Acquia\Club\Command;

use Acquia\Cloud\Api\CloudApiClient;
use Acquia\Cloud\Api\Response\SiteNames;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Helper\ProgressBar;
use Twig_Environment;
use Twig_Loader_Filesystem;

class AcAliasesCommand extends CommandBase
{

  /** @var CloudApiClient */
    protected $cloudApiClient;

    /** @var ProgressBar */
    protected $progressBar;


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
        $question = new ConfirmationQuestion(
            '<comment>This will overwrite existing drush aliases. Do you want to continue?</comment> ',
            false
        );
        $continue = $this->questionHelper->ask($input, $output, $question);
        if (!$continue) {
            return 1;
        }

        $this->cloudApiClient = $this->getCloudApiClient();

        $this->output->writeln("<info>Gathering sites list from Acquia Cloud.</info>");
        $sites = (array) $this->cloudApiClient->sites();
        $sitesCount = count($sites);

        $this->progressBar = new ProgressBar($output, $sitesCount);

        $style = new OutputFormatterStyle('white', 'blue');
        $output->getFormatter()->setStyle('status', $style);
        $this->progressBar->setFormat("<status> %current%/%max% subscriptions [%bar%] %percent:3s%% \n %message%</status>");
        $this->progressBar->setMessage('Starting Aliases sync...');
        $this->output->writeln(
            "<info>Found " . $sitesCount . " subscription(s). Gathering information about each.</info>\n"
        );
        $errors = [];
        $this->progressBar->setRedrawFrequency(0.1);
        foreach ($sites as $site) {
            $this->progressBar->setMessage('Syncing: ' . $site);
            try {
                $this->getSiteAliases($site);
            } catch (\Exception $e) {
                $errors[] = "Could not fetch alias data for $site.";
                $this->output->writeln($e->getMessage(), OutputInterface::VERBOSITY_VERBOSE);
            }
            $this->progressBar->advance();
        }
        $this->progressBar->setMessage("Syncing: complete. \n");
        $this->progressBar->clear();
        $this->progressBar->finish();

        if ($errors) {
            $formatter = $this->getHelper('formatter');
            $formattedBlock = $formatter->formatBlock($errors, 'error');
            $output->writeln($formattedBlock);
        }

        $this->output->writeln("<info>Aliases were written to, type 'drush sa' to see them.</info>");
    }

  /**
   * @param $site SiteNames[]
   */
    protected function getSiteAliases($site)
    {
        // Skip AC trex sites because the api breaks on them.
        $skip_site = false;
        if (strpos($site, 'trex') !== false
         || strpos($site, ':*') !== false) {
            $skip_site = true;
        }
        if (!$skip_site) {
            // gather our environments.
            $environments = $this->cloudApiClient->environments($site);
            // Lets split the site name in the format ac-realm:ac-site
            $site_split = explode(':', $site);
            $siteRealm = $site_split[0];
            $siteID = $site_split[1];

            // Loop over all environments.
            foreach ($environments as $env) {
                // Build our variables in case API changes.
                $envName = $env->name();
                $uri = $env->defaultDomain();
                $remoteHost = $env->sshHost();
                $remoteUser = $env['unix_username'];
                $docroot = '/var/www/html/' . $siteID . '.' . $envName . '/docroot';

                $aliases[$envName] = array(
                'env-name' => $envName,
                'root' => $docroot,
                'ac-site' => $siteID,
                'ac-env' => $envName,
                'ac-realm' => $siteRealm,
                'uri' => $uri,
                'remote-host' => $remoteHost,
                'remote-user' => $remoteUser,
                );
            }
            if ($siteRealm == 'enterprise-g1') {
                $acsf_site_url = 'https://www.' . $siteID . '.acsitefactory.com';
                if ($this->checkForACSFCredentials($siteID)) {
                    // @TODO: Ask the user if they want to update the credentials
                    $sites = $this->getACSFAliases($siteID, $acsf_site_url);
                    foreach ($sites as $site) {
                        $aliases[$site->site] = array();
                        $aliases[$site->site]['uri'] = $site->domain;
                        $aliases[$site->site]['parent'] = '@' . $siteID . '.01_live';
                        $aliases[$site->site]['site'] = $site->site;
                    }
                } else {
                    $this->progressBar->clear();
                    $question = new ConfirmationQuestion(
                        "<comment>Found an Acquia Cloud Site Factory instance named <info>" . $siteID . "</info>.\nTo setup aliases for this instance you will need an instance-specific API key found at <info>" . $acsf_site_url . "</info>. \nDo you want to download aliases for this instance? [y/n]:</comment> ",
                        false
                    );
                    $continue = $this->questionHelper->ask($this->input, $this->output, $question);
                    if ($continue) {
                        $this->askForACSFCredentials($siteID);
                        $this->getACSFAliases($siteID, $acsf_site_url);
                    } else {
                        $config = $this->cloudApiConfig;
                        $acsfConfig = array( "$siteID" => array(
                        'username' => '',
                        'apikey' => '',
                        'enabled' => false
                        )
                        );
                        // @todo this fails when "n" is selected
                        $config = array_merge_recursive($config, $acsfConfig);
                        $this->writeCloudApiConfig($config);
                    }
                }

                $this->progressBar->display();
            }
            $this->writeSiteAliases($siteID, $aliases);
        }
    }

    protected function writeSiteAliases($site_id, $aliases)
    {
        // Load twig template
        $loader = new Twig_Loader_Filesystem(__DIR__ . '/../../Resources/templates');
        $twig = new Twig_Environment($loader);
        // Render our aliases.
        $aliasesRender = $twig->render('aliases.php.twig', array('aliases' => $aliases));
        $aliasesFileName = $this->drushAliasDir . '/' . $site_id . '.aliases.drushrc.php';
        // Write to file.
        file_put_contents($aliasesFileName, $aliasesRender);
    }

    protected function getACSFAliases($siteID, $acsf_site_url)
    {

        $username = $this->cloudApiConfig[$siteID]['username'];
        $apikey = $this->cloudApiConfig[$siteID]['apikey'];
        $creds = base64_encode($username . ':' . $apikey);

        try {
            $sitesList = $this->curlCallToURL($acsf_site_url, $creds);
            $count = $sitesList['count'];
            if ($count > 100) {
                $numberOfPages = $count / 100;
                for ($i=2; $i <= ceil($numberOfPages); $i++) {
                    $sitesList = array_merge_recursive($sitesList, $this->curlCallToURL($acsf_site_url, $creds, $i));
                }
            }
            return $sitesList['sites'];
        } catch (\Exception $e) {
            $this->output->writeln("<error>Failed to load ACSF sites.</error>");
            return false;
        }
    }



    protected function checkForACSFCredentials($siteId)
    {
        if (isset($this->cloudApiConfig[$siteId])) {
            return true;
        } else {
            return false;
        }
    }

    /**
     *
     */
    protected function askForACSFCredentials($siteId)
    {
        $usernameQuestion = new Question("<question>Please enter your ACSF username for $siteId</question>: ", '');
        $privateKeyQuestion = new Question("<question>Please enter your ACSF API key for $siteId</question>: ", '');
        $privateKeyQuestion->setHidden(true);
        $username = $this->questionHelper->ask($this->input, $this->output, $usernameQuestion);
        $apikey = $this->questionHelper->ask($this->input, $this->output, $privateKeyQuestion);

        $config = $this->cloudApiConfig;
        $acsfConfig = array( "$siteId" => array(
          'username' => $username,
          'apikey' => $apikey,
          'enabled' => true
        )
        );
        $this->cloudApiConfig = array_merge_recursive($config, $acsfConfig);
        $this->writeCloudApiConfig($this->cloudApiConfig);
    }


    protected function curlCallToURL($url, $creds, $page = 1, $limit = 100)
    {
        $full_url = $url . '/api/v1/sites?limit=' . $limit . '&page=' . $page;
        // Get cURL resource
        $ch = curl_init();
        // Set url
        curl_setopt($ch, CURLOPT_URL, $full_url);
        // Set method
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
        // Set options
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        // Set headers
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Basic " . $creds,
        ]);
        // Send the request & save response to $resp
        $resp = curl_exec($ch);

        if (!$resp) {
            die('Error: "' . curl_error($ch) . '" - Code: ' . curl_errno($ch));
        }
        if (curl_getinfo($ch, CURLINFO_HTTP_CODE) == 403) {
            throw new \Exception('API Authorization failed.');
        }
        // Close request to clear up some resources
        curl_close($ch);
        return (array)json_decode($resp);
    }
}
