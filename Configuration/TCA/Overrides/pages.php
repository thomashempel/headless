<?php

call_user_func(
    function ($extKey, $table) {
        $storageDoktype = 142;

        // Add new page type as possible select item:
        \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTcaSelectItem(
            $table,
            'doktype',
            [
                'LLL:EXT:' . $extKey . '/Resources/Private/Language/locallang.xlf:storage_page_type',
                $storageDoktype,
                'EXT:' . $extKey . 'Resources/Public/Images/Storage.svg'
            ],
            '1',
            'after'
        );

        // Add icon for new page type:
        \TYPO3\CMS\Core\Utility\ArrayUtility::mergeRecursiveWithOverrule(
            $GLOBALS['TCA']['pages'],
            [
                'ctrl' => [
                    'typeicon_classes' => [
                        $storageDoktype => 'apps-pagetree-storage',
                    ],
                ],
            ]
        );
    },
    'headless',
    'pages'
);