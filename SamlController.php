<?php

class SamlController extends CController
{
    public function actionLogin()
    {
        $returnUrl = Yii::app()->homeUrl;
        $errorUrl =  $this->createUrl(Yii::app()->errorHandler->errorAction);

        Yii::app()->user->login(array(
            'ReturnTo' => $returnUrl,
            'ErrorURL' => $errorUrl,
        ));
    }

    public function actionLogout($returnUrl=null)
    {
        if ($returnUrl === null) {
            $returnUrl = Yii::app()->homeUrl;
        }

        Yii::app()->user->logout($returnUrl);
    }
}
