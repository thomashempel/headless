<?php

namespace Lfda\Monkeyhead\Adaptors\News;

use GeorgRinger\News\Domain\Repository\NewsRepository;
use Lfda\Monkeyhead\Provider\BaseProvider;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Extbase\Persistence\Generic\QuerySettingsInterface;
use TYPO3\CMS\Extbase\Persistence\Generic\Typo3QuerySettings;

class NewsProvider extends BaseProvider
{

    public function fetchData()
    {
        $obj_manager = GeneralUtility::makeInstance(ObjectManager::class);
        /** @var NewsRepository $repository */
        $repository = $obj_manager->get(NewsRepository::class);

        $uid = $this->getArgument('uid', 0);
        if ($uid > 0) {
            return [$repository->findByUid($uid)];

        } else {
            /** @var QuerySettingsInterface $querySettings */
            $querySettings = $obj_manager->get(Typo3QuerySettings::class);
            $querySettings->setStoragePageIds([$GLOBALS['TSFE']->id]);
            $repository->setDefaultQuerySettings($querySettings);
            return $repository->findAll();
        }
    }

}
