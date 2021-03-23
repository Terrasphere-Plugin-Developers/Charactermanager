<?php

//extends the Member Controller since Character Sheet shenanigans are supposed to happen individually for each profile.

namespace Terrasphere\Charactermanager\XF\Pub\Controller;

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