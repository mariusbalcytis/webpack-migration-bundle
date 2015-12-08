<?php

use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Config\Loader\LoaderInterface;

class TestKernel extends Kernel
{
    public function registerBundles()
    {
        $bundles = array(
            new Symfony\Bundle\FrameworkBundle\FrameworkBundle(),
            new Symfony\Bundle\TwigBundle\TwigBundle(),
            new Symfony\Bundle\AsseticBundle\AsseticBundle(),
            new Symfony\Bundle\MonologBundle\MonologBundle(),
            new Maba\Bundle\WebpackBundle\MabaWebpackBundle(),
            new Maba\Bundle\WebpackMigrationBundle\MabaWebpackMigrationBundle(),
            new Fixtures\Maba\Bundle\WebpackMigrationTestBundle\MabaWebpackMigrationTestBundle(),
        );
        return $bundles;
    }

    public function registerContainerConfiguration(LoaderInterface $loader)
    {
        $loader->load(__DIR__ . '/config/config.yml');
    }
}
