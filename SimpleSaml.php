<?php

class SimpleSaml extends RWebUser
{
    public $samlLoaderFilePath;
    public $authSource;
    public $attributesConfig = array();

    private $_initialized = false; // from CApplicationComponent for init()
    private $_parentInitialized = false; // for CWebUser::init()
    private $_saml;
    private $_attributes;

    public function __call($name, $parameters)
    {
        $saml = $this->getSaml();

        if (method_exists($saml, $name)) {
            return call_user_func_array(array($saml,$name), $parameters);
        } else {
            return parent::__call($name, $parameters);
        }
    }

    public function __get($name)
    {
        if ($this->hasAttribute($name)) {
            return $this->getAttribute($name);
        } else {
            return parent::__get($name);
        }
    }

    public function __isset($name)
    {
        if ($this->hasAttribute($name)) {
            return $this->getAttribute($name)!==null;
        } else {
            return parent::__isset($name);
        }
    }

    public function init()
    {
        $this->attachBehaviors($this->behaviors);
        $this->_initialized=true;

        if (!isset($this->samlLoaderFilePath)) {
            throw new CException("SimpleSAMLPHP's core loader file path is not set");
        }
        if (!file_exists($this->samlLoaderFilePath)) {
            throw new CException("SimpleSAMLPHP's core loader file path is not exists");
        }
        if (!isset($this->authSource)) {
            throw new CException("SimpleSAMLPHP's auth source is not set");
        }
    }

    public function parentInit()
    {
        if (!$this->_parentInitialized) {
            $this->_parentInitialized = true;

            if (!Yii::app()->getSession()->getIsStarted()) {
                Yii::app()->getSession()->open();
            }
            if ($this->getIsLocalGuest() && $this->allowAutoLogin) {
                $this->restoreFromCookie();
            } elseif ($this->autoRenewCookie && $this->allowAutoLogin) {
                $this->renewCookie();
            }
            if ($this->autoUpdateFlash) {
                $this->updateFlash();
            }

            $this->updateAuthStatus();
        }
    }

    public function getBranchAccess()
    {
        $this->branchId;

        if (isset($this->branchId) and $this->branchId>0) {
            $arr =  Yii::app()->cache->get('BranchAccess');
            if ($arr) {
                return $arr;
            } else {
                $arr[] = $this->branchId;
                // $dependency = new CDbCacheDependency('SELECT MAX(updated_stamp) FROM com_branch');
                $branchs = Branch::model()->findAllByAttributes(array('parent_id'=>$this->branchId));
                foreach ($branchs as $branch) {
                    $arr[] = $branch->id;
                }
                Yii::app()->cache->set('BranchAccess', $arr);
                return $arr;
            }
        } else {
            return array();
        }
    }

    public function getSaml()
    {
        if (!isset($this->_saml)) {
            spl_autoload_unregister(array('YiiBase','autoload'));

            require_once($this->samlLoaderFilePath);

            $core = new SimpleSAML_Auth_Simple($this->authSource);

            spl_autoload_register(array('YiiBase','autoload'));

            $this->_saml = $core;
        }

        return $this->_saml;
    }

    protected function getAttributesInternal()
    {
        $saml = $this->getSaml();

        $attributes = array();
        $attributesConfig = $this->attributesConfig;
        $coreAttributes = $saml->getAttributes();
        
        foreach ($attributesConfig as $localAttr => $coreAttr) {
            if (isset($coreAttributes[$coreAttr][0])) {
                $attributes[$localAttr] = $coreAttributes[$coreAttr][0];
            }
        }

        return $attributes;
    }

    public function getAttributes()
    {
        if (!isset($this->_attributes)) {
            $this->_attributes = $this->getAttributesInternal();
        }

        return $this->_attributes;
    }

    public function getAttribute($name)
    {
        if (!isset($this->_attributes)) {
            $this->_attributes = $this->getAttributesInternal();
        }

        if (isset($this->_attributes[$name])) {
            return $this->_attributes[$name];
        } else {
            return null;
        }
    }

    public function hasAttribute($name)
    {
        if (!isset($this->_attributes)) {
            $this->_attributes = $this->getAttributesInternal();
        }

        if (isset($this->_attributes[$name])) {
            return true;
        } else {
            return false;
        }
    }

    public function login($params=array(), $duration=0)
    {
        $saml = $this->getSaml();
        $saml->login($params);
        $this->afterLogin(false);
    }

    public function logout($returnUrl=null, $params=array())
    {
        $app = Yii::app();
        $request = $app->getRequest();

        if (is_array($returnUrl)) {
            $params = $returnUrl;
            $returnUrl = null;
        }
        if (isset($params['ReturnTo']) && $returnUrl === null) {
            $returnUrl = $params['ReturnTo'];
        }

        $saml = $this->getSaml();
        if ($saml->isAuthenticated()) {
            $params['ReturnTo'] = $request->url;
            $saml->logout($params);
        }
        
        parent::logout();
        if ($returnUrl === null) {
            $returnUrl = $app->homeUrl;
        }
        $request->redirect($returnUrl);
    }

    public function localLogin($identity, $duration=0)
    {
        $this->parentInit();
        return parent::login($identity, $duration);
    }

    public function localLogout($destroySession=true)
    {
        parent::logout($destroySession);
    }

    public function loginRequired()
    {
        $app = Yii::app();
        $request = $app->getRequest();

        if (!$request->getIsAjaxRequest()) {
            $this->requireAuth(array(
                'ReturnTo' => $request->url,
                'KeepPost' => true,
            ));
            $this->afterLogin(false);
        } elseif ($this->getIsGuest()) {
            if (isset($this->loginRequiredAjaxResponse)) {
                echo $this->loginRequiredAjaxResponse;
            } else {
                echo "login required";
            }
            Yii::app()->end();
        }
    }

    public function getIsGuest()
    {
        return !$this->isAuthenticated();
    }

    public function getIsLocalGuest()
    {
        return parent::getIsGuest();
    }

    public function getId()
    {
        if ($this->hasAttribute('id')) {
            return $this->getAttribute('id');
        } else {
            return parent::getId();
        }
    }

    public function setId($value)
    {
        if (!$this->hasAttribute('id')) {
            parent::setId($value);
        }
    }

    public function getName()
    {
        if ($this->hasAttribute('name')) {
            return $this->getAttribute('name');
        } else {
            return parent::getName();
        }
    }

    public function setName($value)
    {
        if (!$this->hasAttribute('name')) {
            parent::setName($value);
        }
    }

    public function localLoginRequired()
    {
        $this->parentInit();
        parent::loginRequired();
    }

    public function getState($name, $defaultValue = null)
    {
        if ($this->hasAttribute($name)) {
            return $this->getAttribute($name);
        } else {
            $this->parentInit();
            return parent::getState($name, $defaultValue);
        }
    }

    public function setState($key, $value, $defaultValue=null)
    {
        $this->parentInit();
        parent::setState($key, $value, $defaultValue);
    }

    public function hasState($name)
    {
        if ($this->hasAttribute($name)) {
            return true;
        } else {
            $this->parentInit();
            return parent::hasState($name);
        }
    }

    public function clearStates()
    {
        $this->parentInit();
        parent::clearStates();
    }

    public function getFlashes($delete=true)
    {
        $this->parentInit();
        return parent::getFlashes($delete);
    }
}
