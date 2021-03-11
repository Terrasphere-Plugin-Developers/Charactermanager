<?php

//extends the Member Controller since Character Sheet shenanigans are supposed to happen individually for each profile.

namespace Terrasphere\Charactermanager\XF\Pub\Controller;

//use XF\Pub\Controller\Member;
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

	


		$viewParams = [
			'user' => $user
		];
		return $this->view('XF:Member\CharacterSheet', 'terrasphere_cm_character_sheet', $viewParams);
	}

}