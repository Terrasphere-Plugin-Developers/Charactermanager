<?php

namespace Terrasphere\Charactermanager;

use XF\Db\Schema\Create;
use XF\Db\SchemaManager;

trait DBTableInit
{
    protected function installTables(SchemaManager $sm)
    {
        $this->characterMasteryTable($sm);

        // etc...
    }

    // Drops all tables for the addon
    protected function uninstallTables(SchemaManager $sm)
    {
        $sm->dropTable("xf_terrasphere_cm_character_masteries");

        // etc...
    }


    /** ----- Table creation methods ----- */
    /** __________________________________ */

    private function characterMasteryTable(SchemaManager $sm)
    {
        $sm->createTable(
            "xf_terrasphere_cm_character_masteries", function(create $table) {
                $table->addColumn("mastery_id","int")->primaryKey();
                $table->addColumn("character_id","int")->primaryKey();
                $table->addColumn("rank_id","int")->setDefault(1);
                $table->addColumn("target_index","int")->setDefault(0);
            }
        );
    }
}