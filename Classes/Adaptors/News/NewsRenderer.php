<?php

namespace Lfda\Monkeyhead\Adaptors\News;

use GeorgRinger\News\Controller\NewsController;
use GeorgRinger\News\Domain\Repository\NewsRepository;
use Lfda\Monkeyhead\Service\MappingService;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Extbase\Object\ObjectManager;

class NewsRenderer
{

    public function execute($content, $options)
    {
        if (!ExtensionManagementUtility::isLoaded('news')) {
            return ['Extension not installed'];
        }

        // Load settings
        $objectManager = GeneralUtility::makeInstance(ObjectManager::class);
        $configurationManager = $objectManager->get(ConfigurationManagerInterface::class);

        $news_settings_ts = $configurationManager->getConfiguration(ConfigurationManagerInterface::CONFIGURATION_TYPE_SETTINGS, 'news');
        $news_settings_ff = $content['flexform'];

        $settings = array_merge_recursive($news_settings_ts, $news_settings_ff['settings']);

        $news_controller = GeneralUtility::makeInstance(MonkeyheadNewsController::class);
        $demand = $news_controller->getDemand($settings);

        $news_repo = $objectManager->get(NewsRepository::class);
        $news_records = $news_repo->findDemanded($demand);

        $result = [];
        foreach ($news_records as $record) {
            $result[] = MappingService::transform($record, $options['mapping'], $options['table']);
        }

        return $result;
    }

}

class MonkeyheadNewsController extends NewsController
{
    public function __construct()
    {
        $this->objectManager = GeneralUtility::makeInstance(ObjectManager::class);
    }

    public function getDemand($settings) {
        return $this->createDemandObjectFromSettings($settings);
    }
}
