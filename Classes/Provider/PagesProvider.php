<?php

namespace T12\Monkeyhead\Provider;

use T12\Monkeyhead\Service\MappingService;
use T12\Monkeyhead\Service\SelectionService;
use TYPO3\CMS\Core\Utility\GeneralUtility;

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
        $data = $this->fetch(
            $this->table,
            array_keys($this->getConfiguration('mapping')),
            $selection,
            $this->getConfiguration('options/order_by')
        );
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

    public function getLanguageInformation($page_data)
    {
        $current_language = $GLOBALS['TYPO3_REQUEST']->getAttribute('language')->toArray();
        $site = $GLOBALS['TYPO3_REQUEST']->getAttribute('site');
        $configured_languages = $site->getLanguages();
        $available = [];

        foreach ($configured_languages as $configured_language) {
            $data = $configured_language->toArray();

            if ($data['languageId'] != $current_language['languageId']) {
                $page = $this->fetch($this->table, ['uid'], [
                    'sys_language_uid' => SelectionService::make($data['languageId']),
                    'l10n_parent' => SelectionService::make($page_data['uid'])
                ])->fetch();

                if ($page !== false) {
                    $available[] = $data;
                }

            } else {
                $available[] = $data;
            }
        }

        $information = [
            'current' => $current_language,
            'available' => $available
        ];

        return $information;
    }

}
