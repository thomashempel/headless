<?php

namespace Lfda\Monkeyhead\Provider;

use Lfda\Monkeyhead\Service\MappingService;
use Lfda\Monkeyhead\Service\SelectionService;

class RecordsProvider extends BaseProvider
{

    public function fetchData()
    {
        $allowed = $this->getConfiguration('allowed', []);
        $requested_tables = $this->getArgument('tables', []);
        $records = [];

        foreach ($requested_tables as $shortname) {
            if (array_key_exists($shortname, $allowed)) {
                $table_name = $this->getConfiguration('allowed/' . $shortname);
                $mapping = $this->getConfiguration('mapping/' . $table_name, false);
                if ($mapping !== false) {
                    $selection = SelectionService::prepare($this->getConfiguration('selection/' . $table_name, []));
                    $selection['pid'] = SelectionService::make($this->getArgument('page', 0));
                    $statement = $this->fetch($table_name, array_keys($mapping), $selection);

                    $result = [];
                    while ($row = $statement->fetch()) {
                        $result[] = MappingService::transform($row, $mapping, $table_name);
                    }

                    $records[$shortname] = $result;
                }
            }
        }

        return $records;
    }

}
