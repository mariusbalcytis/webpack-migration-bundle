<?php

namespace Maba\Bundle\WebpackMigrationBundle;

use Maba\Bundle\WebpackMigrationBundle\DependencyInjection\AsseticNamedAssetsCompilerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class MabaWebpackMigrationBundle extends Bundle
{
    public function build(ContainerBuilder $container)
    {
        $container->addCompilerPass(new AsseticNamedAssetsCompilerPass());
    }
}
