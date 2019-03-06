<?php
defined('TYPO3_MODE') || die('Access denied.');

call_user_func(
    function() {

        \TYPO3\CMS\Extbase\Utility\ExtensionUtility::configurePlugin(
            'Lfda.Headless',
            'Api',
            [
                'Api' => 'pages, content, image'
            ],
            // non-cacheable actions
            [
                'Api' => 'pages, content, image'
            ]
        );
    }
);

// Define TypoScript as content rendering template
$GLOBALS['TYPO3_CONF_VARS']['FE']['contentRenderingTemplates'][] = 'headless/Configuration/TypoScript/';