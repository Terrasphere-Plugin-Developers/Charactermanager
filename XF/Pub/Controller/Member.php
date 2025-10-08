<?php

namespace Terrasphere\Charactermanager\XF\Pub\Controller;

use DBTech\Credits\Entity\Currency;
use Terrasphere\Charactermanager\Entity\CharacterEquipment;
use Terrasphere\Charactermanager\Entity\CharacterExpertise;
use Terrasphere\Charactermanager\Entity\CharacterMastery;
use Terrasphere\Charactermanager\Entity\CharacterRaceTrait;
use Terrasphere\Charactermanager\Repository\RacialTraits;
use Terrasphere\Charactermanager\Helper\CharacterSheetHelper;
use Terrasphere\Core\Entity\Rank;
use Terrasphere\Core\Repository\Expertise;
use Terrasphere\Core\Repository\Mastery;
use Terrasphere\Core\Util\PostProxyHelper;
use XF\Entity\User;
use XF\Mvc\FormAction;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\Error;
use XF\Mvc\Reply\Exception;
use XF\Mvc\Reply\Redirect;
use XF\Mvc\Reply\View;

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
        $expertiseSlots = CharacterExpertise::getCharacterExpertiseSlots($this, $params->user_id);

        $weapon = $user->getOrInitiateWeapon();
        $armor = $user->getOrInitiateArmor();
        $accessory = $user->getOrInitiateAccessory();

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
            'expertiseSlots' => $expertiseSlots,
            'weapon' => $weapon,
            'armor' => $armor,
            'accessory' => $accessory,
            'raceTraitSlots' => $raceTraitRepo->getRacialTraitSlotsForUser($user),
		    'maxRank' => $maxRank['rank_id'],
            'hasCS' => $user['ts_cm_character_sheet_post_id'] != -1,
            'hasBuildDesc' => $user['ts_cm_character_sheet_build_post_id'] != -1,
            'canViewCS' => $this->canVisitorViewCharacterSheet($params->user_id),
            'canViewRevisions' => $this->canVisitorViewRevisions($params->user_id),
            'canMakeChanges' => $this->canVisitorMakeChanges($params->user_id),
            'unlockedMasterySlots' => CharacterMastery::unlockedMasterySlots($this, $params->user_id),
            'unlockedExpertiseSlots' => CharacterExpertise::unlockedExpertiseSlots($this, $params->user_id),
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
     * Redirects to the character thread for a user.
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
     * Opens confirmation screen for linking a character sheet. Does not link yet.
     * @throws Exception
     */
    public function actionLinkCs(ParameterBag $params)
    {
        /** @var \Terrasphere\Charactermanager\XF\Entity\User $user */
        $user = $this->assertViewableUser($params['user_id']);

        return $this->view('XF:Member\LinkCS', 'terrasphere_cm_cs_link_page', ['user' => $user]);
    }

    /**
     * Links a character sheet after confirmation is set
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




    /**
     * Opens confirmation screen for selecting new mastery. Does not select the mastery yet.
     * @param ParameterBag $params
     * @return View
     */
    public function actionSelectNewMastery(ParameterBag $params): View
    {
        /** @var Mastery $masteryRepo */
        $masteryRepo = $this->repository('Terrasphere\Core:Mastery');

        $allMasteries = $masteryRepo->getMasteryListGroupedByClassification();
        $characterMasteries = CharacterMastery::getCharacterMasteries($this, $params['user_id']);
        $masteries = [];

        // Construct new list with invalid masteries removed
        foreach ($allMasteries as $categoryKey => $category)
        {
            // Remove masteries which are already selected
            $category['masteries'] = $this->stripMasteriesInAFromB($characterMasteries, $category['masteries']);

//            if($category['isNormal'])
//                $masteries[$categoryKey] = $category;
//            else
            if(CharacterMastery::canSelectMasteryFromCategory($this, $category, $params['user_id'], $params['target_index']))
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

    /**
     * Adds new mastery to the specified slot after confirmation has been clicked.
     * @param ParameterBag $params
     * @return Error|Redirect
     * @throws \XF\PrintableException
     */
    public function actionSaveNewMastery(ParameterBag $params)
    {
        if($this->isPost())
        {
            // Permission check
            if(!$this->canVisitorEditCharacterSheet($params['user_id']))
                return $this->error("You don't have permission to edit this character.");

            $unlockedMasterySlots = CharacterMastery::unlockedMasterySlots($this, $params->user_id);

            // Mastery slot index bounds check
            if($params['target_index'] < 0 || $params['target_index'] >= count($unlockedMasterySlots))
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

            // Slot unlock restriction check
            if($params['target_index'] >= count($unlockedMasterySlots) || $unlockedMasterySlots[$params['target_index']] === 0)
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

    /**
     * Appears to be a helper action for saving a mastery slot.
     * @param CharacterMastery|array $masterySlot
     * @return FormAction
     */
    public function saveMasterySlot(CharacterMastery $masterySlot): FormAction
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
     * Opens confirmation screen for selecting new mastery. Does not select the mastery yet.
     * @param ParameterBag $params
     * @return View
     */
    public function actionSelectNewExpertise(ParameterBag $params): View
    {
        /** @var Expertise $expertiseRepo */
        $expertiseRepo = $this->repository('Terrasphere\Core:Expertise');

        $allExpertise = $expertiseRepo->getExpertiseList();
        $characterExpertise = CharacterExpertise::getCharacterExpertises($this, $params['user_id']);
        $expertises = [];

        // Construct a new list with invalid masteries removed
        foreach ($allExpertise as $categoryKey => $category)
        {
            // Also remove masteries which are already selected
            $category['expertises'] = $this->stripExpertisesInAFromB($characterExpertise, $category['expertises']);

            if($category['isNormal'])
                $expertises[$categoryKey] = $category;
            else if(CharacterExpertise::canSelectFromCategory($this, $category, $params['user_id']))
                $expertises[$categoryKey] = $category;
        }

        $dummySlot = $this->em()->create('Terrasphere\Charactermanager:CharacterExpertise');
        $dummySlot['user_id'] = $params['user_id'];
        $dummySlot['target_index'] = $params['target_index'];

        $viewparams = [
            'expertises' => $expertises,
            'expertiseSlot' => $dummySlot,
        ];

        return $this->view('Terrasphere\Charactermanager:ExpertiseSelection', 'terrasphere_cm_expertise_selection', $viewparams);
    }

    /**
     * Adds new mastery to the specified slot after confirmation has been clicked.
     * @param ParameterBag $params
     * @return Error|Redirect
     * @throws \XF\PrintableException
     */
    public function actionSaveNewExpertise(ParameterBag $params)
    {
        if($this->isPost())
        {
            // Permission check
            if(!$this->canVisitorEditCharacterSheet($params['user_id']))
                return $this->error("You don't have permission to edit this character.");

            $unlockedExpertiseSlots = CharacterExpertise::getCharacterExpertiseSlots($this, $params->user_id);

            // Slot index bounds check
            if($params['target_index'] < 0 || $params['target_index'] >= count($unlockedExpertiseSlots))
                return $this->error('Invalid Expertise index for new selection.');

            $entry = $this->finder('Terrasphere\Charactermanager:CharacterExpertise')
                ->where('user_id', $params['user_id'])
                ->where('target_index', $params['target_index'])
                ->fetchOne();

            // Slot already filled check
            if($entry != null)
                return $this->error('Expertise slot is already filled.');

            $input = $this->filter([
                'expertise' => 'uint'
            ]);

            /** @var Expertise $expertiseRepo */
            $expertiseRepo = $this->repository('Terrasphere\Core:Expertise');
            $expertise = $expertiseRepo->getExpertiseWithTraitsByID($input['expertise']);

            // Mastery selection existence check
            if($expertise == null)
                return $this->error('Expertise is missing from database (probably removed). Please refresh the page.');;

            $entry = $this->finder('Terrasphere\Charactermanager:CharacterExpertise')
                ->where('user_id', $params['user_id'])
                ->where('expertise_id', $input['expertise'])
                ->fetchOne();

            // Expertise already owned check
            if($entry != null)
                return $this->error('Character already has this expertise!');

            $sameTypeMasteries = $this->finder('Terrasphere\Charactermanager:CharacterExpertise')
                ->with('Expertise')
                ->where('user_id', $params['user_id'])
//                ->where('Expertise.expertise_type_id', $expertise['expertise_type_id'])
                ->total();

            // Mastery type limit check
//            if($sameTypeMasteries >= $mastery->MasteryType->cap_per_character)
//                return $this->error('You already have the maximum amount of ' . $mastery->MasteryType->name . ' masteries. ('.$sameTypeMasteries.' vs '.$mastery->MasteryType->cap_per_character.')');

            // Slot unlock restriction check
            if($params['target_index'] >= count($unlockedExpertiseSlots) || $unlockedExpertiseSlots[$params['target_index']] === 0)
                return $this->error('Slot not unlocked.');

            $rank = Rank::minRank($this);

            // Save entity to DB
            $slotEntity = $this->em()->create('Terrasphere\Charactermanager:CharacterExpertise');
            $slotEntity['expertise_id'] = $expertise['expertise_id'];
            $slotEntity['user_id'] = $params['user_id'];
            $slotEntity['target_index'] = $params['target_index'];
            $slotEntity['rank_id'] = $rank['rank_id'];
            $this->saveExpertiseSlot($slotEntity)->run();

            // Close overlay via redirect and setup AJAX parameters.
            $redirect = $this->redirect($this->buildLink('members', null, ['user_id' => $params['user_id']]));
            $redirect->setJsonParam('expertiseSlotIndex', $params['target_index']);
            $redirect->setJsonParam('newExpertiseName', $expertise['display_name']);
            $redirect->setJsonParam('newExpertiseIconURL', $expertise['icon_url']);
            $redirect->setJsonParam('newExpertiseColor', $expertise['color']);
            $redirect->setJsonParam('rankTitle', $rank['name']);
            // TODO Add another for updating cost of upgrade...
            return $redirect;
        }

        return $this->error('Post only operation.');
    }

    /**
     * Appears to be a helper action for saving a expertise slot.
     * @param CharacterExpertise|array $expertiseSlot
     * @return FormAction
     */
    public function saveExpertiseSlot(CharacterExpertise $expertiseSlot): FormAction
    {
        $form = $this->formAction();

        $input = [
            'expertise_id' => $expertiseSlot['expertise_id'],
            'user_id' => $expertiseSlot['user_id'],
            'target_index' => $expertiseSlot['target_index'],
        ];

        $form->basicEntitySave($expertiseSlot, $input);

        return $form;
    }




    /**
     * Opens expertise upgrade confirmation screen. Does not upgrade yet.
     */
    public function actionConfirmUpgradeExpertise(ParameterBag $params)
    {
        $validation = CharacterSheetHelper::validateExpertiseUpgrade($this, $params);
        if(gettype($validation) == gettype($this->error('Error')))
            return $validation; // If we received an error, return it.

        $expertise_slot = $this->em()->create("Terrasphere\Charactermanager:CharacterExpertise");
        $expertise_slot['user_id'] = $params['user_id'];
        $expertise_slot['target_index'] = $params['target_index'];

        $viewparams = [
            'thisRank' => $validation['thisRank'],
            'nextRank' => $validation['nextRank'],
            'rankSchema' => $validation['rankSchema'],
            'expertise' => $validation['expertise']->Expertise,
            'user' => $validation['user'],
            'userVal' => $validation['rankSchema']->Currency->getFormattedValue($validation['userVal']),
            'nextCost' => $validation['rankSchema']->Currency->getFormattedValue($validation['nextCost']),
            'afterVal' => $validation['rankSchema']->Currency->getFormattedValue($validation['userVal'] - $validation['nextCost']),
            'expertise_slot' => $expertise_slot,
        ];
        return $this->view('Terrasphere\Charactermanager:ExpertiseUpgradeConfirm', 'terrasphere_cm_confirm_expertise_upgrade', $viewparams);
    }

    /**
     * Upgrades expertise after confirmation has been clicked.
     */
    public function actionUpgradeExpertise(ParameterBag $params)
    {
        // Permission check
        if(!$this->canVisitorEditCharacterSheet($params['user_id']))
            return $this->error("You don't have permission to edit this character.");

        $validation = CharacterSheetHelper::validateExpertiseUpgrade($this, $params);
        if(gettype($validation) == gettype($this->error('Error')))
            return $validation; // If we received an error, return it.

        if($validation['nextCost'] > $validation['userVal'])
            return $this->error('Error: Insufficient Funds.');

        if($this->adjustCurrency($validation['user'], $validation['nextCost'], "Expertise Upgrade (".$validation['expertise']->Expertise['display_name']." Rank ".$validation['nextRank']['name'].")", $validation['rankSchema']->Currency['currency_id']))
        {
            $previousUnlockedExpertiseSlots = CharacterExpertise::unlockedExpertiseSlots($this, $params['user_id']);

            // Update expertise entity to next rank.
            $validation['expertise']->fastUpdate('rank_id', $validation['nextRank']['rank_id']);

            // Close overlay via redirect and setup AJAX parameters.
            $redirect = $this->redirect($this->buildLink('members', null, ['user_id' => $params['user_id']]));
            $redirect->setJsonParam('expertiseSlotIndex', $params['target_index']);
            $redirect->setJsonParam('newRankTier', $validation['nextRank']['tier']);
            $redirect->setJsonParam('newRankTitle', $validation['nextRank']['name']);
            $redirect->setJsonParam('max', $validation['nextRank']['tier'] == Rank::maxTier($this));
            $redirect->setJsonParam('previousUnlockedExpertiseSlots', $previousUnlockedExpertiseSlots);
            $redirect->setJsonParam('unlockedExpertiseSlots', CharacterExpertise::unlockedExpertiseSlots($this, $params['user_id']));

            return $redirect;
        }

        return $this->error("Currency adjustment error...");
    }




    /**
     * Opens upgrade confirmation screen. Does not upgrade yet.
     */
    public function actionConfirmUpgradeMastery(ParameterBag $params)
    {
        $validation = CharacterSheetHelper::validateMasteryUpgrade($this, $params);
        if(gettype($validation) == gettype($this->error('Error')))
            return $validation; // If we received an error, return it.

        $mastery_slot = $this->em()->create("Terrasphere\Charactermanager:CharacterMastery");
        $mastery_slot['user_id'] = $params['user_id'];
        $mastery_slot['target_index'] = $params['target_index'];

        $viewparams = [
            'thisRank' => $validation['thisRank'],
            'nextRank' => $validation['nextRank'],
            'rankSchema' => $validation['rankSchema'],
            'mastery' => $validation['mastery']->Mastery,
            'user' => $validation['user'],
            'userVal' => $validation['rankSchema']->Currency->getFormattedValue($validation['userVal']),
            'nextCost' => $validation['rankSchema']->Currency->getFormattedValue($validation['nextCost']),
            'afterVal' => $validation['rankSchema']->Currency->getFormattedValue($validation['userVal'] - $validation['nextCost']),
            'mastery_slot' => $mastery_slot,
        ];
        return $this->view('Terrasphere\Charactermanager:MasteryUpgradeConfirm', 'terrasphere_cm_confirm_mastery_upgrade', $viewparams);
    }

    /**
     * Upgrades mastery after confirmation has been clicked.
     */
    public function actionUpgradeMastery(ParameterBag $params)
    {
        // Permission check
        if(!$this->canVisitorEditCharacterSheet($params['user_id']))
            return $this->error("You don't have permission to edit this character.");

        $validation = CharacterSheetHelper::validateMasteryUpgrade($this, $params);
        if(gettype($validation) == gettype($this->error('Error')))
            return $validation; // If we received an error, return it.

        if($validation['nextCost'] > $validation['userVal'])
            return $this->error('Error: Insufficient Funds.');

        if($this->adjustCurrency($validation['user'], $validation['nextCost'], "Mastery Upgrade (".$validation['mastery']->Mastery['display_name']." Rank ".$validation['nextRank']['name'].")", $validation['rankSchema']->Currency['currency_id']))
        {
            $previousUnlockedMasterySlots = CharacterMastery::unlockedMasterySlots($this, $params['user_id']);

            // Update mastery entity to next rank.
            $validation['mastery']->fastUpdate('rank_id', $validation['nextRank']['rank_id']);

            // Close overlay via redirect and setup AJAX parameters.
            $redirect = $this->redirect($this->buildLink('members', null, ['user_id' => $params['user_id']]));
            $redirect->setJsonParam('masterySlotIndex', $params['target_index']);
            $redirect->setJsonParam('newRankTier', $validation['nextRank']['tier']);
            $redirect->setJsonParam('newRankTitle', $validation['nextRank']['name']);
            $redirect->setJsonParam('max', $validation['nextRank']['tier'] == Rank::maxTier($this));
            $redirect->setJsonParam('previousUnlockedMasterySlots', $previousUnlockedMasterySlots);
            $redirect->setJsonParam('unlockedMasterySlots', CharacterMastery::unlockedMasterySlots($this, $params['user_id']));

            return $redirect;
        }

        return $this->error("Currency adjustment error...");
    }




    /**
     * Opens confirmation screen for selecting a new race trait. Does not select trait yet.
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

    /**
     * Saves a new race trait slot after confirmation has been clicked.
     * @return Error|Redirect
     * @throws Exception
     * @throws \XF\PrintableException
     */
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

    /**
     * Appears to be a helper function for saving a race trait slot.
     * @param CharacterRaceTrait $slot
     * @return FormAction
     */
    public function saveTraitSlot(CharacterRaceTrait $slot): FormAction
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
     * Opens confirmation screen for changing type of armor. Does not change armor yet.
     * @return Error|View
     */
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

    /**
     * Changes armor after confirmation has been clicked.
     * @return Error|Redirect
     * @throws Exception
     */
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
        $equipment = $charEquip['Equipment'];

        if($equipment == null)
            return $this->error("No current equipment found. This shouldn't happen. Please contact an admin for help.");

        $targetArmor = $this->finder("Terrasphere\Core:Equipment")->whereId($equipId)->fetchOne();

        if($targetArmor == null)
            return $this->error("Target armor doesn't exist.");
        if($targetArmor['equip_group'] != $equipment->equip_group)
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




    /**
     * Opens confirmation screen for changing type of armor. Does not change armor yet.
     * @return Error|View
     */
    public function actionConfirmChangeAccessory(ParameterBag $params) {
        $user_id = $this->filter('user_id', 'uint');
        $equipGroup = $this->filter('equipment_group', 'str');

        try {
            /** @var \Terrasphere\Charactermanager\XF\Entity\User $user */
            $user = $this->assertViewableUser($user_id);
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }

        $charEquip = CharacterEquipment::getEquipmentByGroup($this, $user_id, $equipGroup);
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
            'accessory1' => $otherEquip[0],
            'accessory2' => $otherEquip[1],
            'charEquip' => $charEquip,
            'thisRank' => $charEquip->Rank,
            'rankSchema' => $rankSchema,
            'user' => $user,
            'userVal' => $rankSchema->Currency->getFormattedValue($userVal),
            'nextCost' => $rankSchema->Currency->getFormattedValue($muns),
            'afterVal' => $rankSchema->Currency->getFormattedValue($userVal-$muns),
        ];
        return $this->view('Terrasphere\Charactermanager:EquipAccessoryChangeConfirm', 'terrasphere_cm_confirm_accessory_selection', $viewparams);
    }

    /**
     * Changes armor after confirmation has been clicked.
     * @return Error|Redirect
     * @throws Exception
     */
    public function actionChangeAccessory(ParameterBag $params)
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

        $charEquip = $user->getOrInitiateAccessory();
        $equipment = $charEquip['Equipment'];

        if($equipment == null)
            return $this->error("No current equipment found. This shouldn't happen. Please contact an admin for help.");

        $targetAccessory = $this->finder("Terrasphere\Core:Equipment")->whereId($equipId)->fetchOne();

        if($targetAccessory == null)
            return $this->error("Target accessory doesn't exist.");
        if($targetAccessory['equip_group'] != $equipment->equip_group)
            return $this->error("Tried to switch from accessory to non-accessory equipment (or the opposite).");

        $rankSchema = $equipment->RankSchema;

        if($rankSchema == null)
            return $this->error("No associated rank schema with current equipment when changing accessory type.");

        $cost = $this->getRetrofitCost($rankSchema, $charEquip);
        $userVal = $rankSchema->Currency->getValueFromUser($user, false);

        if($cost > $userVal)
            return $this->error('Error: Insufficient Funds.');

        if($this->adjustCurrency($user, $cost, "Accessory Retrofit (".$targetAccessory->display_name.")", $rankSchema->Currency->currency_id))
        {
            // Change type.
            $charEquip->fastUpdate('equipment_id', $equipId);

            // Close overlay via redirect and setup AJAX parameters.
            $redirect = $this->redirect($this->buildLink('members', $user, ['user_id' => $params['user_id']]));
            $redirect->setJsonParam('newIconURL', $targetAccessory['icon_url']);
            $redirect->setJsonParam('newName', $targetAccessory['display_name']);

            return $redirect;
        }

        return $this->error("Currency adjustment error...");
    }




    /**
     * Opens equipment upgrade confirmation screen. Does not upgrade yet.
     * @return array|mixed|View
     */
    public function actionConfirmUpgradeEquip(ParameterBag $params)
    {
        $validation = CharacterSheetHelper::validateEquipmentUpgrade($this, $params);
        if(gettype($validation) == gettype($this->error('Error')))
            return $validation; // If we received an error, return it.

        $viewparams = [
            'name' => $validation['equipment']['display_name'],
            'iconSrc' => $validation['equipment']['icon_url'],
            'thisRank' => $validation['charEquip']['Rank'],
            'nextRank' => $validation['nextRank'],
            'rankSchema' => $validation['rankSchema'],
            'equipId' => $validation['equipment']['equipment_id'],
            'user' => $validation['user'],
            'userVal' => $validation['rankSchema']['Currency']->getFormattedValue($validation['userVal']),
            'nextCost' => $validation['rankSchema']['Currency']->getFormattedValue($validation['nextCost']),
            'afterVal' => $validation['rankSchema']['Currency']->getFormattedValue($validation['userVal'] - $validation['nextCost']),
        ];
        return $this->view('Terrasphere\Charactermanager:EquipUpgradeConfirm', 'terrasphere_cm_confirm_equip_upgrade', $viewparams);
    }

    /**
     * Upgrades equipment after confirmation has been clicked.
     * @return array|mixed|Error|Redirect
     * @throws Exception
     */
    public function actionUpgradeEquip(ParameterBag $params)
    {
        $validation = CharacterSheetHelper::validateEquipmentUpgrade($this, $params);
        if(gettype($validation) == gettype($this->error('Error')))
            return $validation; // If we received an error, return it.

        // This differs from the helper function in that we need to get the equipment instance.
        // $charEquip = CharacterEquipment::getEquipment($this,$user_id,$equipId);

        if($this->adjustCurrency($validation['user'], $validation['nextCost'], "Equipment Upgrade (".$validation['equipment']['display_name']." Rank ".$validation['nextRank']['name'].")", $validation['rankSchema']['Currency']['currency_id']))
        {
            // Update user's equipment data to next rank.
            $validation['charEquip']->fastUpdate("rank_id", $validation['nextRank']['rank_id']);

            // Close overlay via redirect and setup AJAX parameters.
            $redirect = $this->redirect($this->buildLink('members', null, ['user_id' => $params['user_id']]));

            $redirect->setJsonParam('equipId', $validation['equipment']['equipment_id']);
            $redirect->setJsonParam('newRankTier', $validation['nextRank']['tier']);
            $redirect->setJsonParam('newRankTitle', $validation['nextRank']['name']);
            $redirect->setJsonParam('equipType', $validation['equipment']['equip_group']);
            $redirect->setJsonParam('isMaxRank', $validation['nextRank']['tier'] == Rank::maxTier($this));
            $redirect->setJsonParam('equipGroup', $validation['equipment']['equip_group']);

            return $redirect;
        }

        return $this->error("Currency adjustment error...");
    }




    #region *** Helper Methods ***

    /**
     * @throws Exception
     */
    public function getUser($userID) : User
    {
        return $this->assertViewableUser($userID);
    }

    public function canVisitorViewCharacterSheet(int $userID): bool {
        return true;
    }

    public function doesVisitorHaveCharacterModPowers(): bool {
        return \XF::visitor()->hasPermission('terrasphere', 'terrasphere_cm_review');
    }

    public function canVisitorViewRevisions(int $userID): bool {
        return \XF::visitor()->hasPermission('terrasphere', 'terrasphere_cm_review')
            || $this->visitorIsSameUser($userID);
    }

    public function canVisitorEditCharacterSheet(int $userID): bool {
        return $this->visitorIsSameUser($userID); // TODO || Edit permissions!
    }

    public function canVisitorMakeChanges(int $userID): bool {
        return $this->visitorIsSameUser($userID);
    }

    private function visitorIsSameUser(int $userID): bool {
        return \XF::visitor()->user_id == $userID;
    }

    // Pure function
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

    // Pure function
    private function stripExpertisesInAFromB(array $a, array $b): array
    {
        $ret = [];
        foreach ($b as $expertise)
        {
            $match = false;
            foreach ($a as $m)
            {
                if($expertise['expertise_id'] == $m['expertise_id'])
                {
                    $match = true;
                    break;
                }
            }

            if(!$match) $ret[$expertise['expertise_id']] = $expertise;
        }
        return $ret;
    }

    /**
     * @throws Exception
     */
    private function adjustCurrency(User $user, int $cost, string $message, $currencyID)
    {
        $this->assertPostOnly();
        return CharacterSheetHelper::adjustCurrency($this, $user, $cost, $message, $currencyID);
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
    #endregion
}