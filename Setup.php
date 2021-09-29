<?php

namespace Terrasphere\Charactermanager;

use FG\ASN1\Universal\Integer;
use XF\AddOn\AbstractSetup;
use XF\AddOn\StepRunnerInstallTrait;
use XF\AddOn\StepRunnerUninstallTrait;
use XF\AddOn\StepRunnerUpgradeTrait;

use XF\Db\Schema\Alter;
use XF\Db\Schema\Create;
use Terrasphere\Charactermanager\DBTableInit;

class Setup extends AbstractSetup
{
    use StepRunnerInstallTrait;
    use StepRunnerUpgradeTrait;
    use StepRunnerUninstallTrait;
    use DBTableInit;

    public function installStep1()
    {
        // Initializes all tables for the addon
        $this->installTables($this->schemaManager());
        $this->schemaManager()->alterTable('xf_user', function(Alter $table)
        {
            $table->addColumn('ts_cm_character_sheet_post_id', 'int')->unsigned(false)->setDefault(-1);
        });
        $this->db();
    }

    public function uninstallStep1()
    {
        // Drops all tables from the addon
        $this->uninstallTables($this->schemaManager());

        $this->schemaManager()->alterTable('xf_user', function(Alter $table)
        {
            $table->dropColumns('ts_cm_character_sheet_post_id');
        });
    }

    ### UPDATE STUFF  VERSION 1.0.1###
    public function upgrade1000100Step1()
    {
        $this->schemaManager()->dropTable("xf_terrasphere_cm_equipment");
        $this->characterEquipmentTable($this->schemaManager());
        $this->schemaManager()->alterTable('xf_user', function(Alter $table)
        {
            $table->dropColumns('ts_cm_weapon_rank_id');
            $table->dropColumns('ts_cm_armor_light_rank_id');
            $table->dropColumns('ts_cm_armor_med_rank_id');
            $table->dropColumns('ts_cm_armor_heavy_rank_id');
        });
    }


    ### UPDATE STUFF  VERSION 1.0.6###
    public function upgrade1000600Step1()
    {
        $this->raceTraitTable($this->schemaManager());
        $this->raceTraitGroupAccessTable($this->schemaManager());
        $this->characterRaceTraitTable($this->schemaManager());
    }
}