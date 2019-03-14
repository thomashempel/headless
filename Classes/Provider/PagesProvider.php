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
        return $this->fetchSubPages($this->getArgument('root', 0));
    }

    public function fetchPageData($uid)
    {
        $selection = SelectionService::prepare($this->getConfiguration('selection/defaults', []));
        $selection['uid'] = SelectionService::make($uid);

        return $this->fetch_page_data($selection);
    }

    public function fetchSubPages($pid)
    {
        $selection = SelectionService::prepare($this->getConfiguration('selection/defaults', []));
        $selection['pid'] = SelectionService::make($pid);
        $iteration = $this->getArgument('recursive', 1);

        return $this->fetch_page_data($selection, $iteration);
    }

    protected function fetch_page_data($selection, $iteration = 1)
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
