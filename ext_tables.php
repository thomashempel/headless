<?php

call_user_func(

    function($extKey)
    {
        \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addStaticFile($extKey, 'Configuration/TypoScript', 'Headless');

        $storageDoktype = 142;

        // Add new page type:
        $GLOBALS['PAGES_TYPES'][$storageDoktype] = [
            'type' => 'web',
            'allowedTables' => '*',
        ];

        // Provide icon for page tree, list view, ... :
        \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Imaging\IconRegistry::class)
            ->registerIcon(
                'apps-pagetree-storage',
                TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider::class,
                [
                    'source' => 'EXT:' . $extKey . '/Resources/Public/Icons/Storage.svg',
                ]
            );

        // Allow backend users to drag and drop the new page type:
        \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addUserTSConfig(
            'options.pageTree.doktypesToShowInNewPageDragArea := addToList(' . $storageDoktype . ')'
        );
    },
    'headless'
);
