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
            ->finder('Terrasphere\CharacterManager:RacialTrait')
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
        $currentTraits = $this->finder('Terrasphere\CharacterManager:CharacterRaceTrait')
            ->where('user_id', $user->user_id)
            ->with('RacialTrait')
            ->order('slot_index', 'ASC')
            ->fetch();

        $where = [['cached_group_id', -1], ['cached_group_id', $user->user_group_id]];
        foreach ($user->secondary_group_ids as $id) array_push($where, ['cached_group_id', $id]);

        $racialTraits =
            $this->finder('Terrasphere\CharacterManager:RacialTrait')
            ->whereOr($where)
            ->fetch()->toArray();

        foreach ($racialTraits as $option)
        {
            // TODO Does PHP suffer from concurrent modification errors...?

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


            // For shared traits, skip if our user doesn't have one of the appropriate user groups.
            if(!$this->canUserChooseRacialTrait($user, $option['race_trait_id']))
                continue;

            // If we make it to here, we're good to push it to the return array of valid options.
            array_push($ret, $option);
        }

        return $ret;
    }

    /**
     * Get a 5-element array of 3-tuples, indexed 0 to 4.
     *
     * 'isEmpty' => 2 if lowest empty slot, 1 if other empty slot, 0 otherwise.
     * 'slotIndex' => Index for this slot.
     * 'trait' => If isEmpty is 0, this contains the trait; otherwise it's null.
     */
    public function getRacialTraitSlotsForUser(User $user) : array
    {
        $userTraits = $this->finder('Terrasphere\CharacterManager:CharacterRaceTrait')
            ->where('user_id', $user->user_id)
            ->with('RacialTrait')
            ->order('slot_index', 'ASC')
            ->fetch()->toArray();

        $firstEmptySlotMarked = false;
        $ret = [];
        for($i = 0; $i < 5; $i++)
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

        return $ret;
    }

    public function canUserGroupAccessRacialTrait($groupID, $traitID) : bool
    {
        return $this->db()->fetchOne('SELECT * FROM xf_terrasphere_cm_racial_trait_group_access WHERE race_trait_id = "'.$traitID.'" AND group_id = "'.$groupID.'"') != null;
    }

    /**
     * If the user can pick this racial trait. This doesn't check for duplicate selections.
     */
    public function canUserChooseRacialTrait(User $user, $traitID) : bool
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
}