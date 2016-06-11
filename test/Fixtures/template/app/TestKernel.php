<?php

namespace Fixtures\Maba;

use Fixtures\Maba\Bundle\TestBundle\TestBundle;
use Maba\Bundle\TwigTemplateModificationBundle\MabaTwigTemplateModificationBundle;
use Maba\Bundle\WebpackBundle\MabaWebpackBundle;
use Maba\Bundle\WebpackMigrationBundle\MabaWebpackMigrationBundle;
use Symfony\Bundle\AsseticBundle\AsseticBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\MonologBundle\MonologBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Config\Loader\LoaderInterface;

class TestKernel extends Kernel
{
    public function registerBundles()
    {
        $bundles = array(
            new FrameworkBundle(),
            new TwigBundle(),
            new AsseticBundle(),
            new MonologBundle(),
            new MabaTwigTemplateModificationBundle(),
            new MabaWebpackBundle(),
            new MabaWebpackMigrationBundle(),
            new TestBundle(),
        );
        return $bundles;
    }

    public function registerContainerConfiguration(LoaderInterface $loader)
    {
        $loader->load(__DIR__ . '/config/config.yml');
    }
}
