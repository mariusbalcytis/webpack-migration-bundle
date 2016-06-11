<?php

namespace Maba\Bundle\WebpackMigrationBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class AsseticNamedAssetsCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        $configs = $container->getExtensionConfig('assetic');
        $assets = array();
        foreach ($configs as $config) {
            if (isset($config['assets'])) {
                $assets = array_merge($assets, $config['assets']);
            }
        }

        $assets = $container->getParameterBag()->resolveValue($assets);

        $assetInputs = array();
        foreach ($assets as $assetName => $assetConfig) {
            if (isset($assetConfig['inputs']) && count($assetConfig['inputs']) > 0) {
                $assetInputs[$assetName] = $assetConfig['inputs'];
            }
        }

        $container->setParameter('maba_webpack_migration.assetic.named_assets', $assetInputs);
    }
}
