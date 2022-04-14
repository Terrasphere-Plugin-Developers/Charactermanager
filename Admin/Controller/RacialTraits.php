<?php

namespace Terrasphere\Charactermanager\Admin\Controller;

use Terrasphere\Charactermanager\Entity\RacialTrait;
use XF\Admin\Controller\AbstractController;
use XF\Mvc\FormAction;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\View;

class RacialTraits extends AbstractController
{
    /**
     * Default for terrasphere-core/masteries should be a redirect to mastery-list. You can't actually click on
     * the link for /masteries, but if you COULD, this would fix it. 10/10; solutions to non-problems.
     *
     * @return \XF\Mvc\Reply\Redirect
     */
    public function actionIndex(): View
    {
        $viewParams = [];

        /** @var \Terrasphere\Charactermanager\Repository\RacialTraits $repo */
        $repo = $this->repository('Terrasphere\Charactermanager:RacialTraits');

        $racialTraits = $repo->getRacialTraitsByUsergroups();
        $viewParams['racialTraits'] = $racialTraits;

        return $this->view('Terrasphere\Charactermanager:RacialTrait', 'terrasphere_cm_racial_traits', $viewParams);
    }

    public function actionAddOrEdit(RacialTrait $trait): \XF\Mvc\Reply\View
    {
        /** @var \Terrasphere\Charactermanager\Repository\RacialTraits $repo */
        $repo = $this->repository('Terrasphere\Charactermanager:RacialTraits');

        // We need a way to tell the edit page if each group is selected or not.
        // It won't let you add custom data in an entity's array, but random parameters work as long as we don't save.
        // So for the purposes of the edit page, 'user_title' is a now boolean representing 'is_selected'.
        $groups = $this->finder('XF:UserGroup')->fetch();
        foreach ($groups as $group)
            $group['user_title'] = $repo->canUserGroupAccessRacialTrait($group['user_group_id'], $trait['race_trait_id']);

        $viewParams = [
            'trait' => $trait,
            'userGroups' => $this->finder('XF:UserGroup')->fetch(),
        ];

        return $this->view('Terrasphere\Charactermanager:RacialTrait\Edit', 'terrasphere_cm_racial_traits_edit', $viewParams);
    }

    public function actionAdd(): \XF\Mvc\Reply\View
    {
        $newRacialTrait = $this->em()->create('Terrasphere\Charactermanager:RacialTrait');
        return $this->actionAddOrEdit($newRacialTrait);
    }

    public function actionEdit(ParameterBag $params): \XF\Mvc\Reply\View
    {
        $racialTrait = $this->assertRecordExists('Terrasphere\Charactermanager:RacialTrait', $params['race_trait_id']);
        return $this->actionAddOrEdit($racialTrait);
    }

    public function actionSave(ParameterBag $params)
    {
        $this->assertPostOnly();

        if ($params['race_trait_id'])
            $trait = $this->assertRecordExists('Terrasphere\Charactermanager:RacialTrait', $params['race_trait_id']);
        else
            $trait = $this->em()->create('Terrasphere\Charactermanager:RacialTrait');

        $this->save($trait)->run();

        return $this->redirect($this->buildLink('terrasphere-cm/racial-traits'));
    }

    public function save(RacialTrait $trait): \XF\Mvc\FormAction
    {
        $form = $this->formAction();

        $filterItems = [
            'name' => 'str',
            'icon_url' => 'str',
            'wiki_url' => 'str',
            'tooltip' => 'str',
            'autoselect' => 'bool',
            'exclusivity' => 'int',
        ];

        $simpleEntityData = $this->filter($filterItems);

        $usergroups = $this->finder('XF:UserGroup')->fetch();
        foreach ($usergroups as $usergroup)
        {
            $filterItems['usergroup' . $usergroup['user_group_id']] = "uint";
        }

        $fullEntityData = $this->filter($filterItems);
        $selectedGroupIDs = [];

        foreach ($usergroups as $usergroup)
        {
            if( ((int) ($fullEntityData["usergroup" . $usergroup['user_group_id']])) != 0 )
                array_push($selectedGroupIDs, $usergroup['user_group_id']);
        }

        // Cached group ID is -1 if 0 or multiple are selected, and the one selected ID otherwise.
        $cachedGroupID = count($selectedGroupIDs) != 1 ? -1 : $selectedGroupIDs[0];
        $simpleEntityData['cached_group_id'] = $cachedGroupID;


        // Save Main Entity.
        $form->basicEntitySave($trait, $simpleEntityData);


        // Set user group access.
        /** @var \Terrasphere\Charactermanager\Repository\RacialTraits $repo */
        $repo = $this->repository('Terrasphere\Charactermanager:RacialTraits');

        $form->complete(function(FormAction $form) use ($repo, $trait, $selectedGroupIDs) {
            $repo->setUserGroupAccessForRacialTrait($trait, $selectedGroupIDs);
        });


        return $form;
    }

    public function actionDelete(ParameterBag $params)
    {
        /** @var \Terrasphere\Charactermanager\Entity\RacialTrait $trait */
        $trait = $this->assertRecordExists('Terrasphere\Charactermanager:RacialTrait', $params['race_trait_id']);

        /** @var \Terrasphere\Charactermanager\Repository\RacialTraits $repo */
        $repo = $this->repository('Terrasphere\Charactermanager:RacialTraits');
        $repo->setUserGroupAccessForRacialTrait($trait, []);

        /** @var \XF\ControllerPlugin\Delete $plugin */
        $plugin = $this->plugin('XF:Delete');

        return $plugin->actionDelete(
            $trait,
            $this->buildLink('terrasphere-cm/racial-traits/delete', $trait),
            $this->buildLink('terrasphere-cm/racial-traits/edit', $trait),
            $this->buildLink('terrasphere-cm/racial-traits'),
            $trait->name
        );
    }
}