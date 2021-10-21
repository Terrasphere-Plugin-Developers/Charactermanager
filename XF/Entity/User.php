<?php

namespace Terrasphere\Charactermanager\XF\Entity;

use Terrasphere\Charactermanager\Entity\CharacterEquipment;
use Terrasphere\Charactermanager\Entity\CharacterMastery;
use Terrasphere\Charactermanager\Entity\CharacterRaceTrait;
use Terrasphere\Core\Entity\Rank;
use XF;

class User extends XFCP_User
{
    public function canLinkCharacterSheet() : int
    {
        return (int) XF::visitor()->hasPermission('terrasphere', 'terrasphere_cm_review');
    }

    public function canShowCharacterElements() : int
    {
        return (int) $this->hasPermission('terrasphere', 'terrasphere_cm_showcs');
    }

    /**
     * Gets all masteries for the character/user.
     */
    public function getMasteries() : array
    {
        $masteries = CharacterMastery::getCharacterMasteries($this, $this->user_id);
        return $masteries;
    }

    public function getTraits(): array{
        $traits = CharacterRaceTrait::getCharacterTraits($this,$this->user_id);
        return $traits;
    }
    public function getBannerButtons() : array
    {
        return $this->finder('Terrasphere\Core:BannerButton')->fetch()->toArray();
    }

    public function getOrInitiateWeapon() : CharacterEquipment
    {
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
            $weapon->rank_id = Rank::minRank($this)->rank_id;
            $weapon->equipment_id = $weaponEquiId;
            $weapon->save();
        }

        return $weapon;
    }

    public function getOrInitiateArmor() : CharacterEquipment
    {
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
            $armor->rank_id = Rank::minRank($this)->rank_id;
            $armor->equipment_id = $this->finder('Terrasphere\Core:Equipment')
                ->where('display_name','Medium Armor')
                ->fetchOne()->equipment_id;
            $armor->save();
        }

        return $armor;
    }
}