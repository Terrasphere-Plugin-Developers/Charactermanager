<?php

namespace Terrasphere\Charactermanager;

use XF\Mvc\Entity\Entity;

class Listener
{
    public static function userEntityStructure(\XF\Mvc\Entity\Manager $em, \XF\Mvc\Entity\Structure &$structure)
    {
        $structure->columns['ts_cm_character_sheet_post_id'] = ['type' => Entity::INT, 'default' => -1];
        $structure->columns['ts_cm_weapon_rank_id'] = ['type' => Entity::INT, 'default' => 0];
        $structure->columns['ts_cm_armor_light_rank_id'] = ['type' => Entity::INT, 'default' => 0];
        $structure->columns['ts_cm_armor_med_rank_id'] = ['type' => Entity::INT, 'default' => 0];
        $structure->columns['ts_cm_armor_heavy_rank_id'] = ['type' => Entity::INT, 'default' => 0];
    }
}