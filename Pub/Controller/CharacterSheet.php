<?php

namespace Terrasphere\Charactermanager\Pub\Controller;

use XF\Entity\User;
use XF\Mvc\ParameterBag;
use XF\Pub\Controller\AbstractController;

class CharacterSheet extends AbstractController
{
    public function actionIndex(): \XF\Mvc\Reply\Redirect
    {
        return $this->redirect($this->buildLink('terrasphere-core/masteries/mastery-list'));
    }

    public function actionSelectnew(ParameterBag $params)
    {

    }
}