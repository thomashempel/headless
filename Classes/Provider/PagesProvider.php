<?php

namespace Lfda\Headless\Provider;

use Lfda\Headless\Service\MappingService;
use Lfda\Headless\Service\SelectionService;

class PagesProvider extends BaseProvider
{

    public function __construct()
    {
        $this->table = 'pages';
    }

    public function fetchData()
    {
        $selection = SelectionService::prepare($this->getConfiguration('selection/defaults', []));
        $selection['pid'] = SelectionService::make($this->getArgument('root', 0));
        $iteration = $this->getArgument('recursive', 1);

        return $this->fetch_page_data($selection, $iteration);
    }

    protected function fetch_page_data($selection, $iteration)
    {
        $data = $this->fetch($this->table, array_keys($this->getConfiguration('mapping')), $selection);
        $mapping = $this->getConfiguration('mapping');

        $pages = [];

        while ($row = $data->fetch()) {
            $page = MappingService::transform($row, $mapping, $this->table);

            if ($iteration > 1) {
                $selection['pid'] = SelectionService::make($page['uid']);
                $children = $this->fetch_page_data($selection, --$iteration);
                if (count($children) > 0) {
                    $page['__children'] = $this->fetch_page_data($selection, --$iteration);
                }
            }

            $pages[] = $page;
        }

        return $pages;
    }

}
