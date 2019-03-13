<?php

namespace Lfda\Headless\Controller;

use Lfda\Headless\Provider\ContentProvider;
use Lfda\Headless\Provider\ImageProvider;
use Lfda\Headless\Provider\PagesProvider;
use Lfda\Headless\Service\MappingService;
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
        $image_provider = $this->objectManager->get(ImageProvider::class);

        $image_provider->setArgument('uid', intval($_REQUEST['uid']));
        $image_provider->setArgument('width', intval($_REQUEST['w']));
        $image_provider->setArgument('height', intval($_REQUEST['h']));
        $image_provider->setArgument('crop', $_REQUEST['c']);

        $processedImage = $image_provider->fetchData();

        if ($processedImage) {
            $returnType = $_REQUEST['rt'];

            /** @var \TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController $typoScriptFrontendController */
            $typoScriptFrontendController = $GLOBALS['TSFE'];

            switch ($returnType) {
                case 'uri':
                    $imageService = $this->objectManager->get(ImageService::class);
                    $typoScriptFrontendController->setContentType('text/plain');
                    return $imageService->getImageUri($processedImage, true);
                    break;

                case 'b64':
                    $typoScriptFrontendController->setContentType('text/plain');
                    return base64_encode($processedImage->getContents());
                    break;

                case 'json':
                    $imageService = $this->objectManager->get(ImageService::class);
                    $typoScriptFrontendController->setContentType('application/json');
                    return json_encode([
                        'uri' => $imageService->getImageUri($processedImage, true),
                        'data' => base64_encode($processedImage->getContents())
                    ]);
                    break;

                case 'data':
                default:
                    $typoScriptFrontendController->setContentType($processedImage->getMimeType());
                    return $processedImage->getContents();
            }
        }

        return NULL;
    }
}
