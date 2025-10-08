<?php

namespace Terrasphere\Charactermanager\Pub\Controller;

use Terrasphere\Charactermanager\Entity\CharacterExpertise;
use Terrasphere\Charactermanager\Entity\CharacterMastery;
use Terrasphere\Charactermanager\Helper\CharacterSheetHelper;
use XF\Entity\User;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\Exception;
use XF\PrintableException;
use XF\Pub\Controller\AbstractController;

class Refund extends AbstractController
{
    public function actionIndex(ParameterBag $params)
    {
        $user = \XF::visitor();
        $masteries = CharacterMastery::getCharacterMasteries($this, $user->user_id);
        $expertises = CharacterExpertise::getCharacterExpertises($this, $user->user_id);
        $currency = $this->assertRecordExists('DBTech\Credits:Currency', $this->options()['terrasphereRaceTraitCurrency'], null, null);
        $viewParams = [
            "masteries" => $masteries,
            "expertises" => $expertises,
            "traitCost" => $user->getTraitCumulativeCost(),
            "traitRefund" => $user->getTraitRefund(),
            "traitCurrency" => $currency,
            "traitCount" => count($user->getTraits())
        ];
        return $this->view('Terrasphere\Charactermanager:Refund', 'terrasphere_cm_refund', $viewParams);
    }

    public function actionConfirm(ParameterBag $params)
    {
        return $this->view('Terrasphere\Charactermanager:Refund', 'terrasphere_cm_refund_confirm', []);
    }

    /**
     * @throws Exception
     * @throws PrintableException
     */
    public function actionRefund(ParameterBag $params)
    {
        $user_id = $this->filter('user_id', 'uint');
        $mastery_id = $this->filter('mastery_id', 'uint');
        $expertise_id = $this->filter('expertise_id', 'uint');
        if(\XF::visitor()['user_id'] != $user_id)
            return $this->error("Users don't match: visitor " . \XF::visitor()['user_id'] . ", target " . $user_id);

        /** @var \Terrasphere\Charactermanager\XF\Entity\User $user */
        $user = $this->assertViewableUser($user_id);


        if($mastery_id != null)
            return $this->masteryRefund($user_id, $mastery_id, $user, $params);

        if($expertise_id != null)
            return $this->expertiseRefund($user_id, $expertise_id, $user, $params);

        return null;
    }

    /**
     * @throws Exception
     * @throws PrintableException
     */
    public function actionRefundTraits(ParameterBag $params)
    {
        $user_id = $this->filter('user_id', 'uint');
        if(\XF::visitor()['user_id'] != $user_id)
            return $this->error("Users don't match: visitor " . \XF::visitor()['user_id'] . ", target " . $user_id);

        /** @var \Terrasphere\Charactermanager\XF\Entity\User $user */
        $user = $this->assertViewableUser($user_id);

        $refundAmount = $user->getTraitRefund();
        $refundCurrency = $this->options()['terrasphereRaceTraitCurrency'];
        $msg = "Refunded Race Traits";

        CharacterSheetHelper::adjustCurrency($this, $user, $refundAmount, $msg, $refundCurrency);
        foreach($user->getTraits() as $trait)
            $trait->delete();

        return $this->redirect($this->buildLink('terrasphere/refund', null));
    }

    protected function masteryRefund($user_id, $mastery_id, $user, $params)
    {
        $masteries = CharacterMastery::getCharacterMasteries($this, $user_id);
        /** @var CharacterMastery $mastery */
        $mastery = null;
        foreach ($masteries as $m)
        {
            if($m->Mastery['mastery_id'] == $mastery_id)
            {
                $mastery = $m;
                break;
            }
        }

        if($mastery == null)
            return $this->error("Mastery not found...");

        $rankSchema = $mastery->Mastery->getRankSchema();
        $refundAmount = $mastery->Rank->getRefund($mastery->Mastery->getRankSchema());
        $refundCurrency = $rankSchema['currency_id'];
        $msg = "Refunded " . $mastery->Mastery['display_name'] . " Rank " . $mastery->Rank['name'];

        $mastery->delete();
        CharacterSheetHelper::adjustCurrency($this, $user, $refundAmount, $msg, $refundCurrency);
        return $this->redirect($this->buildLink('terrasphere/refund', null));
    }

    protected function expertiseRefund($user_id, $expertise_id, $user, $params)
    {
        $expertises = CharacterExpertise::getCharacterExpertises($this, $user_id);
        /** @var CharacterExpertise $expertise */
        $expertise = null;
        foreach ($expertises as $exp)
        {
            if($exp->Expertise['expertise_id'] == $expertise_id)
            {
                $expertise = $exp;
                break;
            }
        }

        if($expertise == null)
            return $this->error("Expertise not found...");

        $rankSchema = $expertise->Expertise->getRankSchema();
        $refundAmount = $expertise->Rank->getRefund($expertise->Expertise->getRankSchema());
        $refundCurrency = $rankSchema['currency_id'];
        $msg = "Refunded " . $expertise->Expertise['display_name'] . " Rank " . $expertise->Rank['name'];

        $expertise->delete();
        CharacterSheetHelper::adjustCurrency($this, $user, $refundAmount, $msg, $refundCurrency);
        return $this->redirect($this->buildLink('terrasphere/refund', null));
    }

    protected function assertViewableUser($userId, array $extraWith = [], $basicProfileOnly = false)
    {
        $extraWith[] = 'Option';
        $extraWith[] = 'Privacy';
        $extraWith[] = 'Profile';
        array_unique($extraWith);

        /** @var \XF\Entity\User $user */
        $user = $this->em()->find('XF:User', $userId, $extraWith);
        if (!$user)
        {
            throw $this->exception($this->notFound(\XF::phrase('requested_user_not_found')));
        }

        $canView = $basicProfileOnly ? $user->canViewBasicProfile($error) : $user->canViewFullProfile($error);
        if (!$canView)
        {
            throw $this->exception($this->noPermission($error));
        }

        return $user;
    }
}
