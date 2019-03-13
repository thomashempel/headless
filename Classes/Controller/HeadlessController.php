<?php

namespace Lfda\Headless\Controller;

use Lfda\Headless\Provider\ContentProvider;
use Lfda\Headless\Provider\PagesProvider;
use Lfda\Headless\Service\MappingService;
use TYPO3\CMS\Core\Imaging\ImageManipulation\CropVariantCollection;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Service\ImageService;

class HeadlessController extends ActionController
{

    public function pagesAction()
    {
        $pages_provider = $this->objectManager->get(PagesProvider::class);

        $pages_provider->setConfiguration($this->settings['tables']['pages']);
        $pages_provider->setArgument('recursive', max(1, intval($_REQUEST['r'] > 1)));
        $pages_provider->setArgument('root', intval($GLOBALS['TSFE']->id));
        $pages = $pages_provider->fetchData();

        return json_encode($pages);
    }

    public function contentAction()
    {
        $page_data = $this->configurationManager->getContentObject()->data;
        $mapping = $this->settings['tables']['pages']['mapping'];
        $result = ['page' => MappingService::transform($page_data, $mapping, 'pages')];

        $content_provider = $this->objectManager->get(ContentProvider::class);
        $content_provider->setConfiguration($this->settings['tables']['tt_content']);
        $content_provider->setArgument('page', intval($page_data['uid']));
        $result['content'] = $content_provider->fetchData();

        return json_encode($result);
    }

    public function recordsAction()
    {
        $tables = GeneralUtility::trimExplode(',', $_REQUEST['t']);
        $records = [];

        foreach ($tables as $shortname) {
            if (array_key_exists($shortname, $this->settings['allowedRecords'])) {
                $table = $this->settings['allowedRecords'][$shortname];
                if (array_key_exists($table, $this->settings['mapping'])) {
                    $statement = $this->fetch($table, array_keys($this->settings['mapping'][$table]), $GLOBALS['TSFE']->id);
                    $result = [];

                    while ($row = $statement->fetch()) {
                        $mapping = $this->settings['mapping'][$table];
                        if (!array_key_exists('uid', $mapping)) {
                            $mapping['uid'] = [ 'type' => 'int' ];
                        }
                        $result[] = $this->transform($row, $mapping, $table);
                    }

                    $records[$shortname] = $result;
                }
            }
        }

        return json_encode($records);
    }

    public function imageAction()
    {
        $imageUid = intval($_REQUEST['uid']);

        $imageService = $this->objectManager->get(ImageService::class);

        $image = $imageService->getImage($imageUid, null, true);
        $cropString = null;
        if (isset($_REQUEST['c'])) {
            list($x, $y, $w, $h) = GeneralUtility::trimExplode(',', $_REQUEST['c']);

            $cropString = json_encode([
                'default' => [
                    'cropArea' => [
                        'x' => floatval($x),
                        'y' => floatval($y),
                        'width' => floatval($w),
                        'height' => floatval($h)
                    ]
                ]
            ]);

        }

        // $cropString = '{"default":{"cropArea":{"height":0.6570247933884298,"width":0.7851239669421488,"x":0,"y":0},"selectedRatio":"NaN","focusArea":null}}';
        if ($cropString === null && $image->hasProperty('crop') && $image->getProperty('crop')) {
            $cropString = $image->getProperty('crop');
        }

        // {"default":{"cropArea":{"x":0,"y":0,"width":1,"height":1}}}
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

    /*
    protected function fetch_page_data($pid, $recursive = 1)
    {

        $selection = [];
        if (array_key_exists('defaults', $this->settings['selection']['pages'])) {
            $selection = $this->settings['selection']['pages']['defaults'];
        }

        $statement = $this->fetch('pages', array_keys($this->settings['mapping']['pages']), $pid, 0, $selection);

        $result = [];
        while ($row = $statement->fetch()) {
            $mapping = $this->settings['mapping']['pages'];
            if (!array_key_exists('uid', $mapping)) {
                $mapping['uid'] = [ 'type' => 'int' ];
            }
            $page = $this->transform($row, $mapping, 'pages');

            if ($recursive > 1) {
                $children = $this->fetch_page_data($page['uid'], --$recursive);
                if (count($children) > 0) {
                    $page['__children'] = $this->fetch_page_data($page['uid'], --$recursive);
                }
            }

            $result[] = $page;
        }

        return $result;
    }

    protected function fetch($table, array $fields, $pid = -1, $uid = 0, array $selection = [], $limit = 0, $orderBy = 'sorting')
    {

        if ($pid >= 0) {
            $selection['pid'] = ['value' => $pid, 'type' => \PDO::PARAM_INT];
        }

        if ($uid > 0) {
            $selection['uid'] = ['value' => $pid, 'type' => \PDO::PARAM_INT];
        }


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

        foreach ($selection as $key => $conf) {
            $query->andWhere(
                $queryBuilder->expr()->eq(
                    $key,
                    $queryBuilder->createNamedParameter($conf['value'], $conf['type'])
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

                case 'flexform':
                    $ffs = GeneralUtility::makeInstance(FlexFormService::class);
                    $value = $ffs->convertFlexFormContentToArray($value);
                    break;

                default:
                    break;
            }

            // Map value to static values
            if (isset($fieldConfig['mapStatic']) && isset($fieldConfig['mapStatic'][$value])) {
                $value = $fieldConfig['mapStatic'][$value];
            }

            // If a name is set, return the value as the defined field
            if (isset($fieldConfig['as'])) {
                $result[$fieldConfig['as']] = $value;
            } else {
                $result[$fieldId] = $value;
            }
        }

        return $result;
    }
    */
}
