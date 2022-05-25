<?php

namespace T12\Monkeyhead\Service;


use TYPO3\CMS\Core\Utility\GeneralUtility;

class SelectionService
{

    public static function prepare(array $configuration)
    {
        $res = [];
        foreach ($configuration as $key => $config) {
            $res[$key] = SelectionService::make($config['value'], $config['type'], $config['method'] ?: 'eq');
        }

        return $res;
    }

    public static function make($value, $type = 'int', $method = 'eq')
    {

        switch ($type) {
            case 'string':
                $value = ($method == 'in') ? GeneralUtility::trimExplode(',', $value) : $value;
                return ['value' => $value, 'type' => \PDO::PARAM_STR, 'method' => $method];

            case 'bool':
                return ['value' => boolval($value), 'type' => \PDO::PARAM_BOOL, 'method' => $method];

            case 'int':
            default:
                $value = ($method == 'in') ? GeneralUtility::intExplode(',', $value) : intval($value);
                return ['value' => $value, 'type' => \PDO::PARAM_INT, 'method' => $method];
        }
    }

}
