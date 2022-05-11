<?php

namespace Terrasphere\Charactermanager\Api\Controller;

use Terrasphere\Charactermanager\Repository\RacialTraits;
use Terrasphere\Charactermanager\XF\Entity\User;
use XF\Api\Controller\AbstractController;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Repository;
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
        $account = $this->filter("id","string");
        /** @var \Terrasphere\Charactermanager\XF\Entity\User $user */
        $user = $this->getUserObj($account);
        if(!$user){
            return $this->apiResult([
                'error' => 'user not found'
            ]);
        }
        $isSubAccount = $user->xc_la_is_sub_account;
        $avatarUrls = $this->getAvatarUrls($user);
        $bannerUrls = $this->getBannerUrls($user);
        $result = [
            'username' => $user->username,
            'user_id' => $user->user_id,
            'avatar_urls' => $avatarUrls,
            'banner_urls' => $bannerUrls,
            'primary_user_group_id' => $user->user_group_id,
            'secondary_user_group_ids' => $user->secondary_group_ids,
            'is_sub_account' => $isSubAccount,
        ];

        if($user['custom_title'] != '')
            $result['custom_title'] = $user['custom_title'];

        if($isSubAccount){
            $result['main_account'] = $this->buildProfileUrlNamePairById($user->ParentAccount->user_id);
            //Put in race
            $result['Race'] = $this->getRaceName($user);
            //Build equipment array to return apiresult
            $equipResult = $this->getEquipmentArray($user);
            $result['equipment'] = $equipResult;
            //Build the mastery array to return
            $masteryResult = $this->getMasteryArray($user);
            $result['masteries'] = $masteryResult;
            $result['racial_traits'] = $this->getUserTraits($user);

        }else{
            $result['sub_accounts'] = $this->getSubUsers($user);
        }

        $result['inventory'] = $this->getInventory($user);

        return $this->apiResult($result);
    }

    private function getInventory(User $user): array {
        $repo = $this->repository('DBTech\Shop:Purchase');
        $inventory = $repo->findInventoryForUser($user['user_id'])->fetch()->toArray();
        $i = [];
        foreach ($inventory as $iv)
        {
            $item = [];
            $item['id'] = $iv->Item->item_id;
            $item['is_active'] = $iv->isActive();
            $item['is_expired'] = $iv->isExpired();
            $item['expiry_date'] = $iv->expiry_date;
            $item['name'] = $iv->Item->getTitle();
            $item['tagline'] = $iv->Item->getTagline();

            $i[] = $item;
        }
        return $i;
    }

    private function getEquipmentArray(User $user): array{
        $equipments = $this->finder('Terrasphere\Charactermanager:CharacterEquipment')->where('user_id', $user->user_id);
        $equipResult = array();
        foreach($equipments as $equipment){
            $equipName = $equipment->Equipment->display_name;
            $equipRank = $equipment->Rank->name;
            $equipResult[] = [$equipName => $equipRank];
        }
        return $equipResult;
    }
    private function getMasteryArray(User $user):array{
        $masteries = $user->getMasteries();
        $masteryResult = array();
        foreach($masteries as $mastery){
            $masteryName = $mastery->Mastery->display_name;
            $masteryRank = $mastery->Rank->name;
            $masteryResult[] = ['Mastery' => $masteryName, 'Rank' => $masteryRank];
        }
        return $masteryResult;
    }
    private function getAvatarUrls(User $user):array{
        $avatarUrls = array();
        foreach (array_keys($this->app()->container('avatarSizeMap')) AS $avatarSize)
        {
            $avatarUrls[$avatarSize] = $user->getAvatarUrl($avatarSize, null, true);
        }
        return $avatarUrls;
    }
    private function getBannerUrls(User $user):array{
        $profileBannerUrls = [];
        foreach (array_keys($this->app()->container('profileBannerSizeMap')) AS $bannerSize)
        {
            $profileBannerUrls[$bannerSize] = $user->Profile->getBannerUrl($bannerSize, true);
        }
        return $profileBannerUrls;
    }
    private function getUserObj(String $id){
        if(is_numeric($id)){
            $user=$this->finder('XF:User')->where('user_id',$id)->fetchOne();
        }else{
            $user=$this->finder('XF:User')->where('username',$id)->fetchOne();
        }
        return $user;
    }
    private function getRaceName(User $user):String{

        //get the ids from the subaccount addon for sub-accounts(thus, races) and turn them into a array with race names
        $raceUserGroups = $this->options()['flp_la_usergroups'];
        $raceIds = explode(',',$raceUserGroups);
        $raceGroups = $this->finder('XF:UserGroup')->whereIds($raceIds)->fetch();
        $raceNames = [];
        foreach ($raceGroups as $raceGroup) {
            $raceNames[] = $raceGroup->title;
        }

        //get secondary groups from the user, one of them has to be the race
        $groupIds = $user->secondary_group_ids;
        $groups = $this->finder('XF:UserGroup')->whereIds($groupIds)->fetch();

        //cross-reference the users secondary groups with the racegroup list
        foreach ($groups as $group){
            $name = $group->title;
            foreach($raceNames as $raceName){
                if($raceName == $name){
                    return $name;
                }
            }
        }
        return "Not found";
    }
    private function getSubUsers(User $user):array{
        $result = [];
        $subs = $user->xc_la_user_ids;
        foreach ($subs as $key=>$sub){
            $potentialArray = $this->buildProfileUrlNamePairById($key);
            if($potentialArray != null) $result[] = $potentialArray;
        }
        return $result;
    }
    private function buildProfileUrlNamePairById(int $id){
        $user = $this->finder('XF:User')->where('user_id',$id)->fetchOne();
        if(!$user){
            return null;
        }
        $baseUrl = \XF::options()->boardUrl;
        $url = "$baseUrl/members/{$user->username}.{$user->user_id}";
        $url = str_replace(' ', '-', $url);
        return  [
            'name' => $user->username,
            'url' => $url,
        ];
    }
    private function getUserTraits(User $user):array{
        $raceTraitRepo = $this->repository('Terrasphere\Charactermanager:RacialTraits');
        $traits = $raceTraitRepo->getRacialTraitSlotsForUser($user);
        # $traits = $user->getTraits();
        $traitResult = [];
        foreach ($traits as $trait)
        {
            if($trait['isEmpty'] >= 1)
            {
                $traitResult[] = "EMPTY";
            }
            else
            {
                $traitResult[] = $trait['trait']['RacialTrait']['name'];
            }
        }
        return $traitResult;
    }
}