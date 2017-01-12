<?php

set_time_limit(0);
require __DIR__ . '/../vendor/autoload.php';

use Acquia\Club\Command\CreateProjectCommand;
use Acquia\Club\Command\PullProjectCommand;
use Acquia\Club\Command\AcAliasesCommand;
use Symfony\Component\Console\Application;
use Acquia\Club\Command\LocalEnvironmentFacade;

$application = new Application('club', '@package_version@');

// We create a rudimentary container of our own here. The alternative is to
// require Symfony's FrameworkBundle, which transforms Club into a fully
// fledged Symfony web application rather than a lightweight console
// application.
$container = [
    'localEnvironmentFacade' => new LocalEnvironmentFacade(),
];

$application->addCommands([
    new CreateProjectCommand($container['localEnvironmentFacade']),
    new PullProjectCommand($container['localEnvironmentFacade']),
    new AcAliasesCommand($container['localEnvironmentFacade']),
]);
$application->run();
