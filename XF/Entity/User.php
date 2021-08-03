<?php

namespace Terrasphere\Charactermanager\XF\Entity;

use Terrasphere\Charactermanager\Entity\CharacterMastery;
use Terrasphere\Charactermanager\Entity\Equipment;
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

    /**
     * @return XF\Mvc\Entity\Entity An Equipment entity for the user's weapon.
     */
    public function getWeapon() : XF\Mvc\Entity\Entity
    {
        $weapon = $this->finder('Terrasphere\Charactermanager:Equipment')
            ->with(['User', 'Rank'])
            ->where('user_id', $this->user_id)
            ->where('type', 0)
            ->fetchOne();
        return $this->createEquipIfNotExist($weapon, 0);
    }

    /**
     * @return XF\Mvc\Entity\Entity An Equipment entity for the user's armor.
     */
    public function getArmor() : XF\Mvc\Entity\Entity
    {
        $armor = $this->finder('Terrasphere\Charactermanager:Equipment')
            ->with(['User', 'Rank'])
            ->where('user_id', $this->user_id)
            ->where('type', 1)
            ->fetchOne();
        return $this->createEquipIfNotExist($armor, 1);
    }

    /**
     * @return array An array of misc equipment entities (no weapon/armor).
     */
    public function getMiscEquips() : array
    {
        $other = $this->finder('Terrasphere\Charactermanager:Equipment')
            ->with(['User', 'Rank'])
            ->where('user_id', $this->user_id)
            ->where('type', 2)
            ->fetch();
        return $other->toArray();
    }

    /**
     * Gets all equipment in an array for the player.
     * Index 0 is always weapon.
     * Index 1 is always armor.
     * Index 2 is always an array which contains 0 or more "additional equipment."
     */
    public function getEquipment() : array
    {
        $weapon = $this->getWeapon();
        $armor = $this->getArmor();
        $other = $this->getMiscEquips();

        return [ $weapon, $armor, $other ];
    }

    /**
     * @throws XF\PrintableException
     */
    private function createEquipIfNotExist($equip, $typeID) : XF\Mvc\Entity\Entity
    {
        if($equip == null)
        {
            /** @var Equipment $equip */
            $equip = $this->em()->create('Terrasphere\Charactermanager:Equipment');
            $equip['user_id'] = $this->user_id;
            $equip['rank_id'] = 0;
            $equip['type'] = $typeID;
            $equip['name'] = '';
            $equip['post_link'] = '';

            if($this->exists() && $this->user_id != 0)
            {
                try {
                    $equip->save();
                } catch (XF\PrintableException $e) {
                    throw $e;
                } catch (\Exception $e) {
                    throw $e;
                }

                $equip = $this->finder('Terrasphere\Charactermanager:Equipment')
                    ->with(['User', 'Rank'])
                    ->where('user_id', $this->user_id)
                    ->where('type', $typeID)
                    ->fetchOne();
            }
        }
        return $equip;
    }
}