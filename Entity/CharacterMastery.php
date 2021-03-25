<?php

namespace Terrasphere\Charactermanager\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;
use XF\Pub\Controller\AbstractController;

class CharacterMastery extends Entity
{
    public static function getStructure(Structure $structure) : Structure
    {
        $structure->table = 'xf_terrasphere_cm_character_masteries';
        $structure->shortName = 'Terrasphere\Charactermanager:CharacterMastery';
        $structure->primaryKey = 'mastery_id';
        $structure->columns = [
            'mastery_id' => ['type' => self::UINT, 'required' => true],
            'user_id' => ['type' => self::UINT, 'required' => true],
            'rank_id' => ['type' => self::UINT, 'required' => false],
            'target_index' => ['type' => self::UINT],
        ];

        $structure->getters = [];

        $structure->relations = [
            'Mastery' => [
                'entity' => 'Terrasphere\Core:Mastery',
                'type' => SELF::TO_ONE,
                'conditions' => 'mastery_id',
                'primary' => true
            ],
            'User' => [
                'entity' => 'XF:User',
                'type' => SELF::TO_MANY,
                'conditions' => 'user_id',
                'primary' => true
            ],
        ];

        return $structure;
    }

    /**
     * Gets all masteries for the appropriate character.
     */
    public static function getCharacterMasteries(AbstractController $controller, int $userID): array
    {
        $results = $controller->finder('Terrasphere\Charactermanager:CharacterMastery')
            ->with('Mastery')
            ->where('user_id', '=', $userID)
            ->order('target_index', 'ASC')
            ->fetch();

        return $results->groupBy('target_index');
    }

    /**
     * Similar to above, but always includes an entry for all 5 mastery slots, inserting dummies where a mastery has
     * yet to be selected.
     */
    public static function getCharacterMasterySlots(AbstractController $controller, int $userID): array
    {
        $masteries = self::getCharacterMasteries($controller, $userID);
        for ($index = 0; $index < 5; $index++)
        {
            if(!array_key_exists($index, $masteries))
            {
                /** @var \Terrasphere\Charactermanager\Entity\CharacterMastery $dummy */
                $dummy = $controller->em()->create('Terrasphere\Charactermanager:CharacterMastery');
                $dummy['user_id'] = $userID;
                $dummy['target_index'] = $index;
                $dummy['mastery_id'] = 'N/A';
                $masteries[$index] = $dummy;
            }
        }

        return $masteries;
    }
}