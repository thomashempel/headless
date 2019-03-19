<?php

call_user_func(
    function ($extKey, $table) {
        $archiveDoktype = 142;

        // Add new page type as possible select item:
        \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTcaSelectItem(
            $table,
            'doktype',
            [
                'LLL:EXT:' . $extKey . '/Resources/Private/Language/locallang.xlf:storage_page_type',
                $archiveDoktype,
                'EXT:' . $extKey . 'Resources/Public/Images/Storage.svg'
            ],
            '1',
            'after'
        );
    },
    'monkeyhead',
    'pages_language_overlay'
);