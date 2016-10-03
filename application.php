<?php

set_time_limit(0);
require __DIR__ . '/vendor/autoload.php';

use Acquia\Club\Command\CheckRequirementsCommand;
use Acquia\Club\Command\CreateProjectCommand;
use Acquia\Club\Command\PullProjectCommand;
use Acquia\Club\Command\AcAliasesCommand;
use Symfony\Component\Console\Application;

$application = new Application();
$application->add(new CheckRequirementsCommand());
$application->add(new CreateProjectCommand());
$application->add(new PullProjectCommand());
$application->add(new AcAliasesCommand());
$application->run();
