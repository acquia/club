<?php

set_time_limit(0);
require __DIR__ . '/vendor/autoload.php';

use Acquia\BltValet\Command\CreateProject;
use Symfony\Component\Console\Application;

$application = new Application();
$application->add(new CreateProject());
$application->run();
