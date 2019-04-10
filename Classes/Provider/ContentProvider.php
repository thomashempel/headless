<?php

namespace Lfda\Monkeyhead\Provider;

use Lfda\Monkeyhead\Service\MappingService;
use Lfda\Monkeyhead\Service\SelectionService;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class ContentProvider extends BaseProvider
{

    public function __construct()
    {
        $this->table = 'tt_content';
    }

    public function fetchData()
    {
        $selection = SelectionService::prepare($this->getConfiguration('selection/defaults', []));
        $selection['pid'] = SelectionService::make($this->getArgument('page', 0));
        $language = intval($this->getArgument('language', 0));
        if ($language > 0) {
            $selection['sys_language_uid'] = SelectionService::make($language);
        }
        $order_by = $this->getConfiguration('options/order_by', 'sorting');

        $contents = $this->fetch($this->table, array_keys($this->getConfiguration('mapping')), $selection, $order_by);
        $mapping = $this->getConfiguration('mapping', []);
        $render_configs = $this->getConfiguration('rendering', []);
        $group_by = $this->getConfiguration('options/group_by', false);
        $groups = GeneralUtility::trimExplode(',', $this->getConfiguration('options/groups', ''));

        $result = [];

        foreach ($groups as $group) {
            $result[$group] = [];
        }

        while ($row = $contents->fetch()) {
            $transformed = MappingService::transform($row, $mapping, $this->table);

            // rendering
            foreach ($render_configs as $target_key => $config) {
                if ($this->matches($config, $transformed)) {
                    try {
                        $renderer = GeneralUtility::makeInstance($config['renderer']);
                        $transformed[$target_key] = $renderer->execute($transformed, $config['options']);
                    } catch (Exception $exception) {
                        $transformed[$target_key] = 'Can\'t find renderer!!!';
                    }
                }
            }

            if ($group_by && array_key_exists($group_by, $transformed)) {
                $key = $transformed[$group_by];
                if (!isset($result[$key])) {
                    $result[$key] = [];
                }
                $result[$key][] = $transformed;
            } else {
                $result[] = $transformed;
            }

        }

        return $result;

    }

    protected function matches($config, $row)
    {
        foreach ($config['matching'] as $key => $value) {
            if (!array_key_exists($key, $row) || $row[$key] != $value) {
                return false;
            }
        }
        return true;
    }

}
