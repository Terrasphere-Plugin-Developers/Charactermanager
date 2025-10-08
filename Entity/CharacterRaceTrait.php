<?php

namespace Terrasphere\Charactermanager\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

class CharacterRaceTrait extends Entity
{
    public static function getStructure(Structure $structure) : Structure
    {
        $structure->table = 'xf_terrasphere_cm_character_race_traits';
        $structure->shortName = 'Terrasphere\Charactermanager:CharacterRaceTrait';
        $structure->primaryKey = 'id';
        $structure->columns = [
            'id' => ['type' => self::UINT, 'autoIncrement' => true],
            'race_trait_id' => ['type' => self::UINT, 'required' => true],
            'user_id' => ['type' => self::UINT, 'required' => true],
            'slot_index' => ['type' => self::UINT, 'required' => true],
        ];

        $structure->getters = [];
        $structure->relations = [
            'RacialTrait' => [
                'entity' => 'Terrasphere\Charactermanager:RacialTrait',
                'type' => SELF::TO_ONE,
                'conditions' => 'race_trait_id',
                'primary' => true,
            ],
            'User' => [
                'entity' => 'XF:User',
                'type' => SELF::TO_ONE,
                'conditions' => 'user_id',
                'primary' => true,
                'cascadeDelete' => true
            ],
        ];

        return $structure;
    }

    public static function getCharacterTraits($mustHaveFinderAndEM, int $userID): array
    {
        $results = $mustHaveFinderAndEM->finder('Terrasphere\Charactermanager:CharacterRaceTrait')
            ->with(['RacialTrait'])
            ->where('user_id', $userID)
            ->order('slot_index', 'ASC')
            ->fetch();

        $results = $results->toArray();

        $ret = [];
        foreach ($results as $masteryID => $entry)
            $ret[$entry['slot_index']] = $entry;

        return $ret;
    }
}