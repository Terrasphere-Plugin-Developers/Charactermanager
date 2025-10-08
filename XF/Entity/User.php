<?php

namespace Terrasphere\Charactermanager\XF\Entity;

use Terrasphere\Charactermanager\DBTableInit;
use Terrasphere\Charactermanager\Entity\CharacterEquipment;
use Terrasphere\Charactermanager\Entity\CharacterExpertise;
use Terrasphere\Charactermanager\Entity\CharacterMastery;
use Terrasphere\Charactermanager\Entity\CharacterRaceTrait;
use Terrasphere\Core\Entity\Rank;
use XF;

class User extends XFCP_User
{
    protected function _postDelete()
    {
        parent::_postDelete();

        $db = $this->db();
        foreach(DBTableInit::getUserTablesForPostDeletion() as $tableName)
            $db->delete($tableName, 'user_id = ?', $this->user_id);
    }

    public function getTraitCumulativeCost() : int
    {
        $traitCount = count($this->getTraits());
        $payedTraits =  $traitCount - XF::options()['terrasphereCMFreeRaceTraits'];
        if($payedTraits <= 0) return 0;
        return $payedTraits * XF::options()['terrasphereRaceTraitCost'];
    }

    public function getTraitRefund() : int
    {
        return XF::options()['terrasphereRaceTraitRefundMulti'] * 0.01 * $this->getTraitCumulativeCost();
    }

    public function canLinkCharacterSheet() : int
    {
        return (int) XF::visitor()->hasPermission('terrasphere', 'terrasphere_cm_review');
    }

    public function canShowCharacterElements() : int
    {
        return (int) $this->hasPermission('terrasphere', 'terrasphere_cm_showcs');
    }

    public function getUsergroupBanners() : array
    {
        // Get user from ID
        /** @var XF\Entity\User $user */
        $user = $this->em()->find('XF:User', $this->user_id);
        $userGroups = $user->getUserGroupRepo(); // Includes primary + secondary groups
        return $userGroups->getUserBannerCacheData();
    }

    public function getCharacterRank() : string
    {
        $masteries = $this->getMasteries();

        $averageEquipmentTier = 0;
        $averageEquipmentTier += $this->getOrInitiateWeapon()['Rank']['tier'];
        $averageEquipmentTier += $this->getOrInitiateArmor()['Rank']['tier'];
        $averageEquipmentTier += $this->getOrInitiateAccessory()['Rank']['tier'];

        $averageEquipmentTier = floor($averageEquipmentTier / 3);

        // Find highest mastery rank
        $highestRank = null;
        foreach($masteries as $mastery)
        {
            if($highestRank == null || $mastery->Rank->tier > $highestRank->tier)
                $highestRank = $mastery->Rank;
        }

        // Find highest expertise rank

        return ($highestRank != null ? $highestRank->name : "Beginner")." (+".$averageEquipmentTier.")";
    }

    /**
     * Gets all masteries for the character/user.
     */
    public function getMasteries() : array
    {
        $masteries = CharacterMastery::getCharacterMasteries($this, $this->user_id);
        return $masteries;
    }

    /**
     * Gets all expertise for the character/user.
     */
    public function getExpertises() : array
    {
        $expertises = CharacterExpertise::getCharacterExpertises($this, $this->user_id);
        return $expertises;
    }

    /**
     * Get an array of all traits, indexed by the slot index.
     * WARNING: This does NOT include innate traits; only those specifically owned by the user.
     */
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
        /** @var CharacterEquipment $armor */
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

    public function getOrInitiateAccessory() : CharacterEquipment
    {
        /** @var CharacterEquipment $accessory */
        $accessory = $this->finder('Terrasphere\Charactermanager:CharacterEquipment')
            ->with('Equipment')
            ->where([
                ['user_id', $this->user_id],
                ['Equipment.equip_group', 'Accessory'],
            ])
            ->fetchOne();
        
        if(!$accessory) {
            $accessory = $this->em()->create('Terrasphere\Charactermanager:CharacterEquipment');
            $accessory->user_id = $this->user_id;
            $accessory->rank_id = Rank::minRank($this)->rank_id;
            $accessory->equipment_id = $this->finder('Terrasphere\Core:Equipment')
                ->where('display_name', 'Combat Accessory')
                ->fetchOne()->equipment_id;
            $accessory->save();
        }

        return $accessory;
    }
}