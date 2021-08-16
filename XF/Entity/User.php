<?php

namespace Terrasphere\Charactermanager\XF\Entity;

use Terrasphere\Charactermanager\Entity\CharacterMastery;
use XF;

class User extends XFCP_User
{
    public function canLinkCharacterSheet() : int
    {
        return (int) XF::visitor()->hasPermission('terrasphere', 'terrasphere_cm_review');
    }

    /**
     * Gets all masteries for the character/user.
     */
    public function getMasteries() : array
    {
        $masteries = CharacterMastery::getCharacterMasteries($this, $this->user_id);
        return $masteries;
    }

//    public function getEquipRank($type) : XF\Mvc\Entity\Entity
//    {
//        $idString = $this->getColumnKeyForEquip($type);
//
//        return $this->finder('Terrasphere\Core:Rank')
//            ->whereId($this[$idString])
//            ->fetchOne();
//    }

//    public function getIconForEquip($type) : string
//    {
//        switch ($type)
//        {
//            case 0: return "styles/default/Terrasphere/Charactermanager/weapon-icon.png";
//            case 1: return "styles/default/Terrasphere/Charactermanager/armor-light.png";
//            case 2: return "styles/default/Terrasphere/Charactermanager/armor-medium.png";
//            case 3: return "styles/default/Terrasphere/Charactermanager/armor-heavy.png";
//            default: return "styles/default/Terrasphere/Charactermanager/armor-icon.png";
//        }
//    }

//    public function getDisplayNameForEquip($type) : string
//    {
//        switch ($type)
//        {
//            case 0: return "Weapon";
//            case 1: return "Light Armor";
//            case 2: return "Med. Armor";
//            case 3: return "Heavy Armor";
//            case 4: return "Hybrid Armor";
//            default: return "ERROR";
//        }
//    }

//    public function getColumnKeyForEquip($type) : string
//    {
//        switch ($type)
//        {
//            default: // 0
//                return "ts_cm_weapon_rank_id";
//            case 1:
//                return "ts_cm_armor_light_rank_id";
//            case 2:
//                return "ts_cm_armor_med_rank_id";
//            case 3:
//                return "ts_cm_armor_heavy_rank_id";
//        }
//    }

//    /**
//     * @return array "type" => Integer representing best armor - light(1), med(2), heavy(3), multi(4).
//     *               "src"  => URL string of the icon for the armor type.
//     *               "rank" => Rank entity for best armor type.
//     */
//    private function getBestArmor() : array
//    {
//        $type = 1;
//        $highestRank = $this->finder('Terrasphere\Core:Rank')
//            ->whereId($this["ts_cm_armor_light_rank_id"])
//            ->fetchOne();
//
//        // Check if med armor is better than light.
//        $tempRank = $this->finder('Terrasphere\Core:Rank')
//            ->whereId($this["ts_cm_armor_med_rank_id"])
//            ->fetchOne();
//        if($tempRank['tier'] > $highestRank['tier'])
//        {
//            $highestRank = $tempRank;
//            $type = 2;
//        }
//        else if($tempRank['tier'] == $highestRank['tier']) $type = 4;
//
//        // Check if heavy armor is better than light and/or med armor.
//        $tempRank = $this->finder('Terrasphere\Core:Rank')
//            ->whereId($this["ts_cm_armor_heavy_rank_id"])
//            ->fetchOne();
//        if($tempRank['tier'] > $highestRank['tier'])
//        {
//            $highestRank = $tempRank;
//            $type = 3;
//        }
//        else if($tempRank['tier'] == $highestRank['tier']) $type = 4;
//
//        return [
//            "type" => $type,
//            "rank" => $highestRank,
//        ];
//    }

//    public function getIconForBestArmor() : string
//    {
//        return $this->getIconForEquip($this->getBestArmor()['type']);
//    }
//
//    public function getRankForBestArmor() : XF\Mvc\Entity\Entity
//    {
//        return $this->getBestArmor()['rank'];
//    }

    public function getBuildEditorParamsString() : string
    {
        $masteryNames = "";
        $masteryRanks = "";

        $masteries = $this->getMasteries();

        foreach ($masteries as $mastery)
        {
            // Make sure each mastery display name is formatted as expected, using single dashes as word separators.
            $masteryString = strtolower($mastery->Mastery['display_name']);
            $masteryString = str_replace(' ', '-', $masteryString);
            $masteryString = str_replace('_', '-', $masteryString);

            $masteryRanks .= $mastery->Rank['tier'];

            // Add comma separators.
            $masteryNames .= $masteryString . ',';
            $masteryRanks .= ',';
        }

        // Remove last comma for both names and ranks.
        $masteryNames = substr($masteryNames, 0, -1);
        $masteryRanks = substr($masteryRanks, 0, -1);

        return $masteryNames . '.' .
            $masteryRanks .'..' .
            $this->getEquipRank(0)['tier'] . '.' .
            $this->getEquipRank(1)['tier'] . '.' .
            $this->getEquipRank(2)['tier'] . '.' .
            $this->getEquipRank(3)['tier'] . '.' .
            str_replace(' ', '_', $this->username);
    }
}