<?php

namespace Terrasphere\Charactermanager\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

class Equipment extends Entity
{
    public static function getStructure(Structure $structure) : Structure
    {
        $structure->table = 'xf_terrasphere_cm_equipment';
        $structure->shortName = 'Terrasphere\Charactermanager:Equipment';
        $structure->primaryKey = 'equipment_id';
        $structure->columns = [
            'equipment_id' => ['type' => self::UINT, 'autoIncrement' => true],
            'user_id' => ['type' => self::UINT, 'required' => true],
            'rank_id' => ['type' => self::UINT, 'required' => true],
            'type' => ['type' => self::UINT, 'required' => true],
            'name' => ['type' => self::STR, 'required' => false],
            'post_link' => ['type' => self::STR, 'required' => false],
        ];

        $structure->getters = [];

        $structure->relations = [
            'User' => [
                'entity' => 'XF:User',
                'type' => SELF::TO_ONE,
                'conditions' => 'user_id',
                'primary' => true
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
}