<?php

namespace Terrasphere\Charactermanager\Api\Controller;

use Terrasphere\Charactermanager\Entity\CharacterMastery;
use XF\Api\Controller\AbstractController;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;
use XF\Mvc\RouteMatch;

use XF\Mvc\Entity\Entity;

class Character extends \XF\Api\Controller\AbstractController{


    public function actionGet(): \XF\Api\Mvc\Reply\ApiResult
    {



        $options = $this->options();

        $key = \XF::apiKey();

        return $this->apiResult([
            'version_id' => \XF::$versionId,
            'key' => [
                'type' => $key->key_type,
                'allow_all_scopes' => $key->allow_all_scopes,
                'scopes' => $key->scopes
            ]
        ]);
    }


    public function actionGetMasteries(){
        $this->assertRequiredApiInput('username');
        $username=$this->filter('username','str');

        /** @var \Terrasphere\Charactermanager\XF\Entity\User $user */
        $user=$this->finder('XF:User')->where('username',$username)->fetchOne();

        $masteries = $user->getMasteries();
        $result = $masteries[0];
        return $this->apiResult([
            'val' => $masteries
        ]);
    }


}