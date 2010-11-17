<?php
/**
 * Copyright 2010 Yuri Timofeev tim4dev@gmail.com
 *
 * Webacula is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Webacula is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Webacula.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @author Yuri Timofeev <tim4dev@gmail.com>
 * @package webacula
 * @license http://www.gnu.org/licenses/gpl-3.0.html GNU Public License
 *
 */

class AuthController extends Zend_Controller_Action
{
    protected $defNamespace;
    const MAX_LOGIN_ATTEMPT = 3;
    protected $identity;



    public function init()
    {
    	parent::init();
        Zend_Loader::loadClass('FormLogin');
        Zend_Loader::loadClass('Zend_Auth_Adapter_DbTable');
        Zend_Loader::loadClass('Wbroles');
        $this->view->baseUrl = $this->_request->getBaseUrl();
        $this->_helper->layout->setLayout('login');
        // translate
        $this->view->translate = Zend_Registry::get('translate');
        // для переадресаций
        $this->_redirector = $this->_helper->getHelper('Redirector');
        // для подсчета кол-ва неудачных логинов для вывода капчи
        $this->defNamespace = new Zend_Session_Namespace('Default');
        // Get current role_id, role_name
        $auth    = Zend_Auth::getInstance();
        if ($ident   = $auth->getIdentity() )
            $this->identity = $ident;
    }


    /*
     * @return true если юзер залогинился
     */
    /* Использование :
     * // это действие недоступно без регистрации
		if ( !$this->isAuth() ) {
            $this->_redirect('auth/login');
        }
     */
    function isAuth()
    {
		$auth = Zend_Auth::getInstance();
		return $auth->hasIdentity();
    }

    public function loginAction()
    {
        if ( $this->isAuth() ) {
            $this->_forward('index', 'index'); // если уже залогинен: action, controller
            return;
        }
        $form = new formLogin();
        if ( $this->_request->isPost() && !$this->_request->getParam('from_forgot') ) {
            /* Проверяем валидность данных формы */
            if ( $form->isValid($this->_getAllParams()) )
            {
                $db = Zend_Registry::get('db_bacula');
                /**
                 * Настраиваем правила выборки пользователей из БД
                 * имя таблицы, название поля с идентификатором пользователя, название поля пароля
                 *
                 * [ $zendDb = null], [string $tableName = null], [string $identityColumn = null],
                 * [string $credentialColumn = null], [string $credentialTreatment = null])
                 */
                $authAdapter = new Zend_Auth_Adapter_DbTable(
                    $db, 
                    'webacula_users',
                    'login',
                    'pwd',
                    'MD5(?) AND active = 1' );
                /* Передаем в адаптер данные пользователя */
                $authAdapter->setIdentity($form->getValue('login'));
                $authAdapter->setCredential($form->getValue('pwd'));
                /* Собственно, процесс аутентификации */
                $auth = Zend_Auth::getInstance();
                $resultAuth = $auth->authenticate($authAdapter);
                /* Проверяем валидность результата */
                if( $resultAuth->isValid() )
                {
                    /* Пишем в сессию (default) необходимые нам данные (пароль обнуляем) */
                    $storage = $auth->getStorage();
                    $data = $authAdapter->getResultRowObject(array(
                        'id',
                        'login',
                        'role_id',
                        'role_name',
                        'email'
                    ));
                    // find role name
                    $table = new Wbroles();
                    $row   = $table->find($data->role_id);
                    if ($row->count() == 1)
                        $data->role_name = $row[0]['name'];
                    $storage->write($data);
                    // обнуляем счетчик неудачных логинов
                    if (isset($this->defNamespace->numLoginFails))
                        $this->defNamespace->numLoginFails = 0;
                    // remember me
                    if ($form->getValue('rememberme'))
                        Zend_Session::rememberMe();
                    // goto home page
                    $this->_redirect('index/index');
                } else {
                    sleep(2);  // TODO increase this value
                    $this->view->msg = $this->view->translate->_("Username or password is incorrect");
                    // включаем счетчик, если кол-во неудачных логинов большое то включаем капчу
                    $this->defNamespace->numLoginFails++;
                }
           }
        }
        /* Если данные не передавались или неверный логин, то выводим форму для авторизации */
        $this->view->caption = $this->view->translate->_('Login with your <font color="#00008B">We</font><font color="#A80000">bacula</font> account');
        $this->view->title   = $this->view->translate->_('Login with your Webacula account');
        $this->view->form = $form;
    }



    /**
     * Delete old tmp files
     */
    public function deleteOldTmpFiles()
    {
        foreach ( glob(TMP_DIR. '/webacula*.tmp', GLOB_NOSORT) as $filename ) {
            $diff = time() - filemtime($filename);
            if ( ($diff > 86400) )
                @ unlink($filename);
        }
    }


    /**
     * Cleaning cache : Zend_Cache and data/tmp files
     */
    protected function clearAllCache()
    {
	    // remove Bacula ACLs
        $bacula_acl = new MyClass_BaculaAcl();
        $bacula_acl->removeCache();
        // remove Webacula ACLs
        $cache = Zend_Registry::get('cache');
        $res = $cache->remove('MyClass_WebaculaAcl');
        // main menu cache
        $cache->remove($this->identity->role_id . '_main_menu');
        /*
         * Delete old tmp files
         */
        $this->deleteOldTmpFiles();
    }


    
    /**
     * "Выход" пользователя
     **/
	public function logoutAction()
	{
        $this->clearAllCache();
        /*
         * Final
         */
    	/* "Очищаем" данные об идентификации пользоваля */
		Zend_Auth::getInstance()->clearIdentity();
		Zend_Session::forgetMe();
		/*	Перебрасываем его на главную */
		$this->_redirect('/');
	}



    protected function emailForgotPassword($user_email, $user_name = '', $pwd)
    {
        Zend_Loader::loadClass('MyClass_SendEmail');
        $config = Zend_Registry::get('config_webacula');
        $email = new MyClass_SendEmail();
        $body = $this->view->translate->_( sprintf(
"Hello,

This is an automated message from site %s, please do not reply!

You have or someone impersonating you has requested to change your password
from IP : %s

New Password: %s

Once logged in you can change your password.

If you are not the person who made this request
send email to the site admin : %s

Thanks! \n",
            $this->_request->getServer('SERVER_NAME'),
            $this->_request->getServer('REMOTE_ADDR'),
            $pwd,
            $config->email->to_admin) );
        // $from_email, $from_name, $to_email, $to_name, $subj, $body
        return $email->mySendEmail(
            $config->email->from,
            $this->view->translate->_('Webacula password manager'),
            $user_email,
            $user_name,
            $this->view->translate->_('New Webacula password'),
            $body
        );
        
    }


    
    public function forgotPasswordAction()
	{
        Zend_Loader::loadClass('FormForgotPassword');
        $form = new formForgotPassword();
        if( $this->_request->isPost() ) {
            /* Проверяем валидность данных формы */
            if ( $form->isValid($this->_getAllParams()) )
            {
                $db = Zend_Registry::get('db_bacula');
                Zend_Loader::loadClass('Wbusers');
                $table = new Wbusers();
                // ищем email
                $select  = $table->select()->where('login = ?', $this->_getParam('login'))
                                ->where('email = ?', $this->_getParam('email'));
                $row = $table->fetchRow($select);
                /* login + email найдены ? */
                if( $row )
                {
                    // генерируем новый пароль
                    $new_password = md5( uniqid( rand() ) );
                    // высылаем пароль
                    $res = $this->emailForgotPassword($row->email, $row->name, $new_password);
                    if ( $res ) {
                        // сохраняем пароль в БД
                        $data = array(
                            'pwd' => md5( $new_password )
                        );
                        $where = $table->getAdapter()->quoteInto('id = ?', $row->id);
                        $table->update($data, $where);
                        // goto home page
                        $this->view->msg = $this->view->translate->_("New password set");
                        $this->_forward('login', 'auth', null, array('from_forgot' => 1)); // action, controller
                    } else {
                        $this->view->msg = $this->view->translate->_("Error while sending email. Email not send");
                    }
                } else {
                    sleep(2);  // TODO increase this value
                    $this->view->msg = $this->view->translate->_("Username or email is incorrect");
                }
           }
        }
        /* Если данные не передавались или неверный логин, то выводим форму для авторизации */
        $this->view->title = $this->view->translate->_('Reset password');
        $this->view->form  = $form;
    }

}