<?php

namespace Lfda\Headless\Renderer;

use GeorgRinger\News\Controller\NewsController;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class NewsRenderer
{

    public function execute($content)
    {
        if (!ExtensionManagementUtility::isLoaded('news')) {
            return ['Extension not installed'];
        }

        $newsController = GeneralUtility::makeInstance(NewsController::class);
        return 'bar';

        /*
        $demand = $this->createDemandObjectFromSettings($this->settings);
        $demand->setActionAndClass(__METHOD__, __CLASS__);

        if ($this->settings['disableOverrideDemand'] != 1 && $overwriteDemand !== null) {
            $demand = $this->overwriteDemandObject($demand, $overwriteDemand);
        }
        $newsRecords = $this->newsRepository->findDemanded($demand);
        */
    }

}