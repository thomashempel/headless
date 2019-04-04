<?php

namespace Lfda\Monkeyhead\Adaptors\News;

use GeorgRinger\News\Domain\Repository\NewsRepository;
use Lfda\Monkeyhead\Provider\BaseProvider;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Object\ObjectManager;

class NewsProvider extends BaseProvider
{

    public function fetchData()
    {
        $obj_manager = GeneralUtility::makeInstance(ObjectManager::class);
        /** @var NewsRepository $repository */
        $repository = $obj_manager->get(NewsRepository::class);
        return $repository->findByUid($this->getArgument('uid'));

    }

}
