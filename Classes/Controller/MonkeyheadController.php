<?php

namespace Lfda\Monkeyhead\Controller;

use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Service\ImageService;
use TYPO3\CMS\Core\Utility\GeneralUtility;

use Lfda\Monkeyhead\Provider\ContentProvider;
use Lfda\Monkeyhead\Provider\ImageProvider;
use Lfda\Monkeyhead\Provider\PagesProvider;
use Lfda\Monkeyhead\Provider\RecordsProvider;
use Lfda\Monkeyhead\Service\MappingService;

class MonkeyheadController extends ActionController
{

    protected function checkAccess()
    {
        if (!array_key_exists('security', $this->settings)) {
            return ['allowed' => true];
        }

        $require_secret = boolval($this->settings['security']['require-secret']);
        $secret = $this->settings['security']['secret'];

        if (!$require_secret) {
            return ['allowed' => true];
        }
        if (empty($secret)) {
            return ['allowed' => false, 'msg' => 'Empty secret'];
        }

        $headers = getallheaders();
        if (!array_key_exists('X-Secret', $headers)) {
            return ['allowed' => false, 'Header missing'];
        }

        if ($headers['X-Secret'] == $secret) {
            return ['allowed' => true];
        }

        return ['allowed' => false, 'Secret mismatch'];
    }

    public function pagesAction()
    {
        $access = $this->checkAccess();
        if ($access['allowed'] === false) {
            $this->response->setStatus(403);
            return json_encode($access);
        }

        $pages_provider = $this->objectManager->get(PagesProvider::class);

        $pages_provider->setConfiguration($this->settings['tables']['pages']);
        $pages_provider->setArgument('recursive', max(1, intval($_REQUEST['r'])));
        $pages_provider->setArgument('root', intval($GLOBALS['TSFE']->id));
        $pages = $pages_provider->fetchData();

        return json_encode($pages);
    }

    public function contentAction()
    {
        $access = $this->checkAccess();

        if ($access['allowed'] === false) {
            $this->response->setStatus(403);
            return json_encode($access);
        }

        $page_data = $this->configurationManager->getContentObject()->data;
        $mapping = $this->settings['tables']['pages']['mapping'];
        $mapped_page_record = MappingService::transform($page_data, $mapping, 'pages');

        // inject some information about the page languages
        if (intval($this->settings['tables']['pages']['options']['includeLanguageInformation']) === 1) {
            $pages_provider = $this->objectManager->get(PagesProvider::class);
            $mapped_page_record['__language_information'] = $pages_provider->getLanguageInformation($page_data);
        }

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
        $access = $this->checkAccess();
        if ($access['allowed'] === false) {
            $this->response->setStatus(403);
            return json_encode($access);
        }

        if (!isset($this->settings['records'])) {
            return json_encode(['No records configuration found']);
        }

        $records_provider = $this->objectManager->get(RecordsProvider::class);
        $records_provider->setConfiguration($this->settings['records']);
        $records_provider->setArgument('page', intval($GLOBALS['TSFE']->id));
        $records_provider->setArgument('tables', GeneralUtility::trimExplode(',', $_REQUEST['t']));
        $records_provider->setArgument('record_id', intval($_REQUEST['rid']));

        if (is_array($this->settings['records']['urlArguments'])) {
            foreach ($this->settings['records']['urlArguments'] as $urlArgument => $argumentType) {
                switch ($argumentType) {
                    case 'string':
                        $records_provider->setArgument($urlArgument, strval($_REQUEST[$urlArgument]));
                        break;
                    case 'int':
                    default:
                        $records_provider->setArgument($urlArgument, intval($_REQUEST[$urlArgument]));
                        break;
                }
            }
        }

        $records = $records_provider->fetchData();

        return json_encode($records);
    }

    public function imageAction()
    {
        $access = $this->checkAccess();
        if ($access['allowed'] === false) {
            $this->response->setStatus(403);
            return json_encode($access);
        }

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
                    header_remove('Pragma');
                    header_remove('Content-Language');

                    $dt = new \DateTime();
                    $dt = $dt->add(date_interval_create_from_date_string('1 day'));
                    header('Expires: ' . $dt->format('D, d M Y H:i:s \G\M\T'));
                    header('Content-Length: ' . $processedImage->getSize());
                    header('Content-Type: ' . $processedImage->getMimeType());

                    return $processedImage->getContents();
            }
        }

        return NULL;
    }
}
