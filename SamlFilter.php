<?php

class SamlFilter extends CFilter {

	protected function preFilter($filterChain)
	{
		// logic being applied before the action is executed
		Yii::app()->user->loginRequired();

		return true; // false if the action should not be executed
	}
}