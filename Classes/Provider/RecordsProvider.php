<?php

namespace T12\Monkeyhead\Provider;

use Doctrine\DBAL\Driver\Mysqli\MysqliStatement;
use T12\Monkeyhead\Service\MappingService;
use T12\Monkeyhead\Service\SelectionService;
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

            /** @var BaseProvider $provider_class */
            $provider_setup = $this->getConfiguration('adaptors/' . $table_name . '/provider');
            $provider_options = [];
            if (isset($provider_setup['_typoScriptNodeValue'])) {
                $provider_class = $provider_setup['_typoScriptNodeValue'];
                $provider_options = $provider_setup['options'];
            } else {
                $provider_class = $provider_setup;
            }

            if ($provider_class && $provider = GeneralUtility::makeInstance($provider_class)) {
                $provider->setArgument('lookup_property', $provider_options['lookup_property']);
                $provider->setArgument('lookup_value', $this->getArgument($provider_options['lookup_argument']));
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
                foreach ($provided_result as $record) {
                    $mapped_result[] = MappingService::transform($record, $mapping, $table_name);
                }
            }

            $records[$shortname] = $mapped_result;
        }

        return $records;
    }

}
