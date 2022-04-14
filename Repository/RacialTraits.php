<?php

namespace Terrasphere\Charactermanager\Repository;

use Terrasphere\Charactermanager\Entity\RacialTrait;
use Terrasphere\Charactermanager\XF\Entity\User;
use XF\Mvc\Entity\Repository;

class RacialTraits extends Repository
{
    /**
     * Get an array a two-tuples for all Racial Traits.
     *
     * 'name' => string
     * 'racialTraits' => array of RacialTrait entities
     */
    public function getRacialTraitsByUsergroups(): array
    {
        $queryResults = $this
            ->finder('Terrasphere\Charactermanager:RacialTrait')
            ->order('name', 'ASC')
            ->fetch();

        $groups = $queryResults->groupBy('cached_group_id');
        $ret = [];

        foreach ($groups as $group) {
            $firstIndex = array_keys($group)[0];
            if ($group[$firstIndex]['cached_group_id'] == -1) {
                array_push($ret, [
                    'name' => "Shared",
                    'racialTraits' => $group
                ]);
            } else {
                $usergroup = $this
                    ->finder('XF:UserGroup')
                    ->whereId($group[$firstIndex]['cached_group_id'])
                    ->fetchOne();

                array_push($ret, [
                    'name' => $usergroup['title'],
                    'racialTraits' => $group
                ]);
            }
        }

        return $ret;
    }

    /**
     * Gets an array of new racial traits which may be selected by the given user.
     */
    public function getTraitSelectionsAvailableForUser(User $user) : array
    {
        $ret = [];
        $currentTraits = $this->finder('Terrasphere\Charactermanager:CharacterRaceTrait')
            ->where('user_id', $user->user_id)
            ->with('RacialTrait')
            ->order('slot_index', 'ASC')
            ->fetch();

        $exclusions = [];
        foreach ($currentTraits as $t) $exclusions[$t->RacialTrait['exclusivity']] = true;

        $where = [['cached_group_id', -1], ['cached_group_id', $user->user_group_id]];
        foreach ($user->secondary_group_ids as $id) array_push($where, ['cached_group_id', $id]);

        $racialTraits =
            $this->finder('Terrasphere\Charactermanager:RacialTrait')
            ->whereOr($where)
            ->where('autoselect', false) // Skip traits that are auto-selected.
            ->fetch()->toArray();

        foreach ($racialTraits as $option)
        {
            // Check if player already selected this trait.
            $exists = false;
            foreach ($currentTraits as $trait)
            {
                if($option['race_trait_id'] == $trait->RacialTrait['race_trait_id'])
                {
                    $exists = true;
                    break;
                }
            }

            // Skip if selected already.
            if($exists)
                continue;

            // Skip if a trait is already selected from a non-negative-one exclusivity group.
            if($option['exclusivity'] != -1 && array_key_exists($option['exclusivity'], $exclusions))
                continue;

            // For shared traits, skip if our user doesn't have one of the appropriate user groups.
            if(!$this->doesUserHaveGroupForTrait($user, $option['race_trait_id']))
                continue;

            // If we make it to here, we're good to push it to the return array of valid options.
            array_push($ret, $option);
        }

        return $ret;
    }

    /**
     * Get an array of 3-tuples. At least n will exist, indexed 0 to n, where n is the max trait count. Others may also
     * exist with index = -1, all of which will be non-empty, innate, auto-selected traits.
     *
     * 'isEmpty' => 2 if lowest empty slot, 1 if other empty slot, 0 otherwise.
     * 'slotIndex' => Index for this slot.
     * 'trait' => If isEmpty is 0, this contains the trait; otherwise it's null.
     */
    public function getRacialTraitSlotsForUser(User $user) : array
    {
        $userTraits = $this->finder('Terrasphere\Charactermanager:CharacterRaceTrait')
            ->where('user_id', $user->user_id)
            ->with('RacialTrait')
            ->order('slot_index', 'ASC')
            ->fetch()->toArray();

        // Set up the main entity slots.
        $firstEmptySlotMarked = false;
        $ret = [];
        for($i = 0; $i < $this->options()['terrasphereCMMaxRaceTraits']; $i++)
        {
            $found = false;
            foreach ($userTraits as $t)
            {
                if($t['slot_index'] == $i)
                {
                    array_push($ret, ['isEmpty' => 0, 'slotIndex' => $i, 'trait' => $t]);
                    $found = true;
                    break;
                }
            }
            if($found) continue;

            array_push($ret, ['isEmpty' => ($firstEmptySlotMarked ? 1 : 2), 'slotIndex' => $i, 'trait' => 0]);
            $firstEmptySlotMarked = true;
        }


        // Append innate traits.
        $innateTraits = $this->finder('Terrasphere\Charactermanager:RacialTrait')
            ->where('autoselect', true)
            ->fetch()->toArray();

        foreach ($innateTraits as $it)
        {
            if($this->doesUserHaveGroupForTrait($user, $it['race_trait_id']))
            {
                $dummy = [];
                $dummy['race_trait_id'] = 0;
                $dummy['user_id'] = $user['user_id'];
                $dummy['slot_index'] = -1;
                $dummy['RacialTrait'] = $it;
                $d = ['isEmpty' => false, 'slotIndex' => -1, 'trait' => $dummy];
                $ret = array_merge([$d], $ret);
            }
        }

        return $ret;
    }

    public function canUserGroupAccessRacialTrait($groupID, $traitID) : bool
    {
        return $this->db()->fetchOne('SELECT * FROM xf_terrasphere_cm_racial_trait_group_access WHERE race_trait_id = "'.$traitID.'" AND group_id = "'.$groupID.'"') != null;
    }

    /**
     * True if the user has one of the groups required for the trait.
     */
    public function doesUserHaveGroupForTrait(User $user, $traitID) : bool
    {
        $result = $this->db()->fetchAll('SELECT * FROM xf_terrasphere_cm_racial_trait_group_access WHERE race_trait_id = ' . $traitID);
        foreach ($result as $r)
            if ($user->user_group_id == $r['group_id'] || in_array($r['group_id'], $user->secondary_group_ids))
                return true;
        return false;
    }

    /**
     * Allows access to this trait for all groups with IDs given in the second parameter.
     *
     * Note: this does not change 'cached_group_id' which will need to be edited elsewhere.
     */
    public function setUserGroupAccessForRacialTrait(RacialTrait $trait, array $groupIDs)
    {
        // Wipe all current entries related to this trait.
        $this->db()->delete(
            'xf_terrasphere_cm_racial_trait_group_access',
            'race_trait_id = "' . $trait['race_trait_id'] . '"'
        );

        if (empty($groupIDs)) return;

        // Add an entry for each group.
        $rows = [];
        foreach ($groupIDs as $id) {
            array_push($rows, [
                'race_trait_id' => $trait['race_trait_id'],
                'group_id' => $id
            ]);
        }

        $this->db()->insertBulk('xf_terrasphere_cm_racial_trait_group_access', $rows);
    }

    public function getRaceTraitCostForUser($user) : int
    {
        $filledSlots = 0;
        foreach ($this->getRacialTraitSlotsForUser($user) as $slot)
            if(!$slot['isEmpty'])
                $filledSlots++;

        if($this->options()['terrasphereCMFreeRaceTraits'] > $filledSlots)
            return 0;
        return $this->options()['terrasphereRaceTraitCost'];
    }
}