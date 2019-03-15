<?php

namespace Lfda\Headless\Controller;

use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Service\ImageService;
use TYPO3\CMS\Core\Utility\GeneralUtility;

use Lfda\Headless\Provider\ContentProvider;
use Lfda\Headless\Provider\ImageProvider;
use Lfda\Headless\Provider\PagesProvider;
use Lfda\Headless\Provider\RecordsProvider;
use Lfda\Headless\Service\MappingService;

class HeadlessController extends ActionController
{

    public function pagesAction()
    {
        $pages_provider = $this->objectManager->get(PagesProvider::class);

        $pages_provider->setConfiguration($this->settings['tables']['pages']);
        $pages_provider->setArgument('recursive', max(1, intval($_REQUEST['r'])));
        $pages_provider->setArgument('root', intval($GLOBALS['TSFE']->id));
        $pages = $pages_provider->fetchData();

        return json_encode($pages);
    }

    public function contentAction()
    {
        $page_data = $this->configurationManager->getContentObject()->data;
        $mapping = $this->settings['tables']['pages']['mapping'];
        $mapped_page_record = MappingService::transform($page_data, $mapping, 'pages');

        // inject some information about the page languages
        $pages_provider = $this->objectManager->get(PagesProvider::class);
        $mapped_page_record['__language_information'] = $pages_provider->getLanguageInformation($page_data);

        $result = ['page' => $mapped_page_record];
        $content_provider = $this->objectManager->get(ContentProvider::class);
        $content_provider->setConfiguration($this->settings['tables']['tt_content']);
        if ($page_data['_PAGES_OVERLAY'] === true) {
            $content_provider->setArgument('language', intval($page_data['_PAGES_OVERLAY_LANGUAGE']));
        }
        $content_provider->setArgument('page', intval($page_data['uid']));

        $result['content'] = $content_provider->fetchData();

        return json_encode($result);
    }

    public function recordsAction()
    {
        $records_provider = $this->objectManager->get(RecordsProvider::class);
        $records_provider->setConfiguration($this->settings['records']);
        $records_provider->setArgument('page', intval($GLOBALS['TSFE']->id));
        $records_provider->setArgument('tables', GeneralUtility::trimExplode(',', $_REQUEST['t']));
        $records = $records_provider->fetchData();

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
