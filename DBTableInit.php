<?php

namespace Terrasphere\Charactermanager;

use XF\Db\Schema\Create;
use XF\Db\SchemaManager;

trait DBTableInit
{
    protected function installTables(SchemaManager $sm)
    {
        $this->characterMasteryTable($sm);
        $this->characterSheetTable($sm);
        $this->characterSheetCellTable($sm);
        $this->characterSheetContentTable($sm);
        $this->equipmentTable($sm);
        $this->characterEquipmentTable($sm);
        // etc...
    }

    // Drops all tables for the addon
    protected function uninstallTables(SchemaManager $sm)
    {
        $sm->dropTable("xf_terrasphere_cm_character_masteries");
        $sm->dropTable("xf_terrasphere_cm_cs");
        $sm->dropTable("xf_terrasphere_cm_cs_cell");
        $sm->dropTable("xf_terrasphere_cm_cs_content");
        $sm->dropTable("xf_terrasphere_cm_character_equipment");

        // etc...
    }


    /** ----- Table creation methods ----- */
    /** __________________________________ */

    private function characterMasteryTable(SchemaManager $sm)
    {
        $sm->createTable(
            "xf_terrasphere_cm_character_masteries", function(create $table) {
                $table->addColumn("id","int")->primaryKey()->autoIncrement(true);
                $table->addColumn("mastery_id","int");
                $table->addColumn("user_id","int");
                $table->addColumn("rank_id","int")->nullable(true)->setDefault(1);
                $table->addColumn("target_index","int")->setDefault(0);
            }
        );
    }

    private function characterSheetTable(SchemaManager $sm)
    {
        $sm->createTable(
            "xf_terrasphere_cm_cs", function(create $table) {
                $table->addColumn("character_sheet_id","int")->primaryKey()->autoIncrement(true);
                $table->addColumn("user_id","int");
                $table->addColumn("mod_user_id","int")->nullable(true);
                $table->addColumn("revision_number","int");
                $table->addColumn("authentication_time","timestamp");
            }
        );
    }

    private function characterSheetCellTable(SchemaManager $sm)
    {
        $sm->createTable(
            "xf_terrasphere_cm_cs_cell", function(create $table) {
                $table->addColumn("cell_id","int")->primaryKey()->autoIncrement(true);
                $table->addColumn("name","varchar",30)->setDefault('');
            }
        );
    }

    private function characterSheetContentTable(SchemaManager $sm)
    {
        $sm->createTable(
            "xf_terrasphere_cm_cs_content", function(create $table) {
                $table->addColumn("character_sheet_id","int")->primaryKey();
                $table->addColumn("cell_id","int")->primaryKey();
                $table->addColumn("content","text");
            }
        );
    }



    private function equipmentTable(SchemaManager $sm)
    {
        $sm->createTable(
            "xf_terrasphere_cm_equipment", function (create $table) {
            $table->addColumn("equipment_id", "int")->autoIncrement();
            $table->addColumn("user_id", "int");
            $table->addColumn("rank_id", "int");
            $table->addColumn("type", "int");
            $table->addColumn("name", "varchar", 200)->nullable(true);
            $table->addColumn("post_link", "varchar", 999)->nullable(true);
        }
        );
    }

    private function characterEquipmentTable(SchemaManager $sm){
        $sm->createTable(
            "xf_terrasphere_cm_character_equipment", function (create $table) {
            $table->addColumn("user_id", "int");
            $table->addColumn("equipment_id", "int");
            $table->addColumn("rank_id", "int");
            $table->addPrimaryKey(['user_id', 'equipment_id']);
        }
        );
    }
}