<?php

namespace Terrasphere\Charactermanager\XF\Entity;

use Terrasphere\Charactermanager\Entity\CharacterMastery;

class User extends XFCP_User
{
    public function canLinkCharacterSheet() : int
    {
        return (int) \XF::visitor()->hasPermission('terrasphere', 'terrasphere_cm_review');
    }

    public function getMasteries() : array
    {
        $masteries = CharacterMastery::getCharacterMasteries($this, $this->user_id);
        return $masteries;
    }

    public function getCharacterBannerSrc()
    {
        $files = glob(\XF::getRootDirectory() . "/data/characterbanners/" . $this->user_id . "/banner.*");
        if (!empty($files)) {
            return "data/characterbanners/" . $this->user_id . "/" . basename($files[0]);
        } else {
            return "";
        }
    }
}