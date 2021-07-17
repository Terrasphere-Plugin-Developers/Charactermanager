<?php

namespace Terrasphere\Charactermanager;

use XF\Mvc\Entity\Entity;

class Listener
{
    public static function userEntityStructure(\XF\Mvc\Entity\Manager $em, \XF\Mvc\Entity\Structure &$structure)
    {
        $structure->columns['ts_cm_character_sheet_post_id'] = ['type' => Entity::INT, 'default' => -1];
    }
}