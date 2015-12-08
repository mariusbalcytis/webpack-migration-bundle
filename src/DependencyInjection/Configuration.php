<?php

namespace Maba\Bundle\WebpackMigrationBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * This is the class that validates and merges configuration from your app/config files
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html#cookbook-bundles-extension-config-class}
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('maba_webpack_migration');

        $rootNode->children()->scalarNode('loader_prefix')->defaultValue(
            'imports?this=>window!imports?define=>false!imports?module=>false!'
        );

        return $treeBuilder;
    }
}
