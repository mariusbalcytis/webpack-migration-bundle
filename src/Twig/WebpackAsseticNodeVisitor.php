<?php

namespace Maba\Bundle\WebpackMigrationBundle\Twig;

use Assetic\Asset\AssetCollectionInterface;
use Assetic\Asset\AssetInterface;
use Assetic\Util\VarUtils;
use Symfony\Bundle\AsseticBundle\Twig\AsseticNode;
use Twig_BaseNodeVisitor as NodeVisitor;
use Twig_Node as Node;
use Twig_Node_Expression as Expression;
use Twig_Environment as Environment;
use Twig_Node_Expression_Function as FunctionExpression;
use Twig_Node_Expression_Constant as ConstantExpression;
use Twig_Node_Expression_Name as NameExpression;
use Twig_Node_Expression_Conditional as ConditionalExpression;
use Twig_Node_Expression_GetAttr as GetAttrExpression;
use Twig_Node_Expression_Array as ArrayExpression;
use Twig_Template as Template;
use Twig_Node_Expression_Binary_And as AndExpression;
use Twig_Node_Expression_Binary_Equal as EqualExpression;
use Twig_Node_If as IfNode;

class WebpackAsseticNodeVisitor extends NodeVisitor
{
    protected $targetDir;
    protected $possibleVariables;
    protected $twigFunctionName;
    protected $loaderPrefix;

    public function __construct($targetDir, array $possibleVariables, $twigFunctionName, $loaderPrefix)
    {
        $this->targetDir = $targetDir;
        $this->possibleVariables = $possibleVariables;
        $this->twigFunctionName = $twigFunctionName;
        $this->loaderPrefix = $loaderPrefix;
    }

    public function doEnterNode(Node $node, Environment $env)
    {
        // we only overwrite assetic nodes
        if (!$node instanceof AsseticNode) {
            return $node;
        }
        $lineNumber = $node->getLine();

        // we leave images as they are
        $tagName = $node->getNodeTag();
        if (!in_array($tagName, array('stylesheets', 'javascripts'), true)) {
            return $node;
        }

        // we only handle collection of file assets
        $asset = $node->getAttribute('asset');
        if (!$asset instanceof AssetCollectionInterface) {
            throw new \RuntimeException('Unexpected asset type: ' . get_class($asset));
        }

        $variables = $asset->getVars();
        if (count($variables) === 0) {
            $urlExpression = $this->dumpAssets($asset, $tagName, $lineNumber);

        } else {
            $combinations = VarUtils::getCombinations($variables, $this->possibleVariables);
            $urlExpression = null;
            foreach ($combinations as $combination) {
                if ($urlExpression === null) {
                    // first combination will be in "else" part (if no other combinations will match)
                    $urlExpression = $this->dumpAssets($asset, $tagName, $lineNumber, $combination);
                } else {
                    // we recursively build condition like this:
                    //     matchesCombination ? webpack_asset(combinationPath) : [recursion]
                    $urlExpression = new ConditionalExpression(
                        $this->buildCombinationCondition($combination, $lineNumber),
                        $this->dumpAssets($asset, $tagName, $lineNumber, $combination),
                        $urlExpression,
                        $lineNumber
                    );
                }
            }
        }

        $body = $node->getNode('body');

        // we find and replace "asset_url" to our expression
        $this->replaceAssetUrl(
            $body,
            $urlExpression
        );

        // if URL is empty (usually only for css assets) - do not output body at all
        return new IfNode(new Node(array(
            new EqualExpression(
                $urlExpression,
                new ConstantExpression('', $lineNumber),
                $lineNumber
            ),
            new Node(array())
        )), $body, $lineNumber);
    }

    public function doLeaveNode(Node $node, Environment $env)
    {
        return $node;
    }


    public function getPriority()
    {
        return 0;
    }

    /**
     * Searches and replaces "asset_url" variable with given expression inside body
     *
     * @param Node $body
     * @param Node $expression
     */
    protected function replaceAssetUrl(Node $body, Node $expression)
    {
        foreach ($body as $index => $node) {
            if ($node instanceof NameExpression && $node->getAttribute('name') === 'asset_url') {
                $body->setNode($index, $expression);
            } else {
                $this->replaceAssetUrl($node, $expression);
            }
        }
    }

    /**
     * Makes JS file from all assets by generating require(path) statements
     * Returns webpack_asset function expression with file path of created file
     *
     * @param AssetCollectionInterface $asset
     * @param string $tagName javascripts or stylesheets
     * @param int $lineNumber
     * @param array $combination
     *
     * @return FunctionExpression
     */
    protected function dumpAssets(AssetCollectionInterface $asset, $tagName, $lineNumber, array $combination = array())
    {
        if (!is_dir($this->targetDir)) {
            mkdir($this->targetDir, 0777, true);
        }

        $content = implode("\n", $this->collectInputs($asset, $tagName, $combination));

        $fileName = 'collection-' . sha1($content) . '.js';
        $filePath = $this->targetDir . $fileName;
        file_put_contents($filePath, $content);

        return new FunctionExpression(
            $this->twigFunctionName,    // webpack_asset by default
            new Node(array(
                new ConstantExpression($filePath, $lineNumber),
                new ConstantExpression($tagName === 'stylesheets' ? 'css' : 'js', $lineNumber)
            )),
            $lineNumber
        );
    }

    protected function collectInputs(AssetInterface $asset, $tagName, array $combination)
    {
        $inputs = array();
        if ($asset instanceof AssetCollectionInterface && count($asset->getFilters()) === 0) {
            foreach ($asset->all() as $fileAsset) {
                $inputs = array_merge($inputs, $this->collectInputs($fileAsset, $tagName, $combination));
            }

        } else {
            $sourcePath = $asset->getSourcePath();

            if ($sourcePath === null) {
                $content = $asset->dump();
                $assetPath = $this->targetDir . 'assetic-asset-' . sha1($content)
                    . '.' . ($tagName === 'stylesheets' ? 'css' : 'js');
                file_put_contents($assetPath, $content);

            } else {
                $sourcePath = VarUtils::resolve($sourcePath, array_keys($combination), $combination);
                $assetPath = $asset->getSourceRoot() . DIRECTORY_SEPARATOR . $sourcePath;
            }

            if (substr($assetPath, -3) === '.js') {
                $assetPath = $this->loaderPrefix . $assetPath;  // usually "script!uglify!"
            }

            $inputs[] = 'require(' . json_encode($assetPath) . ');';
        }

        return $inputs;
    }

    /**
     * Builds condition expression to match given assetic variable combination
     *
     * @param array $combination
     * @param int $lineNumber
     *
     * @return Expression
     */
    protected function buildCombinationCondition(array $combination, $lineNumber)
    {
        $condition = null;
        foreach ($combination as $variableName => $value) {
            // retrieves value of assetic variable from the context, $context['assetic']['vars'][$var]
            // compares if equal to given value of combination variable
            $itemCondition = new \Twig_Node_Expression_Binary_Equal(
                new GetAttrExpression(
                    new GetAttrExpression(
                        new NameExpression('assetic', $lineNumber),
                        new ConstantExpression('vars', $lineNumber),
                        new ArrayExpression(array(), $lineNumber),
                        Template::ARRAY_CALL,
                        $lineNumber
                    ),
                    new ConstantExpression($variableName, $lineNumber),
                    new ArrayExpression(array(), $lineNumber),
                    Template::ARRAY_CALL,
                    $lineNumber
                ),
                new ConstantExpression($value, $lineNumber),
                $lineNumber
            );

            // matches only if all variables match current combination - join all with AND
            if ($condition === null) {
                $condition = $itemCondition;
            } else {
                $condition = new AndExpression($condition, $itemCondition, $lineNumber);
            }
        }
        return $condition;
    }
}
