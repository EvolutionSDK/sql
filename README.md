# About the SQL Bundle

The sql bundle contains modeling, structure synchronizing, and advanced querying abilities as well as SQL injection protection.



# Stack structure
	* Bundle
	 * Connection
	  * Query
	   * Result
	  * Architecture
	 * Model
	  * List
	

# Usage

	e::sql("QUERY");
	e::sql()->select();
	
# Query Object

	e::sql()
		->select()
		->selectById()
		->update()
		->updateByID()
		->replace()
		->etc()
	
# Architect
	e::sql()->architect('tablename');
		->prepareSync($file)
			->getUpdateSQL()
			->hasChanges()
			->runSync()
		->addField()
		->modifyField()
		->deleteField()
		->addIndex()
		->modifyIndex()
		->deleteIndex()
		->getFields()
		->addTable()
		->dropTable()
		->backupTable()
		->editTable()
		->addRelationship()

# Multiple Connections
	
	e::sql()->addConnection(slug, array)
	e::sql()->useConnection(slug)->queryobject
	
