<?php

namespace Maba\Bundle\WebpackMigrationBundle\Service;

use Maba\Bundle\WebpackBundle\Service\AliasManager;

class AliasPrefixer
{
    protected $aliasManager;

    public function __construct(AliasManager $aliasManager)
    {
        $this->aliasManager = $aliasManager;
    }

    public function prefixWithAlias($realPath)
    {
        $aliases = $this->aliasManager->getAliases();
        uasort($aliases, function($a, $b) {
            return strlen($b) - strlen($a);
        });

        foreach ($aliases as $alias => $aliasPath) {
            $aliasPath = realpath($aliasPath);
            if (substr($realPath, 0, strlen($aliasPath)) === $aliasPath) {
                return $alias . substr($realPath, strlen($aliasPath));
            }
        }

        throw new \RuntimeException('Did not find any suitable alias for ' . $realPath);
    }
}
