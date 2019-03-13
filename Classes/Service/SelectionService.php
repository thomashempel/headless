<?php

namespace Lfda\Headless\Service;


class SelectionService
{

    public static function prepare(array $configuration)
    {
        $res = [];
        foreach ($configuration as $key => $config) {
            $res[$key] = SelectionService::make($config['value'], $config['type']);
        }

        return $res;
    }

    public static function make($value, $type = 'int')
    {
        switch ($type) {
            case 'string':
                return ['value' => $value, 'type' => \PDO::PARAM_STR];

            case 'bool':
                return ['value' => boolval($value), 'type' => \PDO::PARAM_BOOL];

            case 'int':
            default:
                return ['value' => intval($value), 'type' => \PDO::PARAM_INT];
        }
    }

}
