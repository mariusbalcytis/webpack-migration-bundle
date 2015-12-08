<?php
namespace Helper;

use Codeception\Module\Filesystem;
use Codeception\Module\Symfony2;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Process\Process;

class Functional extends Symfony2
{
    /**
     * @var CommandTester
     */
    protected $commandTester;

    protected function bootKernel()
    {
        if ($this->kernel) {
            return;
        }
        $this->kernel = new \TestKernel(
            $this->config['environment'],
            $this->config['debug']
        );
        $this->kernel->boot();
    }


    public function cleanUp()
    {
        /** @var Filesystem $filesystem */
        $filesystem = $this->getModule('Filesystem');

        if (file_exists(__DIR__ . '/../../functional/Fixtures/package.json')) {
            unlink(__DIR__ . '/../../functional/Fixtures/package.json');
        }
        if (file_exists(__DIR__ . '/../../functional/Fixtures/app/config/webpack.config.js')) {
            unlink(__DIR__ . '/../../functional/Fixtures/app/config/webpack.config.js');
        }
        if (file_exists(__DIR__ . '/../../functional/Fixtures/web/compiled')) {
            $filesystem->cleanDir(__DIR__ . '/../../functional/Fixtures/web/compiled');
        }
        if (file_exists(__DIR__ . '/../../functional/Fixtures/app/cache')) {
            $filesystem->cleanDir(__DIR__ . '/../../functional/Fixtures/app/cache');
        }
    }

    public function runCommand($commandServiceId, array $input = array())
    {
        $command = $this->grabServiceFromContainer($commandServiceId);

        $application = new Application($this->kernel);
        $application->add($command);

        $commandTester = new CommandTester($command);
        $commandTester->execute(array(
            'command' => $command->getName(),
        ) + $input, array('interactive' => false));

        $this->debug($commandTester->getDisplay());

        $this->commandTester = $commandTester;
    }

    public function runNpmCommand($command)
    {
        $process = new Process($command, __DIR__ . '/../../functional/Fixtures');
        $process->mustRun();
    }

    public function seeCommandStatusCode($code)
    {
        $this->assertEquals($code, $this->commandTester->getStatusCode());
    }

    public function seeInCommandDisplay($substring)
    {
        $this->assertContains($substring, $this->commandTester->getDisplay());
    }

    public function dontSeeInCommandDisplay($substring)
    {
        $this->assertNotContains($substring, $this->commandTester->getDisplay());
    }

    public function findAndOpenLink($css, $attribute)
    {
        $link = $this->grabAttributeFrom($css, $attribute);
        $this->assertEquals(1, preg_match('#http://localhost:8080/compiled/(.*)#', $link, $matches));

        /** @var Filesystem $filesystem */
        $filesystem = $this->getModule('Filesystem');

        $filesystem->seeFileFound(__DIR__ . '/../../functional/Fixtures/web/compiled/' . $matches[1]);
        $filesystem->openFile(__DIR__ . '/../../functional/Fixtures/web/compiled/' . $matches[1]);
    }
}
