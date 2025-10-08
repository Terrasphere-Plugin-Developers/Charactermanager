<?php

namespace Terrasphere\Charactermanager\Helper;

use DBTech\Credits\Entity\Currency;
use Terrasphere\Charactermanager\Entity\CharacterEquipment;
use XF\Entity\User;

class CharacterSheetHelper
{
    public static function validateMasteryUpgrade($member, $params)
    {
        // Get character's mastery instance.
        $mastery = $member->finder('Terrasphere\Charactermanager:CharacterMastery')
            ->with('Mastery')
            ->where('user_id', $params['user_id'])
            ->where('target_index', $params['target_index'])
            ->fetchOne();

        // Get the current rank.
        $thisRank = $member->finder('Terrasphere\Core:Rank')
            ->where('rank_id', $mastery['rank_id'])
            ->fetchOne();

        // Rank exists check.
        if($thisRank == null)
            return $member->error('NullRankError.');

        // Get the mastery type for the ranking schema.
        $masteryType = $member->finder('Terrasphere\Core:MasteryType')
            ->where('mastery_type_id', $mastery->Mastery->mastery_type_id)
            ->fetchOne();

        // Type exists check.
        if($masteryType == null)
            return $member->error('Mastery type missing error. Mastery: ' . $mastery['display_name'] . '.');

        // Get the next-highest rank.
        $nextRank = $member->finder('Terrasphere\Core:Rank')
            ->where('tier', $thisRank['tier']+1)
            ->fetchOne();

        // Next-highest rank check.
        if($nextRank == null)
            return $member->error('Already at max rank.');

        // Get rank schema (and the currency associated with it).
        $rankSchema = $member->finder('Terrasphere\Core:RankSchema')
            ->with('Currency')
            ->where('rank_schema_id', $masteryType['rank_schema_id'])
            ->fetchOne();

        // Rank schema exists check.
        if($rankSchema == null)
            return $member->error('No rank schema associated with this mastery type.');

        // Temporary variable to get the cost of the next rank.
        $nxt = $member->finder('Terrasphere\Core:RankSchemaMap')
            ->where('rank_schema_id', $masteryType['rank_schema_id'])
            ->where('rank_id', $nextRank['rank_id'])
            ->fetchOne();

        if($nxt == null)
            return $member->error('No rank schema cost entry for this mastery type and the next tier.');

        $nextCost = $nxt['cost'];
        $user = $member->finder('XF:User')->where('user_id', $params['user_id'])->fetchOne();
        $userVal = $rankSchema->Currency->getValueFromUser($user, false);

        return [
            'user' => $user,
            'thisRank' => $thisRank,
            'nextRank' => $nextRank,
            'rankSchema' => $rankSchema,
            'mastery' => $mastery,
            'userVal' => $userVal,
            'nextCost' => $nextCost,
        ];
    }

    public static function validateEquipmentUpgrade($member, $params)
    {
        $user_id = $member->filter('user_id', 'uint');
        $equipId = $member->filter('equip_id', 'uint');
        $equipGroup = $member->filter('equipment_group', 'str');

        // Permission check
        if(!$member->canVisitorEditCharacterSheet($user_id))
            return $member->error("You lack permission to edit this character.");

        $user = $member->getUser($user_id);

        // Determine which filter is available and fetch the appropriate equipment
        if ($equipId) {
            $charEquip = CharacterEquipment::getEquipment($member, $user_id, $equipId);
        } elseif ($equipGroup) {
            $charEquip = CharacterEquipment::getEquipmentByGroup($member, $user_id, $equipGroup);
        } else {
            return $member->error('Either equip_id or equipment_group must be provided.');
        }

        $equipment = $charEquip->Equipment;

        // Get the next-highest rank.
        $nextRank = $charEquip['Rank']->getNextRank();

        // Next-highest rank check.
        if($nextRank == null)
            return $member->error('Already at max rank.');

        // Get rank schema (and the currency associated with it).
        $rankSchema = $equipment->RankSchema;

        // Rank schema exists check.
        if($rankSchema == null)
            return $member->error('No rank schema set for equipment.');

        // Temporary variable to get the cost of the next rank.
        $nxt = $member->finder('Terrasphere\Core:RankSchemaMap')
            ->where([
                ['rank_schema_id',$rankSchema->rank_schema_id],
                ['rank_id',$nextRank->rank_id]
            ])
            ->fetchOne();

        if($nxt == null)
            return $member->error('Next tier of upgrade has no cost associated in ranking schema.');

        $nextCost = $nxt->cost;
        $userVal = $rankSchema['Currency']->getValueFromUser($user, false);

        if($nextCost > $userVal)
            return $member->error('Error: Insufficient Funds.');

        return [
            'user' => $user,
            'equipment' => $equipment,
            'nextCost' => $nextCost,
            'userVal' => $userVal,
            'nextRank' => $nextRank,
            'rankSchema' => $rankSchema,
            'charEquip' => $charEquip,
        ];
    }

    public static function validateExpertiseUpgrade($member, $params)
    {
        // Get character's mastery instance.
        $expertise = $member->finder('Terrasphere\Charactermanager:CharacterExpertise')
            ->with('Expertise')
            ->where('user_id', $params['user_id'])
            ->where('target_index', $params['target_index'])
            ->fetchOne();

        // Get the current rank.
        $thisRank = $member->finder('Terrasphere\Core:Rank')
            ->where('rank_id', $expertise['rank_id'])
            ->fetchOne();

        // Rank exists check.
        if($thisRank == null)
            return $member->error('NullRankError.');

        // Get the mastery type for the ranking schema.
        $rankSchemaID = $member->options()->terrasphereExpertiseRankSchema;

        // Get the next-highest rank.
        $nextRank = $member->finder('Terrasphere\Core:Rank')
            ->where('tier', $thisRank['tier']+1)
            ->fetchOne();

        // Next-highest rank check.
        if($nextRank == null)
            return $member->error('Already at max rank.');

        // Get rank schema (and the currency associated with it).
        $rankSchema = $member->finder('Terrasphere\Core:RankSchema')
            ->with('Currency')
            ->where('rank_schema_id', $rankSchemaID)
            ->fetchOne();

        // Rank schema exists check.
        if($rankSchema == null)
            return $member->error('No rank schema associated with this mastery type.');

        // Temporary variable to get the cost of the next rank.
        $nxt = $member->finder('Terrasphere\Core:RankSchemaMap')
            ->where('rank_schema_id', $rankSchemaID)
            ->where('rank_id', $nextRank['rank_id'])
            ->fetchOne();

        if($nxt == null)
            return $member->error('No rank schema cost entry for this mastery type and the next tier.');

        $nextCost = $nxt['cost'];
        $user = $member->finder('XF:User')->where('user_id', $params['user_id'])->fetchOne();
        $userVal = $rankSchema->Currency->getValueFromUser($user, false);

        return [
            'user' => $user,
            'thisRank' => $thisRank,
            'nextRank' => $nextRank,
            'rankSchema' => $rankSchema,
            'expertise' => $expertise,
            'userVal' => $userVal,
            'nextCost' => $nextCost,
        ];
    }

    public static function adjustCurrency($finderContainer, User $user, int $cost, string $message, $currencyID)
    {
        /** @var Currency $currency */
        $currency = $finderContainer->assertRecordExists('DBTech\Credits:Currency', $currencyID, null, null);

        $input = [
            'username' => $user['username'],
            'amount' => $cost,
            'message' => $message,
            'negate' => true
        ];

        /** @var \DBTech\Credits\EventTrigger\Adjust $adjustEvent */
        $adjustEvent = $finderContainer->repository('DBTech\Credits:EventTrigger')->getHandler('adjust');
        if (!$adjustEvent->isActive())
        {
            return $finderContainer->error(\XF::phrase('dbtech_credits_invalid_eventtrigger'));
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
}