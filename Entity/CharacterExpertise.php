<?php

namespace Terrasphere\Charactermanager\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

class CharacterExpertise extends Entity
{
    public static function getStructure(Structure $structure) : Structure
    {
        $structure->table = 'xf_terrasphere_cm_character_expertise';
        $structure->shortName = 'Terrasphere\Charactermanager:CharacterExpertise';
        $structure->primaryKey = 'id';
        $structure->columns = [
            'id' => ['type' => self::UINT, 'autoIncrement' => true],
            'expertise_id' => ['type' => self::UINT, 'required' => true],
            'user_id' => ['type' => self::UINT, 'required' => true],
            'rank_id' => ['type' => self::UINT, 'required' => false],
            'target_index' => ['type' => self::UINT],
            'overrank' => ['type' => self::UINT],
        ];

        $structure->getters = [];

        $structure->relations = [
            'Expertise' => [
                'entity' => 'Terrasphere\Core:Expertise',
                'type' => SELF::TO_ONE,
                'conditions' => 'expertise_id',
                'primary' => true,
            ],
            'User' => [
                'entity' => 'XF:User',
                'type' => SELF::TO_ONE,
                'conditions' => 'user_id',
                'primary' => true,
                'cascadeDelete' => true
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
     * Gets all expertise for the appropriate character.
     */
    public static function getCharacterExpertises($mustHaveFinderAndEM, int $userID): array
    {
        $results = $mustHaveFinderAndEM->finder('Terrasphere\Charactermanager:CharacterExpertise')
            ->with(['Expertise', 'Rank'])
            ->where('user_id', $userID)
            ->order('target_index', 'ASC')
            ->fetch();

        $results = $results->toArray();

        $ret = [];
        foreach ($results as $expertiseID => $entry)
            $ret[$entry['target_index']] = $entry;

        return $ret;
    }

    /**
     * Similar to above, but always includes an entry for all mastery slots, inserting dummies where an expertise has
     * yet to be selected.
     */
    public static function getCharacterExpertiseSlots($mustHaveFinderAndEM, int $userID): array
    {
        $expertises = self::getCharacterExpertises($mustHaveFinderAndEM, $userID);
        $ret = [];
        for ($index = 0; $index < 6; $index++)
        {
            if(!array_key_exists($index, $expertises))
            {
                /** @var \Terrasphere\Charactermanager\Entity\CharacterExpertise $dummy */
                $dummy = $mustHaveFinderAndEM->em()->create('Terrasphere\Charactermanager:CharacterExpertise');
                $dummy['user_id'] = $userID;
                $dummy['expertise_id'] = 0;
                $dummy['rank_id'] = 0;
                $dummy['target_index'] = $index;
                $ret[$index] = $dummy;
            }
            else
            {
                $ret[$index] = $expertises[$index];
            }
        }

        return $ret;
    }

    public static function unlockedExpertiseSlots($finderContainer, int $userID): array {
        $slotArray = [1, 1, 1];
        $upgradedExpertiseCount = $finderContainer->finder('Terrasphere\Charactermanager:CharacterExpertise')
            ->with('Rank')
            ->where('user_id', $userID)
            ->where('Rank.tier', '>', 0)
            ->total();

        $doubleUpgradedExpertiseCount = $finderContainer->finder('Terrasphere\Charactermanager:CharacterExpertise')
            ->with('Rank')
            ->where('user_id', $userID)
            ->where('Rank.tier', '>', 1)
            ->total();

        // Slot 4 - When at least 3 are upgraded at least once
        // Slot 5 - When at least 1 has been upgraded at least twice
        // Slot 6 - When at least 3 have been upgraded at least twice
        array_push($slotArray,
            ($upgradedExpertiseCount >= 3 ? 1 : 0),
            ($doubleUpgradedExpertiseCount >= 1 ? 1 : 0),
            ($doubleUpgradedExpertiseCount >= 3 ? 1 : 0));


        return $slotArray;
    }

    public static function canSelectFromCategory($member, array $group, int $userID): bool
    {
        // This was mainly used to check if a character could add an alter mastery, but for expertise, it's irrelevant

//        // The answer is obviously 'no' if the group is empty
//        if(count($group['expertises']) == 0)
//            return false;
//
//        $expertiseList = $group['expertises'];
//        $first = $expertiseList[array_keys($expertiseList)[0]];
//
//        $typeID = $first->mastery_type_id;
//        $typeCap = $first->MasteryType->cap_per_character;
//
//        $characterExpertises = CharacterExpertise::getCharacterExpertises($member, $userID);
//        $amountOfThisType = 0;
//        foreach ($characterExpertises as $m)
//        {
//            if($m->Expertise->mastery_type_id == $typeID)
//            {
//                $amountOfThisType++;
//                if($amountOfThisType >= $typeCap)
//                    return false;
//            }
//        }

        return true;
    }

    protected function setupApiResultData(\XF\Api\Result\EntityResult $result, $verbosity = self::VERBOSITY_NORMAL, array $options = [])
    {
       // parent::setupApiResultData($result, $verbosity, $options); // TODO: Change the autogenerated stub
    }
}