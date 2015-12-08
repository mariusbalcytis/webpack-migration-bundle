<?php

namespace Maba\Bundle\WebpackMigrationBundle\Twig;

use Twig_NodeVisitorInterface as NodeVisitorInterface;

class WebpackAsseticExtension extends \Twig_Extension
{
    protected $webpackAsseticNodeVisitor;

    public function __construct(NodeVisitorInterface $webpackAsseticNodeVisitor)
    {
        $this->webpackAsseticNodeVisitor = $webpackAsseticNodeVisitor;
    }

    public function getName()
    {
        return 'webpack_assetic_migration';
    }

    public function getNodeVisitors()
    {
        return array(
            $this->webpackAsseticNodeVisitor,
        );
    }
}
