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
            'fourthSlotUnlocked' => $this->fourthSlotUnlocked($params->user_id),
            'fifthSlotUnlocked' => $this->fifthSlotUnlocked($params->user_id),
        ];

		return $this->view('XF:Member\CharacterSheet', 'terrasphere_cm_character_sheet', $viewParams);
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
                ->fetch();

            // Mastery type limit check
            if(count($sameTypeMasteries) >= $mastery->MasteryType->cap_per_character)
                return $this->error('You already have the maximum amount of ' . $mastery->MasteryType->name . ' masteries.');

            // 4th slot restriction check
            if($params['target_index'] == 3 && !$this->fourthSlotUnlocked($params['user_id']))
                return $this->error('Slot not unlocked.');

            // 5th slot restriction check
            if($params['target_index'] == 4 && !$this->fifthSlotUnlocked($params['user_id']))
                return $this->error('Slot not unlocked.');

            // Save entity to DB
            $slotEntity = $this->em()->create('Terrasphere\Charactermanager:CharacterMastery');
            $slotEntity['mastery_id'] = $mastery['mastery_id'];
            $slotEntity['user_id'] = $params['user_id'];
            $slotEntity['target_index'] = $params['target_index'];
            $this->saveMasterySlot($slotEntity, $input['mastery'])->run();

            // Close overlay via redirect and setup AJAX parameters.
            $redirect = $this->redirect($this->buildLink('members', null, ['user_id' => $params['user_id']]));
            $redirect->setJsonParam('masterySlotIndex', $params['target_index']);
            $redirect->setJsonParam('newMasteryName', $mastery['display_name']);
            $redirect->setJsonParam('newMasteryIconURL', $mastery['icon_url']);
            // TODO Add another for updating cost of upgrade...
            return $redirect;
        }
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
        $mastery = $this->finder('Terrasphere\Charactermanager:CharacterMastery')
            ->with(['Rank', 'Mastery'])
            ->where('user_id', $params['user_id'])
            ->where('target_index', $params['target_index'])
            ->fetchOne();

        //todo this probably changed, because Currency is now part of the rankSchema.
        $nextRank = $this->finder('Terrasphere\Core:Rank')
            ->with('Currency')
            ->where('rank_id', $mastery['rank_id']+1)
            ->fetchOne();

        $user = $this->finder('XF:User')->where('user_id', $params['user_id'])->fetchOne();

        if($nextRank == null)
            $this->error('Max rank.');

        $viewparams = [
            'nextRank' => $nextRank,
            'mastery' => $mastery,
            'user' => $user,
            'userVal' => $user[$nextRank->Currency->user_id_column],
        ];
        return $this->view('Terrasphere\Charactermanager:MasteryUpgradeConfirm', 'terrasphere_cm_confirm_mastery_upgrade', $viewparams);
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

    private function visitorIsUser(int $userID): bool {
        return \XF::visitor()->user_id == $userID;
    }

    private function fourthSlotUnlocked(int $userID): bool {
        $cAndHigherMasteries = $this->finder('Terrasphere\Charactermanager:CharacterMastery')
            ->where('user_id', $userID)
            ->where('rank_id', '>=', 2)
            ->total();

        return $cAndHigherMasteries >= 3;
    }

    private function fifthSlotUnlocked(int $userID): bool {
        return $this->finder('Terrasphere\Charactermanager:CharacterMastery')
            ->with('Mastery')
            ->where('user_id', $userID)
            ->where('rank_id', '>=', 5)
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
}