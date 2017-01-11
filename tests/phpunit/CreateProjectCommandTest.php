<?php

namespace Acquia\Club\Tests\Command;

use Acquia\Club\Command\CreateProjectCommand;
use Acquia\Club\Tests\TestBase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

class CreateProjectCommandTest extends TestBase
{

    /** @var Filesystem */
    protected $fs;

    protected $machine_name = 'test_project';

    public function setUp()
    {
        parent::setUp();

        $test_dir = __DIR__ . '/../../test_project';
        if (file_exists($test_dir)) {
            $this->fs = new Filesystem();
            $this->fs->remove($test_dir);
        }
    }

    public function testCreateProjectRecipe() {
        $this->application->add(new CreateProjectCommand());

        $command = $this->application->find('create-project');
        $commandTester = new CommandTester($command);
        $commandTester->setInputs([
            // Create new project now?
            'yes',
            // Do you want to push this to an Acquia Cloud subscription?
            'no',
        ]);
        $commandTester->execute(array(
            'command'  => $command->getName(),
            '--recipe' => 'tests/recipes/good.yml'
        ));

        $status_code = $commandTester->getStatusCode();
        $this->assertEquals($status_code, 0, "The command create-project exited with a non-zero code: " . $commandTester->getDisplay());
        $output = $commandTester->getDisplay();
        $this->assertProjectFileExists('acquia-pipelines.yml');
        $this->assertProjectFileExists('composer.json');
        $this->assertProjectFileExists('composer.lock');
        $this->assertProjectFileExists('blt/project.yml');
        $this->assertProjectFileExists('blt/.schema_version');
        $this->assertProjectFileExists('docroot/modules/contrib/acquia_blog');
        $this->assertProjectFileNotExists('box/config.yml');
        $this->assertProjectFileNotExists('VagrantFile');
        $this->assertContains('Your project was created in', $output);
    }

    /**
     * Tests the 'create-project' command.
     */
    public function testCreateProject()
    {
        $this->application->add(new CreateProjectCommand());

        $command = $this->application->find('create-project');
        $commandTester = new CommandTester($command);
        $commandTester->setInputs([
          // Project title (human readable):
          'Test Project',
          // Project machine name:
          $this->machine_name,
          // Project prefix:
          'TP',
          // Do you want to create a VM?
          'no',
          // Do you want to use Continuous Integration?
          'yes',
          // Choose a Continuous Integration provider:
          'pipelines',
          // Do you want to add default ingredients?
          'yes',
          // Choose an ingredient: (acquia_blog)
          '1',
          // Choose an ingredient: (done)
          '0',
          // Create new project now?
          'yes',
          // Do you want to push this to an Acquia Cloud subscription?
          'no'
        ]);

        $commandTester->execute(array(
            'command'  => $command->getName(),
        ));

        $status_code = $commandTester->getStatusCode();
        $this->assertEquals($status_code, 0, "The command create-project exited with a non-zero code: " . $commandTester->getDisplay());
        $output = $commandTester->getDisplay();
        $this->assertProjectFileExists('acquia-pipelines.yml');
        $this->assertProjectFileExists('composer.json');
        $this->assertProjectFileExists('composer.lock');
        $this->assertProjectFileExists('blt/project.yml');
        $this->assertProjectFileExists('blt/.schema_version');
        $this->assertProjectFileExists('docroot/modules/contrib/acquia_blog');
        $this->assertProjectFileNotExists('box/config.yml');
        $this->assertProjectFileNotExists('VagrantFile');
        $this->assertContains('Your project was created in', $output);
    }

    /**
     * @param $filename
     * @param string $message
     */
    public function assertProjectFileExists($filename, $message = '') {
        $this->assertFileExists($this->machine_name . '/' . $filename, $message);
    }

    /**
     * @param $filename
     * @param string $message
     */
    public function assertProjectFileNotExists($filename, $message = '') {
        $this->assertFileNotExists($this->machine_name . '/' . $filename, $message);
    }

}
