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

    public function canUserGroupAccessRacialTrait($groupID, $traitID) : bool
    {
        return $this->db()->fetchOne('SELECT * FROM xf_terrasphere_cm_racial_trait_group_access WHERE race_trait_id = "'.$traitID.'" AND group_id = "'.$groupID.'"') != null;
    }

    public function canUserChooseRacialTrait(User $user, $traitID): bool
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
     * Note: this does not change 'cached_group_id' which has no impact on functionality, but can improve display
     * for the admin panel.
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