<?php

namespace Maba\Tests;

use Composer\Autoload\ClassLoader;
use Maba\Bundle\WebpackMigrationBundle\Service\NamedAssetsDumper;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Maba\Bundle\TwigTemplateModificationBundle\Service\FilesReplacer;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use SplFileInfo;

class FunctionalTest extends KernelTestCase
{
    protected static function getKernelClass()
    {
        return 'Fixtures\Maba\TestKernel';
    }

    protected function setUp()
    {
        $filesystem = new Filesystem();
        $filesystem->remove(__DIR__ . '/Fixtures/tmp');

        $filesystem->mirror(__DIR__ . '/Fixtures/template', __DIR__ . '/Fixtures/tmp');

        $loader = new ClassLoader();
        $loader->addPsr4('Fixtures\\Maba\\Bundle\\', __DIR__ . '/Fixtures/tmp/src');
        $loader->addPsr4('Fixtures\\Maba\\', __DIR__ . '/Fixtures/tmp/app');
        $loader->register(true);

        static::bootKernel();
    }
    
    protected function tearDown()
    {
        $filesystem = new Filesystem();
        $filesystem->remove(__DIR__ . '/Fixtures/tmp');
    }

    public function testReplacing()
    {
        $container = static::$kernel->getContainer();
        /** @var NamedAssetsDumper $dumper */
        $dumper = $container->get('maba_webpack_migration.named_assets_dumper');
        /** @var FilesReplacer $replacer */
        $replacer = $container->get('maba_webpack_migration.twig_files_replacer');

        $this->assertCount(2, $dumper->dump());
        $replacer->replace();

        $this->assertSameContents('expected');
    }

    private function assertSameContents($expectedDir)
    {
        $expectedDir = realpath(__DIR__ . '/Fixtures/' . $expectedDir);
        $realDir = realpath(__DIR__ . '/Fixtures/tmp');

        /** @var Finder|SplFileInfo[] $finder */
        $finder = new Finder();
        $finder->in($expectedDir)->files();

        foreach ($finder as $fileInfo) {
            $this->assertFileEquals(
                $fileInfo->getRealPath(),
                $realDir . substr($fileInfo->getRealPath(), strlen($expectedDir))
            );
        }
    }
}
