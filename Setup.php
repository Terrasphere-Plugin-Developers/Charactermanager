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
            $table->addColumn('ts_cm_character_sheet_post_id', 'int')->setDefault(-1);
        });
        $this->db();
    }

    public function uninstallStep1()
    {
        // Drops all tables from the addon
        $this->uninstallTables($this->schemaManager());

        $this->schemaManager()->alterTable('xf_user', function(Alter $table)
        {
            $table->forgetColumn('ts_cm_character_sheet_post_id');
        });
    }
}