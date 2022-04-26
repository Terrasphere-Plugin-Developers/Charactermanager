<?php

namespace Terrasphere\Charactermanager\Pub\Controller;

use Terrasphere\Charactermanager\Entity\CharacterMastery;
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
        $currency = $this->assertRecordExists('DBTech\Credits:Currency', $this->options()['terrasphereRaceTraitCurrency'], null, null);
        $viewParams = [
            "masteries" => $masteries,
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
        if(\XF::visitor()['user_id'] != $user_id)
            return $this->error("Users don't match: visitor " . \XF::visitor()['user_id'] . ", target " . $user_id);

        /** @var \Terrasphere\Charactermanager\XF\Entity\User $user */
        $user = $this->assertViewableUser($user_id);

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
        $this->adjustCurrency($user, $refundAmount, $msg, $refundCurrency);
        return $this->actionIndex($params);
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

        $this->adjustCurrency($user, $refundAmount, $msg, $refundCurrency);
        foreach($user->getTraits() as $trait)
            $trait->delete();

        return $this->actionIndex($params);
    }

    private function adjustCurrency(User $user, int $gain, string $message, int $currencyID)
    {
        /** @var Currency $currency */
        $currency = $this->assertRecordExists('DBTech\Credits:Currency', $currencyID, null, null);

        $input = [
            'username' => $user['username'],
            'amount' => $gain,
            'message' => $message,
            'negate' => false
        ];

        /** @var \DBTech\Credits\EventTrigger\Adjust $adjustEvent */
        $adjustEvent = $this->repository('DBTech\Credits:EventTrigger')->getHandler('adjust');
        if (!$adjustEvent->isActive())
        {
            return $this->error(\XF::phrase('dbtech_credits_invalid_eventtrigger'));
        }

        // Make sure this is set
        $currency->verifyAdjustEvent();

        $multiplier = $input['negate'] ? (-1 * $input['amount']) : $input['amount'];

        // First test add or remove credits
        $adjustEvent->testApply([
            'currency_id' => $currency->currency_id,
            'multiplier' => $multiplier,
            'message' => $message,
            'source_user_id' => $user->user_id,
        ], $user);

        // Then properly add or remove credits
        $adjustEvent->apply($user->user_id, [
            'currency_id' => $currency->currency_id,
            'multiplier' => $multiplier,
            'message' => $message,
            'source_user_id' => $user->user_id,
        ], $user);

        return true;
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
