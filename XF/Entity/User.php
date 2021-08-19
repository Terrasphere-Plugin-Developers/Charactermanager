<?php

namespace Terrasphere\Charactermanager\XF\Entity;

use Terrasphere\Charactermanager\Entity\CharacterEquipment;
use Terrasphere\Charactermanager\Entity\CharacterMastery;
use Terrasphere\Core\Entity\Rank;
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

    public function getOrInitiateWeapon($controller) : CharacterEquipment{

        $weaponEquiId = $this->finder('Terrasphere\Core:Equipment')
            ->where('display_name','Weapon')->fetchOne()->equipment_id;

        $weapon = $this->finder('Terrasphere\Charactermanager:CharacterEquipment')
            ->where([
                ['user_id',$this->user_id],
                ['equipment_id', $weaponEquiId],
            ])
            ->fetchOne();

        if(!$weapon){
            $weapon = $this->em()->create('Terrasphere\Charactermanager:CharacterEquipment');
            $weapon->user_id = $this->user_id;
            $weapon->rank_id = Rank::minRank($controller)->rank_id;
            $weapon->equipment_id = $weaponEquiId;
            $weapon->save();
        }

        return $weapon;
    }

    public function getOrInitiateArmor($controller) :CharacterEquipment {
        $armor = null;
        $armor = $this->finder('Terrasphere\Charactermanager:CharacterEquipment')
            ->with('Equipment')
            ->where([
                ['user_id', $this->user_id],
                ['Equipment.equip_group', 'Armor'],
            ])
            ->fetchOne();

        if(!$armor) {
            $armor = $this->em()->create('Terrasphere\Charactermanager:CharacterEquipment');
            $armor->user_id = $this->user_id;
            $armor->rank_id = Rank::minRank($controller)->rank_id;
            $armor->equipment_id = $this->finder('Terrasphere\Core:Equipment')
                ->where('display_name','Medium Armor')
                ->fetchOne()->equipment_id;
            $armor->save();
        }

        return $armor;
    }



//    public function getBuildEditorParamsString() : string
//    {
//        $masteryNames = "";
//        $masteryRanks = "";
//
//        $masteries = $this->getMasteries();
//
//        foreach ($masteries as $mastery)
//        {
//            // Make sure each mastery display name is formatted as expected, using single dashes as word separators.
//            $masteryString = strtolower($mastery->Mastery['display_name']);
//            $masteryString = str_replace(' ', '-', $masteryString);
//            $masteryString = str_replace('_', '-', $masteryString);
//
//            $masteryRanks .= $mastery->Rank['tier'];
//
//            // Add comma separators.
//            $masteryNames .= $masteryString . ',';
//            $masteryRanks .= ',';
//        }
//
//        // Remove last comma for both names and ranks.
//        $masteryNames = substr($masteryNames, 0, -1);
//        $masteryRanks = substr($masteryRanks, 0, -1);
//
//        return $masteryNames . '.' .
//            $masteryRanks .'..' .
//            $this->getEquipRank(0)['tier'] . '.' .
//            $this->getEquipRank(1)['tier'] . '.' .
//            $this->getEquipRank(2)['tier'] . '.' .
//            $this->getEquipRank(3)['tier'] . '.' .
//            str_replace(' ', '_', $this->username);
//    }
}