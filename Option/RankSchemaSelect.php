<?php

namespace Terrasphere\Charactermanager\Option;

use XF\Option\AbstractOption;

class RankSchemaSelect extends AbstractOption
{
    public static function renderOption(\XF\Entity\Option $option, array $htmlParams)
    {
        $rankSchemas = $option->finder("Terrasphere\Core:RankSchema")->fetch();

        return self::getSelectRow($option, $htmlParams, $rankSchemas->pluckNamed('name', 'rank_schema_id'));
    }
}