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
        /** @var \Terrasphere\Core\Repository\Mastery $masteryRepo */
        $masteryRepo = $this->repository('Terrasphere\Core:Mastery');

        $masteries = $masteryRepo->getMasteryListGroupedByClassification();

        $viewparams = $params->params();
        $viewparams["masteries"] = $masteries;

        return $this->view('Terrasphere\Charactermanager:MasterySelection', 'terrasphere_cm_mastery_selection', $viewparams);
    }
}