<?php

namespace Terrasphere\Charactermanager\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

class CharacterMastery extends Entity
{
    public static function getStructure(Structure $structure) : Structure
    {
        $structure->table = 'xf_terrasphere_cm_character_masteries';
        $structure->shortName = 'Terrasphere\Charactermanager:CharacterMastery';
        $structure->primaryKey = 'id';
        $structure->columns = [
            'id' => ['type' => self::UINT, 'autoIncrement' => true],
            'mastery_id' => ['type' => self::UINT, 'required' => true],
            'user_id' => ['type' => self::UINT, 'required' => true],
            'rank_id' => ['type' => self::UINT, 'required' => false],
            'target_index' => ['type' => self::UINT],
            'overrank' => ['type' => self::UINT],
        ];

        $structure->getters = [];

        $structure->relations = [
            'Mastery' => [
                'entity' => 'Terrasphere\Core:Mastery',
                'type' => SELF::TO_ONE,
                'conditions' => 'mastery_id',
                'primary' => true,
            ],
            'User' => [
                'entity' => 'XF:User',
                'type' => SELF::TO_ONE,
                'conditions' => 'user_id',
                'primary' => true,
            ],
            'Rank' => [
                'entity' => 'Terrasphere\Core:Rank',
                'type' => SELF::TO_ONE,
                'conditions' => 'rank_id',
                'primary' => true
            ],
        ];

        return $structure;
    }

    /**
     * Gets all masteries for the appropriate character.
     */
    public static function getCharacterMasteries($mustHaveFinderAndEM, int $userID): array
    {
        $results = $mustHaveFinderAndEM->finder('Terrasphere\Charactermanager:CharacterMastery')
            ->with(['Mastery', 'Rank'])
            ->where('user_id', $userID)
            ->order('target_index', 'ASC')
            ->fetch();

        $results = $results->toArray();

        $ret = [];
        foreach ($results as $masteryID => $entry)
        {
            $ret[$entry['target_index']] = $entry;
        }

        return $ret;
    }

    /**
     * Similar to above, but always includes an entry for all mastery slots, inserting dummies where a mastery has
     * yet to be selected.
     */
    public static function getCharacterMasterySlots($mustHaveFinderAndEM, int $userID): array
    {
        $masteries = self::getCharacterMasteries($mustHaveFinderAndEM, $userID);
        $ret = [];
        for ($index = 0; $index < 6; $index++)
        {
            if(!array_key_exists($index, $masteries))
            {
                /** @var \Terrasphere\Charactermanager\Entity\CharacterMastery $dummy */
                $dummy = $mustHaveFinderAndEM->em()->create('Terrasphere\Charactermanager:CharacterMastery');
                $dummy['user_id'] = $userID;
                $dummy['mastery_id'] = 0;
                $dummy['rank_id'] = 0;
                $dummy['target_index'] = $index;
                $ret[$index] = $dummy;
            }
            else
            {
                $ret[$index] = $masteries[$index];
            }
        }

        return $ret;
    }

    public static function unlockedMasterySlots($finderContainer, int $userID): array {
        $slotArray = [1, 1, 1];
        $upgradedMasteryCount = $finderContainer->finder('Terrasphere\Charactermanager:CharacterMastery')
            ->with('Rank')
            ->where('user_id', $userID)
            ->where('Rank.tier', '>', 0)
            ->total();

        $doubleUpgradedMasteryCount = $finderContainer->finder('Terrasphere\Charactermanager:CharacterMastery')
            ->with('Rank')
            ->where('user_id', $userID)
            ->where('Rank.tier', '>', 1)
            ->total();

        // Slot 4 - When at least 3 are upgraded at least once
        // Slot 5 - When at least 1 has been upgraded at least twice
        // Slot 6 - When at least 3 have been upgraded at least twice
        array_push($slotArray,
            ($upgradedMasteryCount >= 3 ? 1 : 0),
            ($doubleUpgradedMasteryCount >= 1 ? 1 : 0),
            ($doubleUpgradedMasteryCount >= 3 ? 1 : 0));


        return $slotArray;
    }

    public static function canSelectMasteryFromCategory($member, array $masteryGroup, int $userID, int $slotIndex): bool
    {
        // 'No' if the group is empty
        if(count($masteryGroup['masteries']) == 0)
            return false;

        $masteryList = $masteryGroup['masteries'];
        $firstMastery = $masteryList[array_keys($masteryList)[0]];

        $typeID = $firstMastery->mastery_type_id;
        $typeCap = $firstMastery->MasteryType->cap_per_character;

        $alterTypeID = $member->options()->terrasphereAlterType;
        $characterMasteries = CharacterMastery::getCharacterMasteries($member, $userID);
        $amountOfThisType = 0;
        $nonAlterCountFirst3Slots = 0;
        foreach ($characterMasteries as $m)
        {
            if ($m['target_index'] < 3 && $m->Mastery->mastery_type_id != $alterTypeID)
                $nonAlterCountFirst3Slots++;

            if($m->Mastery->mastery_type_id == $typeID)
            {
                $amountOfThisType++;
                if($amountOfThisType >= $typeCap)
                    return false;
            }
        }

        // Alter mastery must be one of the first 3 slots and is invalid for further slots.
        if($typeID == $alterTypeID && $slotIndex >= 3)
            return false;

        // Non-alter masteries are invalid for the first 3 slots when 2 non-alters have been selected.
        if($typeID != $alterTypeID && $slotIndex < 3 && $nonAlterCountFirst3Slots >= 2)
            return false;

        return true;
    }

    protected function setupApiResultData(\XF\Api\Result\EntityResult $result, $verbosity = self::VERBOSITY_NORMAL, array $options = [])
    {
       // parent::setupApiResultData($result, $verbosity, $options); // TODO: Change the autogenerated stub
    }
}