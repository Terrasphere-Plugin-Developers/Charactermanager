<?php

namespace Terrasphere\Charactermanager\XF\Pub\Controller;

use DBTech\Credits\Entity\Currency;
use Terrasphere\Charactermanager\Entity\CharacterEquipment;
use Terrasphere\Charactermanager\Entity\CharacterMastery;
use Terrasphere\Charactermanager\Entity\CharacterRaceTrait;
use Terrasphere\Charactermanager\Repository\RacialTraits;
use Terrasphere\Core\Entity\Rank;
use Terrasphere\Core\Repository\Mastery;
use Terrasphere\Core\Util\PostProxyHelper;
use XF\Entity\User;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\Exception;

class Member extends XFCP_Member
{
    /**
     * @throws Exception
     */
    public function actionCharacterSheet(ParameterBag $params)
	{
        /** @var \Terrasphere\Charactermanager\XF\Entity\User $user */
        $user = $this->assertViewableUser($params['user_id']);

        $masterySlots = CharacterMastery::getCharacterMasterySlots($this, $params->user_id);
        $weapon = $user->getOrInitiateWeapon();

        $armor = $user->getOrInitiateArmor();

        /** @var RacialTraits $raceTraitRepo */
        $raceTraitRepo = $this->repository('Terrasphere\Charactermanager:RacialTraits');

        /** @var Currency $raceTraitCurrency */
        $raceTraitCurrency = $this->assertRecordExists('DBTech\Credits:Currency', $this->options()['terrasphereRaceTraitCurrency'], null, null);
        $traitCurrencyVal = $raceTraitCurrency->getValueFromUser($user, false);
        $traitCost = $raceTraitRepo->getRaceTraitCostForUser($user);

        $maxRank = Rank::maxRank($this);

		$viewParams = [
		    'user' => $user,
		    'masterySlots' => $masterySlots,
            'weapon' => $weapon,
            'armor' => $armor,
            'raceTraitSlots' => $raceTraitRepo->getRacialTraitSlotsForUser($user),
		    'maxRank' => $maxRank['rank_id'],
            'hasCS' => $user['ts_cm_character_sheet_post_id'] != -1,
            'hasBuildDesc' => $user['ts_cm_character_sheet_build_post_id'] != -1,
            'canViewCS' => $this->canVisitorViewCharacterSheet($params->user_id),
            'canViewRevisions' => $this->canVisitorViewRevisions($params->user_id),
            'canMakeChanges' => $this->canVisitorMakeChanges($params->user_id),
            'fourthSlotUnlocked' => $this->fourthSlotUnlocked($params->user_id),
            'fifthSlotUnlocked' => $this->fifthSlotUnlocked($params->user_id),
            'hasFundsForRacialTrait' => ($traitCurrencyVal >= $traitCost),
            'amountForRacial' => $traitCost,
            'racialCurrencyName' => $raceTraitCurrency->title,
        ];

		if($viewParams['hasCS'])
		    $viewParams = array_merge($viewParams, ['csPost' => PostProxyHelper::getPostProxyParams($this, $user['ts_cm_character_sheet_post_id'])] );

        if($viewParams['hasBuildDesc'])
            $viewParams = array_merge($viewParams, ['csBuildDesc' => PostProxyHelper::getPostProxyParams($this, $user['ts_cm_character_sheet_build_post_id'])] );

		return $this->view('XF:Member\CharacterSheet', 'terrasphere_cm_character_sheet', $viewParams);
	}
    /**
     * @throws Exception
     */
    public function actionCharacterThread(ParameterBag $params)
    {
        /** @var \Terrasphere\Charactermanager\XF\Entity\User $user */
        $user = $this->assertViewableUser($params['user_id']);

        $postAndThread = PostProxyHelper::getPostProxyParams($this, $user['ts_cm_character_sheet_post_id']);
        if($postAndThread == null) return $this->message("User hasn't created a character thread yet (or it's awaiting approval).");

        return $this->redirectPermanently('threads/'.$postAndThread['thread']['thread_id']);
    }

    /**
     * @throws Exception
     */
    public function actionLinkCs(ParameterBag $params)
    {
        /** @var \Terrasphere\Charactermanager\XF\Entity\User $user */
        $user = $this->assertViewableUser($params['user_id']);

        return $this->view('XF:Member\LinkCS', 'terrasphere_cm_cs_link_page', ['user' => $user]);
    }

    /**
     * @throws Exception
     */
    public function actionLinkCSConfirm(ParameterBag $params)
    {
        if($this->isPost())
        {
            if(!$this->doesVisitorHaveCharacterModPowers())
                return $this->error("You lack permission to link posts.");

            $vals = $this->filter([
                'post_id' => 'int',
                'desc_post_id' => 'int',
            ]);

            /** @var \Terrasphere\Charactermanager\XF\Entity\User $user */
            $user = $this->assertViewableUser($params['user_id']);

            $user->fastUpdate('ts_cm_character_sheet_post_id', $vals['post_id']);
            $user->fastUpdate('ts_cm_character_sheet_build_post_id', $vals['desc_post_id']);
        }

        return $this->redirect($this->buildLink('members', null, ['user_id' => $params['user_id']]));
    }

    public function actionSelectNew(ParameterBag $params)
    {
        /** @var Mastery $masteryRepo */
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

            /** @var Mastery $masteryRepo */
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

            $rank = Rank::minRank($this);

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
     * @throws Exception
     */
    public function actionSelectNewTrait(ParameterBag $params)
    {
        $userID = $this->filter('user_id', 'uint');
        $slotIndex = $this->filter('slot_index', 'uint');

        /** @var RacialTraits $repo */
        $repo = $this->repository('Terrasphere\Charactermanager:RacialTraits');

        /** @var \Terrasphere\Charactermanager\XF\Entity\User $user */
        $user = $this->assertViewableUser($userID);

        $traits = $repo->getTraitSelectionsAvailableForUser($user);

        $dummySlot = $this->em()->create('Terrasphere\Charactermanager:CharacterRaceTrait');
        $dummySlot['user_id'] = $userID;
        $dummySlot['slot_index'] = $slotIndex;

        /** @var Currency $currency */
        $currency = $this->assertRecordExists('DBTech\Credits:Currency', $this->options()['terrasphereRaceTraitCurrency'], null, null);
        $currentVal = $currency->getValueFromUser($user, false);
        $traitCost = $repo->getRaceTraitCostForUser($user);

        $viewparams = [
            'traits' => $traits,
            'slot' => $dummySlot,
            'currencyName' => $currency->title,
            'currencyCost' => $traitCost,
            'currencyCurrent' => (int) $currentVal,
            'currencyAfter' => (int) $currentVal - $traitCost,
        ];

        return $this->view('Terrasphere\Charactermanager:TraitSelection', 'terrasphere_cm_confirm_trait_select', $viewparams);
    }

    public function actionSaveNewTrait(ParameterBag $params)
    {
        if($this->isPost())
        {
            /** @var \Terrasphere\Charactermanager\XF\Entity\User $user */
            $user = $this->assertViewableUser($params['user_id']);

            // Permission check
            if(!$this->canVisitorEditCharacterSheet($params['user_id']))
                return $this->error("You don't have permission to edit this character.");

            // Mastery slot index bounds check
            if($params['slot_index'] < 0 || $params['slot_index'] > $this->options()['terrasphereCMMaxRaceTraits'] - 1)
                return $this->error('Invalid slot index.');

            $input = $this->filter([
                'trait' => 'uint'
            ]);

            $entry = $this->finder('Terrasphere\Charactermanager:CharacterRaceTrait')
                ->where('user_id', $params['user_id'])
                ->whereOr([['slot_index', $params['slot_index']], ['race_trait_id', $input['trait']]])
                ->fetchOne();

            // Slot already filled or trait already chosen check.
            if($entry != null)
                return $this->error('Slot is already filled or trait is already chosen.');

            /** @var RacialTraits $repo */
            $repo = $this->repository('Terrasphere\Charactermanager:RacialTraits');
            $traitsAvailable = $repo->getTraitSelectionsAvailableForUser($user);

            $trait = null;
            foreach ($traitsAvailable as $t)
            {
                if($t['race_trait_id'] == $input['trait'])
                {
                    $trait = $t;
                    break;
                }
            }

            if($trait == null)
            {
                $str = "";
                foreach ($traitsAvailable as $t) $str = $str . " {" . $t['name'] . " - " . $t['race_trait_id'] . "}";
                return $this->error("Trait missing from database (or user groups may be broken). IDs:".$str);
            }

            $traitCost = $repo->getRaceTraitCostForUser($user);
            if($traitCost > 0)
            {
                $this->adjustCurrency($user, $traitCost, "Purchased Race Trait: ".$trait['name'], $this->options()['terrasphereRaceTraitCurrency']);
            }

            // Save entity to DB
            $slotEntity = $this->em()->create('Terrasphere\Charactermanager:CharacterRaceTrait');
            $slotEntity['race_trait_id'] = $trait['race_trait_id'];
            $slotEntity['user_id'] = $user['user_id'];
            $slotEntity['slot_index'] = $params['slot_index'];
            /** @var CharacterRaceTrait $slotEntity */
            $this->saveTraitSlot($slotEntity)->run();

            // Close overlay via redirect and setup AJAX parameters.
            $redirect = $this->redirect($this->buildLink('members', $user, ['user_id' => $params['user_id']]));
            $redirect->setJsonParam('slotIndex', $params['slot_index']);
            $redirect->setJsonParam('newName', $trait['name']);
            $redirect->setJsonParam('newIconURL', $trait['icon_url']);
            return $redirect;
        }

        return $this->error('Post only operation.');
    }

    public function saveTraitSlot(CharacterRaceTrait $slot): \XF\Mvc\FormAction
    {
        $form = $this->formAction();

        $input = [
            'race_trait_id' => $slot['race_trait_id'],
            'user_id' => $slot['user_id'],
            'slot_index' => $slot['slot_index'],
        ];

        $form->basicEntitySave($slot, $input);

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
        // Permission check
        if(!$this->canVisitorEditCharacterSheet($params['user_id']))
            return $this->error("You don't have permission to edit this character.");

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
    * Opens prestige confirmation screen.
    */
    public function actionConfirmPrestige(ParameterBag $params)
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
        // Make sure it's maxed.
        if($thisRank['tier'] < Rank::maxRank($this)['tier'])
            return $this->error('Must be max tier to prestige.');

        // Get rank schema (and the currency associated with it).
        $rankSchema = $mastery->Mastery->getRankSchema();

        // Rank schema exists check.
        if($rankSchema == null)
            return $this->error('No rank schema associated with this mastery type.');

        $user = $this->finder('XF:User')->where('user_id', $params['user_id'])->fetchOne();
        $userVal = $rankSchema->Currency->getValueFromUser($user, false);

        $mastery_slot = $this->em()->create("Terrasphere\Charactermanager:CharacterMastery");
        $mastery_slot['user_id'] = $params['user_id'];
        $mastery_slot['target_index'] = $params['target_index'];


        $viewparams = [
            'rankSchema' => $rankSchema,
            'prestige' => $mastery['overrank'],
            'mastery' => $mastery->Mastery,
            'user' => $user,
            'userVal' => $rankSchema->Currency->getFormattedValue($userVal),
            'mastery_slot' => $mastery_slot,
        ];
        return $this->view('Terrasphere\Charactermanager:MasteryPrestigeConfirm', 'terrasphere_cm_confirm_mastery_prestige', $viewparams);
    }
    /**
     * Opens prestige confirmation screen.
     */
    public function actionPrestige(ParameterBag $params)
    {
        return $this->error("Nope.");
        // Permission check
        if(!$this->canVisitorEditCharacterSheet($params['user_id']))
            return $this->error("You don't have permission to edit this character.");

        $params['amount'] = $this->filter('amount', 'int');

        // Negative value.
        if($params['amount'] <= 0)
            return $this->error("Amount must be positive.");

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
        // Make sure it's maxed.
        if($thisRank['tier'] < Rank::maxRank($this)['tier'])
            return $this->error('Must be max tier to prestige.');

        // Get rank schema (and the currency associated with it).
        $rankSchema = $mastery->Mastery->getRankSchema();

        // Rank schema exists check.
        if($rankSchema == null)
            return $this->error('No rank schema associated with this mastery type.');

        $user = $this->finder('XF:User')->where('user_id', $params['user_id'])->fetchOne();
        $userVal = $rankSchema->Currency->getValueFromUser($user, false);

        //$mastery_slot = $this->em()->create("Terrasphere\Charactermanager:CharacterMastery");
        //$mastery_slot['user_id'] = $params['user_id'];
        //$mastery_slot['target_index'] = $params['target_index'];

        if($params['amount'] > $userVal)
            return $this->error('Error: Insufficient Funds.');

        if($this->adjustCurrency($user, $params['amount'], "Prestige (".$mastery->Mastery->display_name." +".$params['amount'].")", $rankSchema->Currency->currency_id))
        {
            // Update character mastery entity.
            $mastery->fastUpdate('overrank', $mastery['overrank'] + $params['amount']);

            // Close overlay via redirect and setup AJAX parameters.
            $redirect = $this->redirect($this->buildLink('members', null, ['user_id' => $params['user_id']]));
            $redirect->setJsonParam('masterySlotIndex', $params['target_index']);

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
        $equipGroup = $this->filter('equipment_group', 'str');


        try {
            /** @var \Terrasphere\Charactermanager\XF\Entity\User $user */
            $user = $this->assertViewableUser($user_id);
        } catch (Exception $e) {
            throw $e;
        }

        $charEquip = CharacterEquipment::getEquipmentByGroup($this,$user_id,$equipGroup);
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
            'equipId' => $equipment->equipment_id,
            'user' => $user,
            'userVal' => $rankSchema->Currency->getFormattedValue($userVal),
            'nextCost' => $rankSchema->Currency->getFormattedValue($nextCost),
            'afterVal' => $rankSchema->Currency->getFormattedValue($userVal - $nextCost),
        ];
        return $this->view('Terrasphere\Charactermanager:EquipUpgradeConfirm', 'terrasphere_cm_confirm_equip_upgrade', $viewparams);
    }

    public function actionConfirmChangeArmor(ParameterBag $params) {
        $user_id = $this->filter('user_id', 'uint');
        $equipGroup = $this->filter('equipment_group', 'str');

        try {
            /** @var \Terrasphere\Charactermanager\XF\Entity\User $user */
            $user = $this->assertViewableUser($user_id);
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }

        $charEquip = CharacterEquipment::getEquipmentByGroup($this,$user_id,$equipGroup);
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

        // Permission check
        if(!$this->canVisitorEditCharacterSheet($userId))
            return $this->error("You don't have permission to edit this character.");

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
        $userVal = $rankSchema->Currency->getValueFromUser($user, false);

        if($cost > $userVal)
            return $this->error('Error: Insufficient Funds.');

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

        // Permission check
        if(!$this->canVisitorEditCharacterSheet($user_id))
            return $this->error("You don't have permission to edit this character.");

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
            $redirect->setJsonParam('equipGroup', $equipment->equip_group);

            return $redirect;
        }

        return $this->error("Currency adjustment error...");
    }

    public function canVisitorViewCharacterSheet(int $userID): bool {
        return true;
    }

    public function doesVisitorHaveCharacterModPowers(): bool {
        return \XF::visitor()->hasPermission('terrasphere', 'terrasphere_cm_review');
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

        /** @var Currency $currency */
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