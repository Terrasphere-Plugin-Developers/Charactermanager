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

    public function getEquipmentRankCap() : int
    {
        return Rank::maxRank($this)['tier'];
    }

    public function getMasteryRankCap($slotIndex = null) : int
    {
        $maxRank = Rank::maxRank($this);

        $isAlter = false;
        if($slotIndex != null)
        {
            $masterySlots = CharacterMastery::getCharacterMasterySlots($this, $this->user_id);

            $slot = $this->finder('Terrasphere\Core:Mastery')
                ->where('mastery_id', $masterySlots[(int) $slotIndex]['mastery_id'])
                ->fetchOne();

            if($slot != null && $slot['mastery_type_id'] != 1)
                $isAlter = true;
        }

        if($isAlter)
            return $maxRank['tier'];

        $minusOne = $maxRank->getPreviousRank();
        $minusTwo = $minusOne->getPreviousRank();

        $masteries = $this->getMasteries();
        $countAtMinusOne = 0; // Only counts standard masteries
        foreach($masteries as $mastery)
            if($mastery->Mastery['mastery_type_id'] == 1 && $mastery->Rank == $minusOne)
                $countAtMinusOne++;

        if($countAtMinusOne < 2)
            return $minusOne['tier'];

        return $minusTwo['tier'];
    }

    public function getExpertiseRankCap($slotIndex = null) : int
    {
        $maxRank = Rank::maxRank($this);
        $minusOne = $maxRank->getPreviousRank();
        $minusTwo = $minusOne->getPreviousRank();

        $expertises = $this->getExpertises();
        $countAtMax = 0;
        $countAtMinusOne = 0;
        foreach($expertises as $expertise)
        {
            if($expertise->Rank['tier'] > $minusTwo['tier'])
                $countAtMinusOne++;
            if($expertise->Rank['tier'] > $minusOne['tier'])
                $countAtMax++;
        }

        // If there's no slot at (max), and this slot is (max - 1), it can be (max).
        $expertiseSlots = CharacterExpertise::getCharacterExpertiseSlots($this, $this->user_id);
        if($expertiseSlots[(int) $slotIndex]['rank_id'] == $minusOne['rank_id'] && $countAtMax < 1)
            return $maxRank['tier'];

        // Otherwise, if there are 3 slots which have reached (max - 1), it can only be (max - 2).
        if($countAtMinusOne >= 3)
        {
            // Otherwise, it can only be (max - 2).
            return $minusTwo['tier'];
        }

        // Otherwise, it can reach (max - 1), possibly reaching (max) later via condition 1.
        if($countAtMax >= 1)
            return $minusOne['tier'];

        // Default to (max), before the conditions take over.
        return $maxRank['tier'];
    }

    public function getUsergroupBanners() : array
    {
        // Get user from ID
        /** @var XF\Entity\User $user */
        $user = $this->em()->find('XF:User', $this->user_id);

        $userGroupIds = array_merge([$user->user_group_id], $user->secondary_group_ids);

        $groups = \XF::finder('XF:UserGroup')
            ->where('user_group_id', $userGroupIds)
            ->fetch();


        $groupsWithBanners = [];

        foreach ($groups as $group)
        {
            if ($group->banner_text)
            {
                $groupsWithBanners[] = [
                    'title' => $group->title,
                    'text' => $group->banner_text,
                    'class' => $group->banner_css_class
                ];
            }
        }

        return $groupsWithBanners;
    }

    private function getRankValue($rankNum) : int
    {
        $RANK_BONUSES = [
            0 => 0,   // E rank
            1 => 10,  // D rank
            2 => 15,  // C rank
            3 => 25,  // B rank
            4 => 30,  // A rank
            5 => 40   // S rank
        ];

        return $RANK_BONUSES[$rankNum] ?? 0;
    }

    public function getCharacterRank() : string
    {
        $weaponRank = $this->getOrInitiateWeapon()['Rank']['tier'];
        $armorRank = $this->getOrInitiateArmor()['Rank']['tier'];
        $equipmentRank = $this->getOrInitiateAccessory()['Rank']['tier'];

        $weaponScore = $this->getRankValue($weaponRank);
        $armorScore = $this->getRankValue($armorRank);
        $equipmentScore = $this->getRankValue($equipmentRank);

        $masteries = $this->getMasteries();
        $masteryScore = 0;
        foreach($masteries as $mastery)
            $masteryScore += $this->getRankValue($mastery->Rank['tier']);

        $expertises = $this->getExpertises();
        $expertiseScore = 0;
        foreach($expertises as $expertise)
            $expertiseScore += $this->getRankValue($expertise->Rank['tier']);

        $totalScore = $weaponScore + $armorScore + $equipmentScore + $masteryScore + ($expertiseScore / 4);

        $characterRanks = [
            0 => 'E',
            31 => 'E+',
            62 => 'D',
            93 => 'D+',
            124 => 'C',
            155 => 'C+',
            186 => 'B',
            217 => 'B+',
            248 => 'A',
            279 => 'A+',
            310 => 'S',
            339 => 'S+',
        ];

        $ret = $characterRanks[0];

        foreach($characterRanks as $scoreThreshold => $name)
            if($totalScore >= $scoreThreshold)
                $ret = $name;

        return $ret;
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