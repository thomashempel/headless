<?php

namespace Lfda\Monkeyhead\Controller;

use Lfda\Monkeyhead\Service\MappingService;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Crypto\PasswordHashing\PasswordHashFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;

class AuthController extends ActionController
{

    public function indexAction()
    {
        $action = $_REQUEST['action'];
        switch ($action) {
            case 'login':
                return $this->login();
            case 'logout':
                return $this->logout();
            case 'user':
                return $this->user();
            default:
                return json_encode(['success' => false, 'msg' => 'Invalid action']);
        }
    }

    public function login()
    {
        if ($this->request->getMethod() === 'OPTIONS') {
            return true;
        }

        $data = json_decode(file_get_contents('php://input'), true);

        if (!array_key_exists('username', $data) || !array_key_exists('password', $data)) {
            return json_encode(['succes' => false, 'msg' => 'Missing username or password']);
        }

        $username = $data['username'];
        $password = $data['password'];

        $GLOBALS['TSFE']->fe_user->checkPid = 0; //do not use a particular pid
        $info = $GLOBALS['TSFE']->fe_user->getAuthInfoArray();
        $user = $GLOBALS['TSFE']->fe_user->fetchUserRecord($info['db_user'], $username);


        if ( isset($user) && $user != '' ) {
            $hashFactory = GeneralUtility::makeInstance(PasswordHashFactory::class);
            $hashInstance = $hashFactory->getDefaultHashInstance('FE');

            if ($user && $hashInstance->checkPassword($password, $user['password'])) {
                $GLOBALS['TSFE']->fe_user->createUserSession($user);
                $GLOBALS['TSFE']->fe_user->user = $GLOBALS['TSFE']->fe_user->fetchUserSession();

                $reflection = new \ReflectionClass($GLOBALS['TSFE']->fe_user);
                $setSessionCookieMethod = $reflection->getMethod('setSessionCookie');
                $setSessionCookieMethod->setAccessible(TRUE);
                $setSessionCookieMethod->invoke($GLOBALS['TSFE']->fe_user);

                return json_encode(['succes' => true, 'user' => $this->user(false)]);
            }

            return json_encode(['succes' => false, 'msg' => 'Wrong password']);
        }

        return json_encode(['succes' => false, 'msg' => 'Login failed']);

    }

    public function logout()
    {
        return json_encode(['succes' => true]);
    }

    public function user($encode = true)
    {
        $loggedIn = GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect('frontend.user', 'isLoggedIn');
        $mapped_user = false;

        if ($loggedIn) {
            $user = $GLOBALS['TSFE']->fe_user->fetchUserSession();
            $mapped_user = MappingService::transform($user, $this->settings['auth']['mapping'], 'fe_users');
        }

        if ($encode) {
            return json_encode(['user' => $mapped_user]);
        } else {
            return $mapped_user;
        }
    }
}
