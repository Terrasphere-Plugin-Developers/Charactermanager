<?php

namespace Terrasphere\Charactermanager\Pub\Controller;

use Terrasphere\Charactermanager\Entity\CharacterEquipment;
use Terrasphere\Charactermanager\Entity\CharacterExpertise;
use Terrasphere\Charactermanager\Entity\CharacterMastery;
use Terrasphere\Charactermanager\Helper\CharacterSheetHelper;
use Terrasphere\Core\Entity\RankSchema;
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
        $equipmentCumulativeCost = $this->getEquipmentCumulativeCost($user);
        $equipmentRefund = $this->getEquipmentRefund($user);
        $equipmentRefundCurrency = $this->getEquipmentRefundCurrency();
        $viewParams = [
            "masteries" => $masteries,
            "expertises" => $expertises,
            "traitCost" => $user->getTraitCumulativeCost(),
            "traitRefund" => $user->getTraitRefund(),
            "traitCurrency" => $currency,
            "traitCount" => count($user->getTraits()),
            "equipmentCumulativeCost" => $equipmentCumulativeCost,
            "equipmentRefund" => $equipmentRefund,
            "equipmentRefundCurrency" => $equipmentRefundCurrency,
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
        $equipment_id = $this->filter('equipment_id', 'uint');
        if(\XF::visitor()['user_id'] != $user_id)
            return $this->error("Users don't match: visitor " . \XF::visitor()['user_id'] . ", target " . $user_id);

        /** @var \Terrasphere\Charactermanager\XF\Entity\User $user */
        $user = $this->assertViewableUser($user_id);


        if($mastery_id != null)
            return $this->masteryRefund($user_id, $mastery_id, $user, $params);

        if($expertise_id != null)
            return $this->expertiseRefund($user_id, $expertise_id, $user, $params);

        if($equipment_id != null)
            return $this->equipmentRefund($user);

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

        CharacterSheetHelper::adjustCurrency($this, $user, $refundAmount, $msg, $refundCurrency, false);
        foreach($user->getTraits() as $trait)
            $trait->delete();

        return $this->redirect($this->buildLink('terrasphere/refund', null));
    }

    protected function equipmentRefund($user)
    {
        /** @var CharacterEquipment $weapon */
        $weapon = $user->getOrInitiateWeapon();
        /** @var CharacterEquipment $armor */
        $armor = $user->getOrInitiateArmor();
        /** @var CharacterEquipment $accessory */
        $accessory = $user->getOrInitiateAccessory();

        $equipmentRankSchemaID = $this->app()->options()->terrasphereEquipmentRankSchema;

        $refund = $this->getEquipmentRefund($user);
        $refundCurrency = $this->getEquipmentRefundCurrency();
        $msg = "Refunded Equipment";

        // Set the ranks to rank at tier 0
        $baseRank = $this->finder('Terrasphere\Core:RankSchemaMap')
            ->with('Rank')
            ->where([
                ['rank_schema_id', $equipmentRankSchemaID],
                ['Rank.tier', '=', 0],
            ])
            ->fetchOne();

        if (!$baseRank) {
            return $this->error("Base rank not found...");
        }

        $weapon->rank_id = $baseRank->rank_id;
        $weapon->save();
        $armor->rank_id = $baseRank->rank_id;
        $armor->save();
        $accessory->rank_id = $baseRank->rank_id;
        $accessory->save();

        CharacterSheetHelper::adjustCurrency($this, $user, $refund, $msg, $refundCurrency['currency_id'], false);
        return $this->redirect($this->buildLink('terrasphere/refund'));
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
        $refundAmount = $mastery->Rank->getMasteryRefund($mastery->Mastery->getRankSchema());
        $refundCurrency = $rankSchema['currency_id'];
        $msg = "Refunded " . $mastery->Mastery['display_name'] . " Rank " . $mastery->Rank['name'];

        $mastery->delete();
        CharacterSheetHelper::adjustCurrency($this, $user, $refundAmount, $msg, $refundCurrency, false);
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
        $refundAmount = $expertise->Rank->getMasteryRefund($expertise->Expertise->getRankSchema());
        $refundCurrency = $rankSchema['currency_id'];
        $msg = "Refunded " . $expertise->Expertise['display_name'] . " Rank " . $expertise->Rank['name'];

        $expertise->delete();
        CharacterSheetHelper::adjustCurrency($this, $user, $refundAmount, $msg, $refundCurrency, false);
        return $this->redirect($this->buildLink('terrasphere/refund', null));
    }

    public function getEquipmentCumulativeCost($user) : int
    {
        $weapon = $user->getOrInitiateWeapon();
        $armor = $user->getOrInitiateArmor();
        $accessory = $user->getOrInitiateAccessory();

        $equipmentRankSchemaID = $this->app()->options()->terrasphereEquipmentRankSchema;
        /** @var RankSchema $rankSchema */
        $rankSchema = $this->finder('Terrasphere\Core:RankSchema')->with('Currency')
            ->where('rank_schema_id', $equipmentRankSchemaID)->fetchOne();

        if($rankSchema == null)
            return 0;

        $weaponCumulativeCost = $weapon['Rank']->getCumulativeCost($rankSchema);
        $armorCumulativeCost = $armor['Rank']->getCumulativeCost($rankSchema);
        $accessoryCumulativeCost = $accessory['Rank']->getCumulativeCost($rankSchema);

        return $weaponCumulativeCost + $armorCumulativeCost + $accessoryCumulativeCost;
    }

    public function getEquipmentRefund($user) : int
    {
        $weapon = $user->getOrInitiateWeapon();
        $armor = $user->getOrInitiateArmor();
        $accessory = $user->getOrInitiateAccessory();

        $equipmentRankSchemaID = $this->app()->options()->terrasphereEquipmentRankSchema;
        /** @var RankSchema $rankSchema */
        $rankSchema = $this->finder('Terrasphere\Core:RankSchema')->with('Currency')
            ->where('rank_schema_id', $equipmentRankSchemaID)->fetchOne();

        if($rankSchema == null)
            return 0;

        $weaponCumulativeRefund = $weapon['Rank']->getEquipmentRefund($rankSchema);
        $armorCumulativeRefund = $armor['Rank']->getEquipmentRefund($rankSchema);
        $accessoryCumulativeRefund = $accessory['Rank']->getEquipmentRefund($rankSchema);

        return $weaponCumulativeRefund + $armorCumulativeRefund + $accessoryCumulativeRefund;
    }

    public function getEquipmentRefundCurrency()
    {
        $equipmentRankSchemaID = $this->app()->options()->terrasphereEquipmentRankSchema;
        /** @var RankSchema $rankSchema */
        $rankSchema = $this->finder('Terrasphere\Core:RankSchema')->with('Currency')
            ->where('rank_schema_id', $equipmentRankSchemaID)->fetchOne();
        return $rankSchema->Currency;
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
