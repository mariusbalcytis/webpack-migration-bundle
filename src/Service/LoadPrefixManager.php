<?php

namespace Maba\Bundle\WebpackMigrationBundle\Service;

class LoadPrefixManager
{

    public function getPrefix($filePath)
    {
        if (substr($filePath, -3) !== '.js') {
            return '';
        }

        $source = file_get_contents($filePath);

        $prefixes = array();

        $globalScopeParts = array();
        if (preg_match('/^.*?function\s*\([^{]*/', $source, $matches) === 1) {
            $globalScopeParts[] = $matches[0];
        }
        if (preg_match('/[^}]+$/', $source, $matches) === 1) {
            $globalScopeParts[] = $matches[0];
        }
        $globalScopeScript = implode("\n", $globalScopeParts);

        if (strpos($globalScopeScript, 'this') !== false) {
            $prefixes[] = 'imports?this=>window!';
        }

        if (preg_match('/[^a-z0-9]define[^a-z0-9]/i', $source) === 1) {
            $prefixes[] = 'imports?define=>false!';
        }

        if (
            preg_match('/[^a-z0-9_]module[^a-z0-9_]/', $source) === 1
            && preg_match('/\.exports[^a-z0-9_]/', $source) === 1
        ) {
            $prefixes[] = 'imports?module=>false!';
        }


        if (preg_match_all('/var ([a-z_][a-z_0-9])\s*=/i', $globalScopeScript, $matches)) {
            $variableNames = $matches[1];
            foreach ($variableNames as $variableName) {
                $prefixes[] = 'expose?' . $variableName . '!exports?' . $variableName . '!';
            }
        }

        return implode('', $prefixes);
    }
}
