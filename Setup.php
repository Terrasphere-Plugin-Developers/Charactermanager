<?php

namespace Terrasphere\Charactermanager;

use XF\AddOn\AbstractSetup;
use XF\AddOn\StepRunnerInstallTrait;
use XF\AddOn\StepRunnerUninstallTrait;
use XF\AddOn\StepRunnerUpgradeTrait;

class Setup extends AbstractSetup
{
	use StepRunnerInstallTrait;
	use StepRunnerUpgradeTrait;
	use StepRunnerUninstallTrait;
	use XF\Db\Schema\Alter;
	use XF\Db\Schema\Create;

	public function installStep1()
	{
		$this->schemaManager()->createTable('xf_terrasphere_core_rank', function(Create $table)
    	{
        	$table->addColumn('rank_id', 'int');
        	$table->addColumn('name', 'varchar');
        	$table->addColumn('price', 'int');
        	$table->addColumn('next_rank_id', 'int');
        	$table->addPrimaryKey('rank_id');
    	});
		
		$this->schemaManager()->createTable('xf_terrasphere_core_mastery_save', function(Create $table)
    	{
        	$table->addColumn('save_id', 'int');
        	$table->addColumn('name', 'varchar');
        	$table->addPrimaryKey('save_id');
    	});
		
		$this->schemaManager()->createTable('xf_terrasphere_core_mastery_role', function(Create $table)
    	{
        	$table->addColumn('role_id', 'int');
        	$table->addColumn('name', 'varchar');
        	$table->addPrimaryKey('role_id');
    	});
		
		$this->schemaManager()->createTable('xf_terrasphere_core_mastery_expertise', function(Create $table)
    	{
        	$table->addColumn('expertise_id', 'int');
        	$table->addColumn('name', 'varchar');
        	$table->addPrimaryKey('expertise_id');
    	});
		
		$this->schemaManager()->createTable('xf_terrasphere_core_mastery_type', function(Create $table)
    	{
        	$table->addColumn('type_id', 'int');
        	$table->addColumn('name', 'varchar');
        	$table->addColumn('max', 'int');
        	$table->addPrimaryKey('type_id');
    	});

		$this->schemaManager()->createTable('xf_terrasphere_core_character_masteries', function(Create $table)
    	{
        	$table->addColumn('type_id', 'int');
        	$table->addColumn('name', 'varchar');
        	$table->addColumn('max', 'int');
        	$table->addPrimaryKey('mastery_id');
        	$table->addPrimaryKey('character_id');
    	});

	}
}