<?php

namespace Terrasphere\Charactermanager\Api\Controller;

use XF\Api\Controller\AbstractController;
use XF\Mvc\ParameterBag;
use XF\Api\Mvc\Reply\ApiResult;

class Character extends AbstractController{


    /**
     * Default Get Action for the Character Controller, requiring a user_id/name to function.
     * @param ParameterBag $params
     * @return ApiResult
     */
    public function actionGet(): ApiResult
    {

        ///Check if the user ID is numeric or a name, if numeric it's the user id, if name it is the user name
        /** @var \Terrasphere\Charactermanager\XF\Entity\User $user */
          $account = $this->filter("id","string");
        if(is_numeric($account)){
            $user=$this->finder('XF:User')->where('user_id',$account)->fetchOne();
        }else{
            $user=$this->finder('XF:User')->where('username',$account)->fetchOne();
        }


        //Build proper equipment array to return apiresult
        //TODO: Need to refactor how equipment works for this to work
        $equipments = $this->finder('Terrasphere\Charactermanager:CharacterEquipment')->where('user_id', $user->user_id);
        $equipResult = array();
        foreach($equipments as $equipment){
            $equipName = $equipment->Equipment->display_name;
            $equipRank = $equipment->Rank->name;
            $equipResult[] = [$equipName => $equipRank];
        }

        //Build the prooper mastery array to return
        $masteries = $user->getMasteries();
        $masteryResult = array();
        foreach($masteries as $mastery){
            $masteryName = $mastery->Mastery->display_name;
            $masteryRank = $mastery->Rank->name;
            $masteryResult[] = ['Mastery' => $masteryName, 'Rank' => $masteryRank];
        }

        return $this->apiResult([
            'username' => $user->username,
            'user_id' => $user->user_id,
            'masteries' => $masteryResult,
            'equipment' => $equipResult,
        ]);
    }


}