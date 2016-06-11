<?php

namespace Maba\Bundle\WebpackMigrationBundle\Service;

use RuntimeException;

class NamedAssetsDumper
{
    private $namedAssets;
    private $assetFileDumper;
    private $additionalAliases;

    public function __construct(
        AssetFileDumper $assetFileDumper,
        array $namedAssets,
        array $additionalAliases
    ) {
        $this->namedAssets = $namedAssets;
        $this->assetFileDumper = $assetFileDumper;
        $this->additionalAliases = $additionalAliases;
    }

    public function dump()
    {
        // we take alias from parameters as AliasManager requires for alias directory to exist
        $appAliasPath = $this->additionalAliases['app'];

        if (!file_exists($appAliasPath)) {
            mkdir($appAliasPath, 0777, true);
        }
        if (!is_dir($appAliasPath)) {
            throw new RuntimeException(sprintf('@app alias path is not a directory: %s', $appAliasPath));
        }

        $path = realpath($appAliasPath) . '/';

        $result = array();
        foreach ($this->namedAssets as $assetName => $inputs) {
            $filePath = $path . $assetName . '.js';
            $this->assetFileDumper->dump($filePath, $inputs);
            $result[] = $filePath;
        }
        return $result;
    }
}
