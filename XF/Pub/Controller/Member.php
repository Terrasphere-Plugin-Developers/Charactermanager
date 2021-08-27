<?php

//extends the Member Controller since Character Sheet shenanigans are supposed to happen individually for each profile.

namespace Terrasphere\Charactermanager\XF\Pub\Controller;

use Terrasphere\Charactermanager\Entity\CharacterEquipment;
use Terrasphere\Charactermanager\Entity\CharacterMastery;
use Terrasphere\Core\Entity\Rank;
use Terrasphere\Core\Util\PostProxyHelper;
use XF\Entity\User;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\Exception;

class Member extends XFCP_Member
{
    //copied and changed from Member latestActivity
    /**
     * @throws Exception
     */
    public function actionCharacterSheet(ParameterBag $params)
	{
        try {
            /** @var \Terrasphere\Charactermanager\XF\Entity\User $user */
            $user = $this->assertViewableUser($params['user_id']);
        } catch (Exception $e) {
            throw $e;
        }

        $masterySlots = CharacterMastery::getCharacterMasterySlots($this, $params->user_id);
        $weapon = $user->getOrInitiateWeapon();

        $armor = $user->getOrInitiateArmor();

        $maxRank = Rank::maxRank($this);

		$viewParams = [
		    'user' => $user,
		    'masterySlots' => $masterySlots,
            'weapon' => $weapon,
            'armor' => $armor,
		    'maxRank' => $maxRank['rank_id'],
            'hasCS' => $user['ts_cm_character_sheet_post_id'] != -1,
            'canViewCS' => $this->canVisitorViewCharacterSheet($params->user_id),
            'canViewRevisions' => $this->canVisitorViewRevisions($params->user_id),
            'canMakeChanges' => $this->canVisitorMakeChanges($params->user_id),
            'fourthSlotUnlocked' => $this->fourthSlotUnlocked($params->user_id),
            'fifthSlotUnlocked' => $this->fifthSlotUnlocked($params->user_id),
        ];

		if($viewParams['hasCS'])
		    $viewParams = array_merge($viewParams, PostProxyHelper::getPostProxyParams($this, $user['ts_cm_character_sheet_post_id']));

		return $this->view('XF:Member\CharacterSheet', 'terrasphere_cm_character_sheet', $viewParams);
	}

    /**
     * @throws Exception
     */
    public function actionLinkCs(ParameterBag $params)
    {
        try {
            /** @var \Terrasphere\Charactermanager\XF\Entity\User $user */
            $user = $this->assertViewableUser($params['user_id']);
        } catch (Exception $e) {
            throw $e;
        }

        return $this->view('XF:Member\LinkCS', 'terrasphere_cm_cs_link_page', ['user' => $user]);
    }

    /**
     * @throws Exception
     */
    public function actionLinkCSConfirm(ParameterBag $params)
    {
        if($this->isPost())
        {
            $postID = $this->filter('post_id', 'int');

            try {
                /** @var \Terrasphere\Charactermanager\XF\Entity\User $user */
                $user = $this->assertViewableUser($params['user_id']);
            } catch (Exception $e) {
                throw $e;
            }

            $user->fastUpdate('ts_cm_character_sheet_post_id', $postID);
        }

        return $this->redirect($this->buildLink('members', null, ['user_id' => $params['user_id']]));
    }

    public function actionSelectNew(ParameterBag $params)
    {
        /** @var \Terrasphere\Core\Repository\Mastery $masteryRepo */
        $masteryRepo = $this->repository('Terrasphere\Core:Mastery');

        $allMasteries = $masteryRepo->getMasteryListGroupedByClassification();
        $characterMasteries = CharacterMastery::getCharacterMasteries($this, $params['user_id']);
        $masteries = [];

        // Construct new list with invalid masteries removed
        foreach ($allMasteries as $categoryKey => $category)
        {
            // Also remove masteries which are already selected
            $category['masteries'] = $this->stripMasteriesInAFromB($characterMasteries, $category['masteries']);

            if($category['isNormal'])
                $masteries[$categoryKey] = $category;
            else if($this->canSelectMasteryFromCategory($category, $params['user_id']))
                $masteries[$categoryKey] = $category;
        }

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
            // Permission check
            if(!$this->canVisitorEditCharacterSheet($params['user_id']))
                return $this->error("You don't have permission to edit this character.");

            // Mastery slot index bounds check
            if($params['target_index'] < 0 || $params['target_index'] > 4)
                return $this->error('Invalid Mastery index for new mastery selection.');

            $entry = $this->finder('Terrasphere\Charactermanager:CharacterMastery')
                ->where('user_id', $params['user_id'])
                ->where('target_index', $params['target_index'])
                ->fetchOne();

            // Slot already filled check
            if($entry != null)
                return $this->error('Mastery slot is already filled.');

            $input = $this->filter([
                'mastery' => 'uint'
            ]);

            /** @var \Terrasphere\Core\Repository\Mastery $masteryRepo */
            $masteryRepo = $this->repository('Terrasphere\Core:Mastery');
            $mastery = $masteryRepo->getMasteryWithTraitsByID($input['mastery']);

            // Mastery selection existence check
            if($mastery == null)
                return $this->error('Mastery is missing from database; try reloading.');

            $entry = $this->finder('Terrasphere\Charactermanager:CharacterMastery')
                ->where('user_id', $params['user_id'])
                ->where('mastery_id', $input['mastery'])
                ->fetchOne();

            // Mastery already owned check
            if($entry != null)
                return $this->error('Character already has this mastery!');

            $sameTypeMasteries = $this->finder('Terrasphere\Charactermanager:CharacterMastery')
                ->with('Mastery')
                ->where('user_id', $params['user_id'])
                ->where('Mastery.mastery_type_id', $mastery['mastery_type_id'])
                ->total();

            // Mastery type limit check
            if($sameTypeMasteries >= $mastery->MasteryType->cap_per_character)
                return $this->error('You already have the maximum amount of ' . $mastery->MasteryType->name . ' masteries. ('.$sameTypeMasteries.' vs '.$mastery->MasteryType->cap_per_character.')');

            // 4th slot restriction check
            if($params['target_index'] == 3 && !$this->fourthSlotUnlocked($params['user_id']))
                return $this->error('Slot not unlocked.');

            // 5th slot restriction check
            if($params['target_index'] == 4 && !$this->fifthSlotUnlocked($params['user_id']))
                return $this->error('Slot not unlocked.');

            $rank = $this->finder('Terrasphere\Core:Rank')->where('tier', 1)->fetchOne();

            // Save entity to DB
            $slotEntity = $this->em()->create('Terrasphere\Charactermanager:CharacterMastery');
            $slotEntity['mastery_id'] = $mastery['mastery_id'];
            $slotEntity['user_id'] = $params['user_id'];
            $slotEntity['target_index'] = $params['target_index'];
            $slotEntity['rank_id'] = $rank['rank_id'];
            $this->saveMasterySlot($slotEntity)->run();

            // Close overlay via redirect and setup AJAX parameters.
            $redirect = $this->redirect($this->buildLink('members', null, ['user_id' => $params['user_id']]));
            $redirect->setJsonParam('masterySlotIndex', $params['target_index']);
            $redirect->setJsonParam('newMasteryName', $mastery['display_name']);
            $redirect->setJsonParam('newMasteryIconURL', $mastery['icon_url']);
            $redirect->setJsonParam('newMasteryColor', $mastery['color']);
            $redirect->setJsonParam('rankTitle', $rank['name']);
            // TODO Add another for updating cost of upgrade...
            return $redirect;
        }

        return $this->error('Post only operation.');
    }

    public function saveMasterySlot(CharacterMastery $masterySlot): \XF\Mvc\FormAction
    {
        $form = $this->formAction();

        $input = [
            'mastery_id' => $masterySlot['mastery_id'],
            'user_id' => $masterySlot['user_id'],
            'target_index' => $masterySlot['target_index'],
        ];

        $form->basicEntitySave($masterySlot, $input);

        return $form;
    }

    /**
     * Opens upgrade confirmation screen. Does not actually upgrade.
     */
    public function actionConfirmUpgrade(ParameterBag $params)
    {
        // Get character's mastery instance.
        $mastery = $this->finder('Terrasphere\Charactermanager:CharacterMastery')
            ->with('Mastery')
            ->where('user_id', $params['user_id'])
            ->where('target_index', $params['target_index'])
            ->fetchOne();

        // Get the current rank.
        $thisRank = $this->finder('Terrasphere\Core:Rank')
            ->where('rank_id', $mastery['rank_id'])
            ->fetchOne();

        // Rank exists check.
        if($thisRank == null)
            return $this->error('NullRankError.');

        // Get the mastery type for the ranking schema.
        $masteryType = $this->finder('Terrasphere\Core:MasteryType')
            ->where('mastery_type_id', $mastery->Mastery->mastery_type_id)
            ->fetchOne();

        // Type exists check.
        if($masteryType == null)
            return $this->error('Mastery type missing error. Mastery: ' . $mastery['display_name'] . '.');

        // Get the next-highest rank.
        $nextRank = $this->finder('Terrasphere\Core:Rank')
            ->where('tier', $thisRank['tier']+1)
            ->fetchOne();

        // Next-highest rank check.
        if($nextRank == null)
            return $this->error('Already at max rank.');

        // Get rank schema (and the currency associated with it).
        $rankSchema = $this->finder('Terrasphere\Core:RankSchema')
            ->with('Currency')
            ->where('rank_schema_id', $masteryType['rank_schema_id'])
            ->fetchOne();

        // Rank schema exists check.
        if($rankSchema == null)
            return $this->error('No rank schema associated with this mastery type.');

        // Temporary variable to get the cost of the next rank.
        $nxt = $this->finder('Terrasphere\Core:RankSchemaMap')
            ->where('rank_schema_id', $masteryType['rank_schema_id'])
            ->where('rank_id', $nextRank['rank_id'])
            ->fetchOne();

        if($nxt == null)
            return $this->error('No rank schema cost entry for this mastery type and the next tier.');

        $nextCost = $nxt['cost'];
        $user = $this->finder('XF:User')->where('user_id', $params['user_id'])->fetchOne();
        $userVal = $rankSchema->Currency->getValueFromUser($user, false);

        $mastery_slot = $this->em()->create("Terrasphere\Charactermanager:CharacterMastery");
        $mastery_slot['user_id'] = $params['user_id'];
        $mastery_slot['target_index'] = $params['target_index'];


        $viewparams = [
            'thisRank' => $thisRank,
            'nextRank' => $nextRank,
            'rankSchema' => $rankSchema,
            'mastery' => $mastery->Mastery,
            'user' => $user,
            'userVal' => $rankSchema->Currency->getFormattedValue($userVal),
            'nextCost' => $rankSchema->Currency->getFormattedValue($nextCost),
            'afterVal' => $rankSchema->Currency->getFormattedValue($userVal - $nextCost),
            'mastery_slot' => $mastery_slot,
        ];
        return $this->view('Terrasphere\Charactermanager:MasteryUpgradeConfirm', 'terrasphere_cm_confirm_mastery_upgrade', $viewparams);
    }

    public function actionUpgrade(ParameterBag $params)
    {
        $mastery = $this->finder('Terrasphere\Charactermanager:CharacterMastery')
            ->with('Mastery')
            ->where('user_id', $params['user_id'])
            ->where('target_index', $params['target_index'])
            ->fetchOne();

        $thisRank = $this->finder('Terrasphere\Core:Rank')
            ->where('rank_id', $mastery['rank_id'])
            ->fetchOne();

        if($thisRank == null)
            return $this->error('NullRankError.');

        $masteryType = $this->finder('Terrasphere\Core:MasteryType')
            ->where('mastery_type_id', $mastery->Mastery->mastery_type_id)
            ->fetchOne();

        if($masteryType == null)
            return $this->error('Mastery type missing error. Mastery: ' . $mastery['display_name'] . '.');

        $nextRank = $this->finder('Terrasphere\Core:Rank')
            ->where('tier', $thisRank['tier']+1)
            ->fetchOne();

        if($nextRank == null)
            return $this->error('Already at max rank.');

        $rankSchema = $this->finder('Terrasphere\Core:RankSchema')
            ->with('Currency')
            ->where('rank_schema_id', $masteryType['rank_schema_id'])
            ->fetchOne();

        if($rankSchema == null)
            return $this->error('No rank schema associated with this mastery type.');

        $nxt = $this->finder('Terrasphere\Core:RankSchemaMap')
            ->where('rank_schema_id', $masteryType['rank_schema_id'])
            ->where('rank_id', $nextRank['rank_id'])
            ->fetchOne();

        if($nxt == null)
            return $this->error('No rank schema cost entry for this mastery type and the next tier.');

        $nextCost = $nxt['cost'];
        $user = $this->finder('XF:User')->where('user_id', $params['user_id'])->fetchOne();

        $userVal = $rankSchema->Currency->getValueFromUser($user, false);

        if($nextCost > $userVal)
            return $this->error('Error: Insufficient Funds.');

        if($this->adjustCurrency($user, $nextCost, "Mastery Upgrade (".$mastery->Mastery->display_name." Rank ".$nextRank['name'].")", $rankSchema->Currency->currency_id))
        {
            $fourthSlotUnlockedPrev = $this->fourthSlotUnlocked($params['user_id']);
            $fifthSlotUnlockedPrev = $this->fifthSlotUnlocked($params['user_id']);

            // Update mastery entity to next rank.
            $mastery->fastUpdate('rank_id', $nextRank['rank_id']);

            // Close overlay via redirect and setup AJAX parameters.
            $redirect = $this->redirect($this->buildLink('members', null, ['user_id' => $params['user_id']]));
            $redirect->setJsonParam('masterySlotIndex', $params['target_index']);
            $redirect->setJsonParam('newRankTier', $nextRank['tier']);
            $redirect->setJsonParam('newRankTitle', $nextRank['name']);
            $redirect->setJsonParam('max', $nextRank['tier'] == Rank::maxTier($this));
            $redirect->setJsonParam('fourthSlotWasUnlocked', $fourthSlotUnlockedPrev);
            $redirect->setJsonParam('fifthSlotWasUnlocked', $fifthSlotUnlockedPrev);
            $redirect->setJsonParam('fourthSlotUnlocked', $this->fourthSlotUnlocked($params['user_id']));
            $redirect->setJsonParam('fifthSlotUnlocked', $this->fifthSlotUnlocked($params['user_id']));

            return $redirect;
        }

        return $this->error("Currency adjustment error...");
    }

    /**
     * @throws Exception
     */
    public function actionConfirmUpgradeEquip(ParameterBag $params)
    {
        $user_id = $this->filter('user_id', 'uint');
        $equipmentId = $this->filter('equipment_id', 'uint');


        try {
            /** @var \Terrasphere\Charactermanager\XF\Entity\User $user */
            $user = $this->assertViewableUser($user_id);
        } catch (Exception $e) {
            throw $e;
        }

        $charEquip = CharacterEquipment::getEquipment($this,$user_id,$equipmentId);
        $equipment = $charEquip->Equipment;

        // Get the next-highest rank.
        $nextRank = $charEquip->Rank->getNextRank();

        // Next-highest rank check.
        if($nextRank == null)
            return $this->error('Already at max rank.');

        // Get rank schema (and the currency associated with it).
        $rankSchema = $equipment->RankSchema;

        // Rank schema exists check.
        if($rankSchema == null)
            return $this->error('No rank schema set for equipment.');

        // Temporary variable to get the cost of the next rank.
        $nxt = $this->finder('Terrasphere\Core:RankSchemaMap')
            ->where([
                ['rank_schema_id',$rankSchema->rank_schema_id],
                ['rank_id',$nextRank->rank_id]
            ])
            ->fetchOne();

        if($nxt == null)
            return $this->error('Next tier of upgrade has no cost associated in ranking schema.');

        $nextCost = $nxt->cost;
        $userVal = $rankSchema->Currency->getValueFromUser($user, false);

        $viewparams = [
            'name' => $equipment->display_name,
            'iconSrc' => $equipment->icon_url,
            'thisRank' => $charEquip->Rank,
            'nextRank' => $nextRank,
            'rankSchema' => $rankSchema,
            'equipId' => $equipmentId,
            'user' => $user,
            'userVal' => $rankSchema->Currency->getFormattedValue($userVal),
            'nextCost' => $rankSchema->Currency->getFormattedValue($nextCost),
            'afterVal' => $rankSchema->Currency->getFormattedValue($userVal - $nextCost),
        ];
        return $this->view('Terrasphere\Charactermanager:EquipUpgradeConfirm', 'terrasphere_cm_confirm_equip_upgrade', $viewparams);
    }

    public function actionConfirmChangeArmor(ParameterBag $params) {
        $user_id = $this->filter('user_id', 'uint');
        $equipId = $this->filter('equipment_id', 'uint');

        try {
            /** @var \Terrasphere\Charactermanager\XF\Entity\User $user */
            $user = $this->assertViewableUser($user_id);
        } catch (Exception $e) {
            throw $e;
        }

        $charEquip = CharacterEquipment::getEquipment($this,$user_id,$equipId);
        $equipment = $charEquip->Equipment;

        $otherEquip = array_values($this->finder('Terrasphere\Core:Equipment')
            ->where([
                ['equip_group', $equipment->equip_group],
                ['equipment_id', '!=', $equipment->equipment_id],
            ])
            ->fetch()->toArray());

        $rankSchema = $equipment->RankSchema;
        $muns = $this->getRetrofitCost($rankSchema, $charEquip);
        $userVal = $rankSchema->Currency->getValueFromUser($user, false);
        $viewparams = [
            'armor1' => $otherEquip[0],
            'armor2' => $otherEquip[1],
            'charEquip' => $charEquip,
            'thisRank' => $charEquip->Rank,
            'rankSchema' => $rankSchema,
            'user' => $user,
            'userVal' => $rankSchema->Currency->getFormattedValue($userVal),
            'nextCost' => $rankSchema->Currency->getFormattedValue($muns),
            'afterVal' => $rankSchema->Currency->getFormattedValue($userVal-$muns),
        ];
        return $this->view('Terrasphere\Charactermanager:EquipArmorChangeConfirm', 'terrasphere_cm_confirm_armor_selection', $viewparams);
    }

    public function actionChangeArmor(ParameterBag $params)
    {
        $userId = $params['user_id'];
        $equipId = $this->filter('equipment', 'uint');

        try {
            /** @var \Terrasphere\Charactermanager\XF\Entity\User $user */
            $user = $this->assertViewableUser($userId);
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }

        $charEquip = $user->getOrInitiateArmor();
        $equipment = $charEquip->Equipment;

        if($charEquip == null || $equipment == null)
            return $this->error("No current equipment found. This shouldn't happen. Please contact an admin for help.");

        $targetArmor = $this->finder("Terrasphere\Core:Equipment")->whereId($equipId)->fetchOne();

        if($targetArmor == null)
            return $this->error("Target armor doesn't exist.");
        if($targetArmor->equip_group != $equipment->equip_group)
            return $this->error("Tried to switch from armor to non-armor equipment (or the opposite).");

        $rankSchema = $equipment->RankSchema;

        if($rankSchema == null)
            return $this->error("No associated rank schema with current equipment when changing armor type.");

        $cost = $this->getRetrofitCost($rankSchema, $charEquip);

        if($this->adjustCurrency($user, $cost, "Armor Retrofit (".$targetArmor->display_name.")", $rankSchema->Currency->currency_id))
        {
            // Change type.
            $charEquip->fastUpdate('equipment_id', $equipId);

            // Close overlay via redirect and setup AJAX parameters.
            $redirect = $this->redirect($this->buildLink('members', $user, ['user_id' => $params['user_id']]));
            $redirect->setJsonParam('newIconURL', $targetArmor['icon_url']);
            $redirect->setJsonParam('newName', $targetArmor['display_name']);

            return $redirect;
        }

        return $this->error("Currency adjustment error...");
    }

    public function actionUpgradeEquip(ParameterBag $params)
    {
        $user_id = $this->filter('user_id', 'uint');
        $equipId = $this->filter('equip_id', 'uint');


        try {
            /** @var \Terrasphere\Charactermanager\XF\Entity\User $user */
            $user = $this->assertViewableUser($user_id);
        } catch (Exception $e) {
            throw $e;
        }

        $charEquip = CharacterEquipment::getEquipment($this,$user_id,$equipId);
        $equipment = $charEquip->Equipment;

        // Get the next-highest rank.
        $nextRank = $charEquip->Rank->getNextRank();

        // Next-highest rank check.
        if($nextRank == null)
            return $this->error('Already at max rank.');

        // Get rank schema (and the currency associated with it).
        $rankSchema = $equipment->RankSchema;

        // Rank schema exists check.
        if($rankSchema == null)
            return $this->error('No rank schema set for equipment.');

        // Temporary variable to get the cost of the next rank.
        $nxt = $this->finder('Terrasphere\Core:RankSchemaMap')
            ->where([
                ['rank_schema_id',$rankSchema->rank_schema_id],
                ['rank_id',$nextRank->rank_id]
            ])
            ->fetchOne();

        if($nxt == null)
            return $this->error('Next tier of upgrade has no cost associated in ranking schema.');

        $nextCost = $nxt->cost;
        $userVal = $rankSchema->Currency->getValueFromUser($user, false);

        if($nextCost > $userVal)
            return $this->error('Error: Insufficient Funds.');

        if($this->adjustCurrency($user, $nextCost, "Equipment Upgrade (".$equipment->display_name." Rank ".$nextRank->name.")", $rankSchema->Currency->currency_id))
        {
            // Update user's equipment data to next rank.
            $charEquip->fastUpdate("rank_id", $nextRank->rank_id);

            // Close overlay via redirect and setup AJAX parameters.
            $redirect = $this->redirect($this->buildLink('members', null, ['user_id' => $params['user_id']]));

            $redirect->setJsonParam('equipId',$equipment->equipment_id);
            $redirect->setJsonParam('newRankTier', $nextRank['tier']);
            $redirect->setJsonParam('newRankTitle', $nextRank['name']);
            $redirect->setJsonParam('equipType', $equipment->equip_group);
            $redirect->setJsonParam('isMaxRank', $nextRank['tier'] == Rank::maxTier($this));

            return $redirect;
        }

        return $this->error("Currency adjustment error...");
    }

    public function canVisitorViewCharacterSheet(int $userID): bool {
        return true;
    }

    public function canVisitorViewRevisions(int $userID): bool {
        return \XF::visitor()->hasPermission('terrasphere', 'terrasphere_cm_review')
            || $this->visitorIsUser($userID);
    }

    public function canVisitorEditCharacterSheet(int $userID): bool {
        return $this->visitorIsUser($userID); // TODO || Edit permissions!
    }

    public function canVisitorMakeChanges(int $userID): bool {
        return $this->visitorIsUser($userID);
    }

    private function visitorIsUser(int $userID): bool {
        return \XF::visitor()->user_id == $userID;
    }

    private function fourthSlotUnlocked(int $userID): bool {
        $cAndHigherMasteries = $this->finder('Terrasphere\Charactermanager:CharacterMastery')
            ->with('Rank')
            ->where('user_id', $userID)
            ->where('Rank.tier', '>', 1)
            ->total();

        return $cAndHigherMasteries >= 3;
    }

    private function fifthSlotUnlocked(int $userID): bool {
        return $this->finder('Terrasphere\Charactermanager:CharacterMastery')
            ->with('Rank')
            ->where('user_id', $userID)
            ->where('Rank.tier', '>', 4)
            ->fetchOne() != null;
    }

    private function canSelectMasteryFromCategory(array $masteryGroup, int $userID): bool
    {
        // The answer is obviously 'no' if the group is empty
        if(count($masteryGroup['masteries']) == 0)
            return false;

        $masteryList = $masteryGroup['masteries'];
        $firstMastery = $masteryList[array_keys($masteryList)[0]];

        $typeID = $firstMastery->mastery_type_id;
        $typeCap = $firstMastery->MasteryType->cap_per_character;

        $characterMasteries = CharacterMastery::getCharacterMasteries($this, $userID);
        $amountOfThisType = 0;
        foreach ($characterMasteries as $m)
        {
            if($m->Mastery->mastery_type_id == $typeID)
            {
                $amountOfThisType++;
                if($amountOfThisType >= $typeCap)
                    return false;
            }
        }

        return true;
    }

    #[Pure]
    private function stripMasteriesInAFromB(array $a, array $b): array
    {
        $ret = [];
        foreach ($b as $mastery)
        {
            $match = false;
            foreach ($a as $m)
            {
                if($mastery['mastery_id'] == $m['mastery_id'])
                {
                    $match = true;
                    break;
                }
            }

            if(!$match) $ret[$mastery['mastery_id']] = $mastery;
        }
        return $ret;
    }

    private function adjustCurrency(User $user, int $cost, string $message, $currencyID)
    {
        $this->assertPostOnly();

        /** @var \DBTech\Credits\Entity\Currency $currency */
        $currency = $this->assertRecordExists('DBTech\Credits:Currency', $currencyID, null, null);

        $input = [
            'username' => $user['username'],
            'amount' => $cost,
            'message' => $message,
            'negate' => true
        ];

        /** @var \DBTech\Credits\EventTrigger\Adjust $adjustEvent */
        $adjustEvent = $this->repository('DBTech\Credits:EventTrigger')->getHandler('adjust');
        if (!$adjustEvent->isActive())
        {
            return $this->error(\XF::phrase('dbtech_credits_invalid_eventtrigger'));
        }

        // Make sure this is set
        $currency->verifyAdjustEvent();

        $message = $input['message'];
        $multiplier = $input['negate'] ? (-1 * $input['amount']) : $input['amount'];

        // First test add or remove credits
        $adjustEvent->testApply([
            'currency_id' => $currency->currency_id,
            'multiplier' => $multiplier,
            'message' => $message,
            'source_user_id' => $user->user_id,
        ], $user);

        // Then properly add or remove credits
        $adjustEvent->apply($user->user_id, [
            'currency_id' => $currency->currency_id,
            'multiplier' => $multiplier,
            'message' => $message,
            'source_user_id' => $user->user_id,
        ], $user);

        return true;
    }

    private function getRetrofitCost($rankSchema, $charEquip) : int
    {
        $rankSchemaCost = $this
            ->finder('Terrasphere\Core:RankSchemaMap')
            ->with('Rank')
            ->where([
                ['rank_schema_id', $rankSchema->rank_schema_id],
                ['Rank.tier', '<=', $charEquip->Rank->tier],
            ])
            ->fetch()->toArray();

        $cost = 0;
        foreach($rankSchemaCost as $value) { $cost += $value->cost; }
        return $cost * 0.1;
    }
}