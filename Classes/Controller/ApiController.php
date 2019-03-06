<?php

namespace Lfda\Headless\Controller;

use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;

class ApiController extends ActionController
{

    public function pagesAction($page = 0)
    {
        \TYPO3\CMS\Extbase\Utility\DebuggerUtility::var_dump($this->request, __LINE__ . ' in ' . __CLASS__ );
        return json_encode('Foo');
    }

}