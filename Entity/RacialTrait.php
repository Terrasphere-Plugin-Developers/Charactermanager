<?php

namespace Terrasphere\Charactermanager\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

class RacialTrait extends Entity
{
    public static function getStructure(Structure $structure) : Structure
    {
        $structure->table = 'xf_terrasphere_cm_racial_trait';
        $structure->shortName = 'Terrasphere\Charactermanager:RacialTrait';
        $structure->primaryKey = 'race_trait_id';
        $structure->columns = [
            'race_trait_id' => ['type' => self::UINT, 'autoIncrement' => true],
            'name' => ['type' => self::STR, 'required' => true],
            'icon_url' => ['type' => self::STR, 'required' => true],
            'wiki_url' => ['type' => self::STR, 'required' => true],
            'tooltip' => ['type' => self::STR, 'required' => true],
            'cached_group_id' => ['type' => self::INT, 'required' => true],
        ];

        $structure->getters = [];
        $structure->relations = [];

        return $structure;
    }
}