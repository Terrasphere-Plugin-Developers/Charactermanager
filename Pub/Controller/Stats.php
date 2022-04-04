<?php

namespace Terrasphere\Charactermanager\Pub\Controller;

use XF\Mvc\ParameterBag;
use XF\Pub\Controller\AbstractController;

class Stats extends AbstractController
{
    public function actionIndex(ParameterBag $params)
    {
        $charmasteries = $this->finder('Terrasphere\Charactermanager:CharacterMastery')
            ->with("Mastery")
            ->with("Rank")
            ->with("User")
            ->fetch();

        $top = [];
        foreach($charmasteries as $charmastery)
        {
            $id = $charmastery->Mastery['mastery_id'];
            $u = [
                'username' => $charmastery->User['username'],
                'user' => $charmastery->User,
                'rank' => [ 'name' => $charmastery->Rank['name'], 'tier' => $charmastery->Rank['tier'] ],
                'overrank' => $charmastery['overrank']
            ];
            if(!array_key_exists($id, $top))
            {
                $nu = [
                    'username' => "None",
                    'rank' => [ 'name' => "N/A", 'tier' => -1 ],
                    'overrank' => 0
                ];
                $top[$id] = [
                    'name' => $charmastery->Mastery['display_name'],
                    'img' => $charmastery->Mastery['icon_url'],
                    'color' => $charmastery->Mastery['color'],
                    'masters' => [0 => $u, 1 => $nu, 2 => $nu]
                ];
            }
            else
            {
                if($this->getHigherRank($u, $top[$id]['masters'][2]) == $u)
                {
                    if($this->getHigherRank($u, $top[$id]['masters'][1]) == $u)
                    {
                        if($this->getHigherRank($u, $top[$id]['masters'][0]) == $u)
                        {
                            // New #1.
                            $top[$id]['masters'][2] = $top[$id]['masters'][1];
                            $top[$id]['masters'][1] = $top[$id]['masters'][0];
                            $top[$id]['masters'][0] = $u;
                        }
                        else
                        {
                            // New #2.
                            $top[$id]['masters'][2] = $top[$id]['masters'][1];
                            $top[$id]['masters'][1] = $u;
                        }
                    }
                    else
                    {
                        // New #3.
                        $top[$id]['masters'][2] = $u;
                    }
                }
                // Not top 3.
            }
        }

        return $this->view('Terrasphere\Charactermanager:Stats', 'terrasphere_cm_stats', ['masters_list' => $top]);
    }

    private function getHigherRank(array $a1, array $a2) : array
    {
        if($a1['overrank'] > $a2['overrank'])
            return $a1;
        elseif ($a1['overrank'] < $a2['overrank'])
            return $a2;
        if($a2['rank']['tier'] > $a1['rank']['tier'])
            return $a2;
        return $a1;
    }
}