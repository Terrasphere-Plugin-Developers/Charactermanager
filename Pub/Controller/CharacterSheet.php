<?php

namespace Terrasphere\Charactermanager\Pub\Conntroller;

use XF\Pub\Controller\AbstractController;

class CharacterSheet extends AbstractController
{
    public function actionIndex(): \XF\Mvc\Reply\Redirect
    {
        return $this->redirect($this->buildLink('terrasphere-core/masteries/mastery-list'));
    }
}