<?php

namespace Terrasphere\Charactermanager\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

class CharacterEquipment extends Entity
{
    public static function getStructure(Structure $structure) : Structure
    {
        $structure->table = 'xf_terrasphere_cm_character_equipment';
        $structure->shortName = 'Terrasphere\Charactermanager:CharacterEquipment';
        $structure->primaryKey = ['user_id','equipment_id'];
        $structure->columns = [
            'equipment_id' => ['type' => self::UINT, 'required' => true],
            'user_id' => ['type' => self::UINT, 'required' => true],
            'rank_id' => ['type' => self::UINT, 'required' => true],
        ];

        $structure->getters = [];

        $structure->relations = [
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
            'Equipment' => [
                'entity' => 'Terrasphere\Core:Equipment',
                'type' => SELF::TO_ONE,
                'conditions' => 'equipment_id',
                'primary' => true
            ]
        ];

        return $structure;
    }

    public static function getEquipment($mustHaveFinderAndEM, int $userId, int $equipId) : CharacterEquipment
    {
        return $mustHaveFinderAndEM->finder('Terrasphere\Charactermanager:CharacterEquipment')
            ->where([
                ['user_id', $userId],
                ['equipment_id',$equipId],
            ])
            ->fetchOne();
    }

    public static function getEquipmentByGroup($mustHaveFinderAndEM, int $userId, string $equipGroup) : CharacterEquipment
    {
        return $mustHaveFinderAndEM->finder('Terrasphere\Charactermanager:CharacterEquipment')
            ->with('Equipment')
            ->where([
                ['user_id', $userId],
                ['Equipment.equip_group',$equipGroup],
            ])
            ->fetchOne();
    }
}