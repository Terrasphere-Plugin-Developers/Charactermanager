<?php

//extends the Member Controller since Character Sheet shenanigans are supposed to happen individually for each profile.

namespace Terrasphere\Charactermanager\XF\Pub\Controller;

use Laminas\Mail\Header\From;
use Terrasphere\Charactermanager\Entity\CharacterMastery;
use XF\Entity\User;
use XF\Entity\UserProfile;
use XF\Mvc\FormAction;
use XF\Mvc\ParameterBag;

class Member extends XFCP_Member
{
    //copied and changed from Member latestActivity
    public function actionCharacterSheet(ParameterBag $params)
	{
        //gotta figure out what dis does
		$user = $this->assertViewableUser($params->user_id);

        $masterySlots = CharacterMastery::getCharacterMasterySlots($this, $params->user_id);

		$viewParams = [
		    'user' => $user,
		    'masterySlots' => $masterySlots,
            'canViewCS' => $this->canVisitorViewCharacterSheet($params->user_id),
            'canViewRevisions' => $this->canVisitorViewRevisions($params->user_id),
            'viewerIsUser' => $this->visitorIsUser($params->user_id),
        ];

		return $this->view('XF:Member\CharacterSheet', 'terrasphere_cm_character_sheet', $viewParams);
	}

    public function actionSelectNew(ParameterBag $params)
    {
        /** @var \Terrasphere\Core\Repository\Mastery $masteryRepo */
        $masteryRepo = $this->repository('Terrasphere\Core:Mastery');

        $masteries = $masteryRepo->getMasteryListGroupedByClassification();

        $dummyMasterySlot = $this->em()->create('Terrasphere\Charactermanager:CharacterMastery');
        $dummyMasterySlot['user_id'] = $params['user_id'];
        $dummyMasterySlot['target_index'] = $params['target_index'];

        $viewparams = [
            'masteries' => $masteries,
            'masterySlot' => $dummyMasterySlot,
        ];

        return $this->view('Terrasphere\Charactermanager:MasterySelection', 'terrasphere_cm_mastery_selection', $viewparams);
    }

    public function actionSaveNewMastery(ParameterBag $params)
    {
        if($this->isPost())
        {
            $input = $this->filter([
                'mastery' => 'uint'
            ]);

            /** @var \Terrasphere\Core\Repository\Mastery $masteryRepo */
            $masteryRepo = $this->repository('Terrasphere\Core:Mastery');

            $mastery = $masteryRepo->getMasteryByID($input['mastery']);

            if($mastery != null)
            {
                $redirect = $this->redirect($this->buildLink('members', null, ['user_id' => $params['user_id']]));
                $redirect->setJsonParam('masterySlotIndex', $params['target_index']);
                $redirect->setJsonParam('newMasteryName', $mastery['display_name']);
                $redirect->setJsonParam('newMasteryIconURL', $mastery['icon_url']);
                // TODO Add another for updating cost of upgrade...
                return $redirect;
            }
            else
            {
                return $this->error('Mastery is missing from database; try reloading.');
            }
        }
    }

	public function canVisitorViewCharacterSheet(int $userID): bool {
        return true;
    }

    public function canVisitorViewRevisions(int $userID): bool {
        return \XF::visitor()->hasPermission('terrasphere', 'terrasphere_cm_review')
            || $this->visitorIsUser($userID);
    }


    private function visitorIsUser(int $userID): bool {
        return \XF::visitor()->user_id == $userID;
    }
}