<?php

defined('TYPO3_MODE') || die('Access denied.');

call_user_func(
    function()
    {

        \TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerPlugin(
            'Lfda.Headless',
            'Api',
            'Api'
        );

        \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addStaticFile('headless', 'Configuration/TypoScript', 'Headless');
    }
);
