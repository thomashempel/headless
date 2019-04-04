<?php

namespace Lfda\Monkeyhead\Provider;

use Doctrine\DBAL\Driver\Mysqli\MysqliStatement;
use Lfda\Monkeyhead\Service\MappingService;
use Lfda\Monkeyhead\Service\SelectionService;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class RecordsProvider extends BaseProvider
{

    public function fetchData()
    {
        $allowed = $this->getConfiguration('allowed', []);
        $requested_tables = $this->getArgument('tables', []);
        $requested_id = $this->getArgument('record_id', 0);
        $records = [];

        foreach ($requested_tables as $shortname) {
            if (!array_key_exists($shortname, $allowed)) {
                $records[$shortname] = 'Not allowed';
                continue;
            }


            $table_name = $this->getConfiguration('allowed/' . $shortname);
            $mapping = $this->getConfiguration('mapping/' . $table_name, false);
            if ($mapping === false) {
                $records[$shortname] = 'No mapping';
                continue;
            }

            $mapped_result = [];

            $provider_class = $this->getConfiguration('adaptors/' . $table_name . '/provider');

            if ($provider_class && $provider = GeneralUtility::makeInstance($provider_class)) {
                if ($requested_id > 0) {
                    $provider->setArgument('uid', $requested_id);
                }
                $provided_result = $provider->fetchData();
            } else {
                $selection = SelectionService::prepare($this->getConfiguration('selection/' . $table_name, []));
                $selection['pid'] = SelectionService::make($this->getArgument('page', 0));
                if ($requested_id > 0) {
                    $selection['uid'] = SelectionService::make($requested_id);
                }
                $provided_result = $this->fetch($table_name, array_keys($mapping), $selection);
            }

            if (get_class($provided_result) == MysqliStatement::class) {
                while ($row = $provided_result->fetch()) {
                    $mapped_result[] = MappingService::transform($row, $mapping, $table_name);
                }

            } else {
                if ($requested_id > 0) {
                    $mapped_result[] = MappingService::transform($provided_result, $mapping, $table_name);
                } else {
                    foreach ($provided_result as $record) {
                        $mapped_result[] = MappingService::transform($record, $mapping, $table_name);
                    }
                }
            }

            $records[$shortname] = $mapped_result;
        }

        return $records;
    }

}
