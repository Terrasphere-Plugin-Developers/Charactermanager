<?php

namespace Terrasphere\Charactermanager\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

class CharacterMastery extends Entity
{
    /**
     * Gets masteries for the appropriate character.
     * @return array
     */
    public function getCharacterMasteries($userID): array
    {

    }

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
                'type' => SELF::TO_MANY,
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
}