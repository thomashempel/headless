<?php

namespace Lfda\Headless\Controller;

use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Imaging\ImageManipulation\CropVariantCollection;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Service\ImageService;

class HeadlessController extends ActionController
{
    public function pagesAction()
    {
        $page_data = $this->configurationManager->getContentObject()->data;
        $result = $this->fetch_page_data($page_data['uid']);

        return json_encode($result);
    }

    public function contentAction()
    {
        $cacheIdentifier = sha1($GLOBALS['TSFE']->id);
        $cache = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Cache\CacheManager::class)->getCache('headless_content');

        if (($entry = $cache->get($cacheIdentifier)) === FALSE) {
            $page_data = $this->configurationManager->getContentObject()->data;
            $mapping = $this->settings['mapping']['pages'];
            $result = ['page' => $this->transform($page_data, $mapping, 'pages'), 'content' => []];

            $statement = $this->fetch('tt_content', array_keys($this->settings['mapping']['tt_content']), $page_data['uid']);
            $mapping = $this->settings['mapping']['tt_content'];
            if (!array_key_exists('uid', $mapping)) {
                $mapping['uid'] = [ 'type' => 'int' ];
            }

            while ($row = $statement->fetch()) {
                $result['content'][] = $this->transform($row, $mapping, 'tt_content');
            }

            $entry = json_encode($result);
            $cache->set(
                $cacheIdentifier,
                $entry,
                explode(',', $page_data['cache_tags']),
                $page_data['cache_timeout'] > 0 ? $page_data['cache_timeout'] : 60
            );

        }

        return $entry;
    }

    public function imageAction()
    {
        $imageUid = intval($_REQUEST['uid']);

        $imageService = $this->objectManager->get(ImageService::class);

        $image = $imageService->getImage($imageUid, null, true);
        $cropString = $_REQUEST['crop'];
        if ($cropString === null && $image->hasProperty('crop') && $image->getProperty('crop')) {
            $cropString = $image->getProperty('crop');
        }

        $cropVariantCollection = CropVariantCollection::create($cropString);
        $cropVariant = $_REQUEST['cropVariant'] ?: 'default';
        $cropArea = $cropVariantCollection->getCropArea($cropVariant);

        $processingInstructions = [
            'crop' => $cropArea->isEmpty() ? null : $cropArea->makeAbsoluteBasedOnFile($image),
        ];

        $width = intval($_REQUEST['w']) ?: 0;
        if ($width > 0) {
            $processingInstructions['maxWidth'] = $width;
        }

        $height = intval($_REQUEST['h']) ?: 0;
        if ($height > 0) {
            $processingInstructions['maxHeight'] = $height;
        }

        $processedImage = $imageService->applyProcessingInstructions($image, $processingInstructions);

        $returnType = $_REQUEST['rt'];

        /** @var \TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController $typoScriptFrontendController */
        $typoScriptFrontendController = $GLOBALS['TSFE'];

        switch ($returnType) {
            case 'uri':
                $typoScriptFrontendController->setContentType('text/plain');
                return $imageService->getImageUri($processedImage, true);
                break;

            case 'b64':
                $typoScriptFrontendController->setContentType('text/plain');
                return base64_encode($processedImage->getContents());
                break;

            case 'json':
                $typoScriptFrontendController->setContentType('application/json');
                return json_encode([
                    'uri' => $imageService->getImageUri($processedImage, true),
                    'data' => base64_encode($processedImage->getContents())
                ]);
                break;

            case 'data':
            default:
                $typoScriptFrontendController->setContentType($processedImage->getMimeType());

                // $this->response->setHeader('Content-Transfer-Encoding: binary'
                return $processedImage->getContents();
        }
    }

    protected function fetch_page_data($pid, $uid = 0)
    {
        $statement = $this->fetch('pages', array_keys($this->settings['mapping']['pages']), $pid, $uid);

        $result = [];
        while ($row = $statement->fetch()) {
            $mapping = $this->settings['mapping']['pages'];
            if (!array_key_exists('uid', $mapping)) {
                $mapping['uid'] = [ 'type' => 'int' ];
            }
            $result[] = $this->transform($row, $mapping, 'pages');
        }

        return $result;
    }

    protected function fetch($table, array $fields, $pid = -1, $uid = 0, $limit = 0, $includeHidden = false, $orderBy = 'sorting')
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($table);
        $query = $queryBuilder
            ->select(...$fields)
            ->from($table)
            ->where(
                $queryBuilder->expr()->eq(
                    'deleted',
                    $queryBuilder->createNamedParameter(0, \PDO::PARAM_INT)
                )
            );

        if ($pid >= 0) {
            $query->andWhere(
                $queryBuilder->expr()->eq(
                    'pid',
                    $queryBuilder->createNamedParameter($pid, \PDO::PARAM_INT)
                )
            );
        }
        if ($uid > 0) {
            $query->andWhere(
                $queryBuilder->expr()->eq(
                    'uid',
                    $queryBuilder->createNamedParameter($uid, \PDO::PARAM_INT)
                )
            );
        }

        if ($includeHidden === false) {
            $query->andWhere(
                $queryBuilder->expr()->eq(
                    'hidden',
                    $queryBuilder->createNamedParameter(0, \PDO::PARAM_INT)
                )
            );
        }

        $query->orderBy($orderBy);
        $query->setMaxResults($limit > 0 ? $limit : PHP_INT_MAX);

        return $query->execute();
    }

    protected function fetch_image_references($table, $fieldName, $uid, $fields = ['uid'])
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_file_reference');
        $query = $queryBuilder
            ->select(...$fields)
            ->from('sys_file_reference')
            ->where(
                $queryBuilder->expr()->eq(
                    'tablenames',
                    $queryBuilder->createNamedParameter($table, \PDO::PARAM_STR)
                )
            )
            ->andWhere(
                $queryBuilder->expr()->eq(
                    'uid_foreign',
                    $queryBuilder->createNamedParameter($uid, \PDO::PARAM_INT)
                )
            )
            ->andWhere(
                $queryBuilder->expr()->eq(
                    'fieldname',
                    $queryBuilder->createNamedParameter($fieldName, \PDO::PARAM_STR)
                )
            )
            ->orderBy('sorting_foreign');

        return $query->execute();
    }

    protected function transform(array $row, array $config, $table)
    {
        $result = [];

        foreach ($config as $fieldId => $fieldConfig) {
            $value = $row[$fieldId];

            switch ($fieldConfig['type']) {
                case 'int':
                    $value = intval($value);
                    break;
                case 'string':
                    $value = strval($value);
                    break;
                case 'bool':
                    $value = boolval($value);
                    break;
                case 'datetime':
                    $o = new \DateTime();
                    $o->setTimestamp(intval($value));
                    $tz = new \DateTimeZone(date_default_timezone_get());
                    $o->setTimezone($tz);
                    $value = $o;
                    break;
                case 'images':
                    // $fileRepository = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Resource\FileRepository::class);
                    // $fileObjects = $fileRepository->findByRelation($table, $fieldId, $row['uid']);
                    // \TYPO3\CMS\Extbase\Utility\DebuggerUtility::var_dump($fileObjects, __LINE__ . ' in ' . __CLASS__ );
                    $selection = isset($fieldConfig['select']) ? explode(',', $fieldConfig['select']) : ['uid'];
                    $value = $this->fetch_image_references($table, $fieldId, $row['uid'], $selection)->fetchAll();
                    break;
                default:
                    break;
            }

            // Map value to static values
            if (isset($fieldConfig['mapStatic']) && isset($fieldConfig['mapStatic'][$value])) {
                $value = $fieldConfig['mapStatic'][$value];
            }

            // If a name is set, return the value as the defined field
            if (isset($fieldConfig['name'])) {
                $result[$fieldConfig['name']] = $value;
            } else {
                $result[$fieldId] = $value;
            }
        }

        return $result;
    }
}