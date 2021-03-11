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
        $this->db();
    }

    public function uninstallStep1()
    {
        // Drops all tables from the addon
        $this->uninstallTables($this->schemaManager());
    }
}