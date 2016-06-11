<?php

namespace Maba\Bundle\WebpackMigrationBundle\Service;

use Symfony\Component\HttpKernel\KernelInterface;

class AssetFileDumper
{
    private $aliasPrefixer;
    private $rootDir;
    private $kernel;
    private $loadPrefixManager;

    public function __construct(
        AliasPrefixer $aliasPrefixer,
        $rootDir,
        KernelInterface $kernel,
        LoadPrefixManager $loadPrefixManager
    ) {
        $this->aliasPrefixer = $aliasPrefixer;
        $this->rootDir = $rootDir;
        $this->kernel = $kernel;
        $this->loadPrefixManager = $loadPrefixManager;
    }

    public function dump($filePath, array $inputs)
    {
        $assets = array();
        foreach ($inputs as $input) {
            $assets = array_merge($assets, $this->resolveInput($input));
        }
        $lines = array_map(function($asset) {
            return 'require(' . str_replace('\\/', '/', json_encode($asset)) . ');';
        }, $assets);
        $content = implode("\n", $lines);

        $dirname = dirname($filePath);
        if (!is_dir($dirname)) {
            mkdir($dirname, 0777, true);
        }

        file_put_contents($filePath, $content);
    }

    /**
     * This function is merged from Assetic and AsseticBundle and modified to suit specific needs for assets on FS
     *
     * @param string $input as provided in assetic
     *
     * @return array webpack-compatible paths to assets
     */
    protected function resolveInput($input)
    {
        $rootDir = rtrim($this->rootDir, '/');

        // expand bundle notation
        if ('@' == $input[0] && strpos($input, '/') !== false) {
            // use the bundle path as this asset's root
            $bundle = substr($input, 1);
            $pos = strpos($bundle, '/');
            if ($pos !== false) {
                $bundle = substr($bundle, 0, $pos);
            }
            $rootDir = array($this->kernel->getBundle($bundle)->getPath());

            // canonicalize the input
            $pos = strpos($input, '*');
            if ($pos !== false) {
                // locateResource() does not support globs so we provide a naive implementation here
                list($before, $after) = explode('*', $input, 2);
                $input = $this->kernel->locateResource($before) . '*' . $after;
            } else {
                $input = $this->kernel->locateResource($input);
            }
        }

        if ('@' == $input[0]) {
            return array('@app/' . substr($input, 1) . '.js');
        }

        if (false !== strpos($input, '://') || 0 === strpos($input, '//')) {
            return array($input);
        }

        if ($input[0] !== '/') {
            $input = $rootDir . '/' . $input;
        }

        if (strpos($input, '*') !== false) {
            $result = array();
            foreach (glob($input) as $path) {
                if (is_dir($path)) {
                    continue;
                }
                $result[] = $this->handleFileAsset($path);
            }
            return $result;
        }

        return array($this->handleFileAsset($input));
    }

    protected function handleFileAsset($path)
    {
        $realPath = realpath($path);
        if ($realPath === false) {
            throw new \RuntimeException(sprintf('File not found: %s', $path));
        }
        return $this->loadPrefixManager->getPrefix($realPath) . $this->aliasPrefixer->prefixWithAlias($realPath);
    }
}
