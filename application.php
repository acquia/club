<?php

set_time_limit(0);
require __DIR__ . '/vendor/autoload.php';

use Acquia\BltValet\Command\CreateProject;
use Acquia\BltValet\Command\GetSites;
use Acquia\BltValet\Command\Configure;
use Symfony\Component\Console\Application;

$application = new Application();
$application->add(new CreateProject());
$application->add(new GetSites());
$application->add(new Configure());
$application->run();
