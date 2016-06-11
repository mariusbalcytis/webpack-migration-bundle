<?php

namespace Maba\Bundle\WebpackMigrationBundle\Service;

use Assetic\Asset\AssetCollectionInterface;
use Assetic\Asset\AssetInterface;
use Assetic\Util\VarUtils;
use Maba\Bundle\TwigTemplateModificationBundle\Entity\TemplateContext;
use Maba\Bundle\TwigTemplateModificationBundle\Service\NodeReplaceHelper;
use Maba\Bundle\TwigTemplateModificationBundle\Service\TwigNodeReplacerInterface;
use Symfony\Bundle\AsseticBundle\Twig\AsseticNode;
use Twig_Node as Node;
use Twig_Node_Expression_Name as NameExpression;

class AsseticNodeReplacer implements TwigNodeReplacerInterface
{
    const ATTRIBUTE_NODE_COUNT = 'assetic_node_count';

    protected $nodeReplaceHelper;
    protected $aliasPrefixer;
    protected $possibleVariables;
    protected $twigFunctionName;
    protected $assetFileDumper;
    protected $pathInBundle;
    protected $ignoredFilters;

    public function __construct(
        NodeReplaceHelper $nodeReplaceHelper,
        AliasPrefixer $aliasPrefixer,
        array $possibleVariables,
        $twigFunctionName,
        AssetFileDumper $assetFileDumper,
        $pathInBundle,
        array $ignoredFilters
    ) {
        $this->nodeReplaceHelper = $nodeReplaceHelper;
        $this->aliasPrefixer = $aliasPrefixer;
        $this->possibleVariables = $possibleVariables;
        $this->twigFunctionName = $twigFunctionName;
        $this->assetFileDumper = $assetFileDumper;
        $this->pathInBundle = $pathInBundle;
        $this->ignoredFilters = $ignoredFilters;
    }

    public function replace(Node $node, TemplateContext $context)
    {
        // we only overwrite assetic nodes
        if (!$node instanceof AsseticNode) {
            return null;
        }

        // we leave images as they are
        $tagName = $node->getNodeTag();
        if (!in_array($tagName, array('stylesheets', 'javascripts'), true)) {
            return null;
        }

        // we only handle collection of file assets
        $asset = $node->getAttribute('asset');
        if (!$asset instanceof AssetCollectionInterface) {
            throw new \RuntimeException('Unexpected asset type: ' . get_class($asset));
        }

        // skip assetic nodes that has some (not-ignored) filters defined
        $additionalFilters = array_diff($node->getAttribute('filters'), $this->ignoredFilters);
        if (count($additionalFilters) > 0) {
            $context->addNotice(sprintf(
                'Skipping %s in %s as filter is used (%s). Remove filter and re-run if you want to migrate this tag',
                $tagName,
                $context->getTemplateName(),
                implode(' ', $additionalFilters)
            ));
            return null;
        }

        $count = $context->getAttribute(self::ATTRIBUTE_NODE_COUNT);
        $count = $count !== null ? $count : 0;
        $context->setAttribute(self::ATTRIBUTE_NODE_COUNT, $count + 1);
        $postfix = $count === 0 ? '' : '-' . $count;

        $templateName = $context->getTemplateName();
        $assetPath = str_replace('/Resources/views/', '/' . $this->pathInBundle . '/', $templateName);
        $assetPath = preg_replace('/(\.html)?\.twig$/', '', $assetPath) . $postfix . '.js';

        $variables = $asset->getVars();
        if (count($variables) === 0) {
            $expression = $this->dumpAssets($node, $asset, $tagName, $assetPath);

        } else {
            $combinations = VarUtils::getCombinations($variables, $this->possibleVariables);
            $expression = null;
            foreach ($combinations as $combination) {
                $combinationAssetPath = substr($assetPath, 0, -2) . implode('.', $combination) . '.js';
                if ($expression === null) {
                    // first combination will be in "else" part (if no other combinations will match)
                    $expression = $this->dumpAssets($node, $asset, $tagName, $combinationAssetPath, $combination);
                } else {
                    // we recursively build condition like this:
                    //     matchesCombination ? webpack_asset(combinationPath) : [recursion]
                    $expression = $this->buildCombinationCondition($combination) . "\n"
                        . '? ' . $this->dumpAssets($node, $asset, $tagName, $combinationAssetPath, $combination) . "\n"
                        . ': (' . "\n" . $this->indent($expression) . "\n" . ')';
                }
            }
        }

        $body = $node->getNode('body');

        // we find and replace "asset_url" to our expression
        return $this->replaceAssetUrl($body, $body, $expression);
    }

    protected function dumpAssets(
        Node $node,
        AssetCollectionInterface $asset,
        $tagName,
        $assetPath,
        array $combination = array()
    ) {
        $inputs = $this->collectInputs(
            $asset->all(),
            $combination,
            $node->getAttribute('inputs')
        );

        $this->assetFileDumper->dump($assetPath, $inputs);
        $aliasedAssetPath = $this->aliasPrefixer->prefixWithAlias($assetPath);

        $arguments = array(str_replace('\\/', '/', json_encode($aliasedAssetPath)));
        if ($tagName === 'stylesheets') {
            $arguments[] = json_encode('css');
        }

        return $this->twigFunctionName . '(' . implode(', ', $arguments) . ')';
    }

    /**
     * @param AssetInterface[] $assets
     * @param array $combination
     * @param array|null $nodeInputs
     * @return array
     */
    protected function collectInputs(array $assets, array $combination, array $nodeInputs = null)
    {
        $inputs = array();

        foreach ($assets as $index => $asset) {
            if ($asset instanceof AssetCollectionInterface) {
                $inputs = array_merge($inputs, $this->collectInputs($asset->all(), $combination));
            } else {
                $sourcePath = $asset->getSourcePath();

                if ($sourcePath === null) {
                    if ($nodeInputs === null || !isset($nodeInputs[$index])) {
                        throw new \RuntimeException('Source not set in asset and cannot be guessed');
                    }
                    $assetPath = $nodeInputs[$index];
                } else {
                    $sourcePath = VarUtils::resolve($sourcePath, array_keys($combination), $combination);
                    $assetPath = $asset->getSourceRoot() . DIRECTORY_SEPARATOR . $sourcePath;
                }

                $inputs[] = $assetPath;
            }
        }

        return $inputs;
    }

    /**
     * Searches and replaces "asset_url" variable with given expression inside body
     *
     * @param Node $parent
     * @param Node $body
     * @param string $expression
     * @return null|string
     */
    protected function replaceAssetUrl(Node $parent, Node $body, $expression)
    {
        foreach ($parent as $index => $node) {
            if ($node instanceof NameExpression && $node->getAttribute('name') === 'asset_url') {
                return $this->nodeReplaceHelper->getReplacedSource($body, $node, $expression);
            } else {
                $replaced = $this->replaceAssetUrl($node, $body, $expression);
                if ($replaced !== null) {
                    return $replaced;
                }
            }
        }

        return null;
    }

    protected function buildCombinationCondition(array $combination)
    {
        $conditions = array();
        foreach ($combination as $variableName => $value) {
            $conditions[] = sprintf('assetic.vars[%s] == %s', json_encode($variableName), json_encode($value));
        }
        return implode(' and ', $conditions);
    }

    protected function indent($text)
    {
        return '    ' . str_replace("\n", "\n    ", $text);
    }
}
