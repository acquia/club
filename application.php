<?php

set_time_limit(0);
require __DIR__ . '/vendor/autoload.php';

use Acquia\BltValet\Command\CheckRequirementsCommand;
use Acquia\BltValet\Command\CreateProjectCommand;
use Acquia\BltValet\Command\PullProjectCommand;
use Acquia\BltValet\Command\AcAliasesCommand;
use Symfony\Component\Console\Application;

$application = new Application();
$application->add(new CheckRequirementsCommand());
$application->add(new CreateProjectCommand());
$application->add(new PullProjectCommand());
$application->add(new AcAliasesCommand());
$application->run();
