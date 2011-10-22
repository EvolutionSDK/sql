<?php

namespace Evolution\SQL;
use e;

class SQLBundle {
	
	public function __construct($dir) {
		
		$bundle = basename($dir);
		$sql = e::spyc()->load($dir.'/configure/_sql_structure.yaml', true);
		
		/**
		 * If a relation is on the same table prefix it with its bundle name
		 */
		foreach($sql as $table=>$relations) {
			foreach($relations as $kind=>$values) {
				if($kind == 'fields' || $kind == 'singular' || $kind == 'plural') continue;
				
				foreach($values as $key=>$val) {
					if(strpos($val, '.')) continue;
					
					$values[$key] = $bundle.'.'.$val;
				}
				
				$relations[$kind] = $values;
			}
			$sql[$table] = $relations;
		}
				
		/**
		 * Save the DB structure
		 */
		foreach($sql as $table=>$val) Bundle::$db_structure[$bundle.'.'.$table] = $val;
		
	}
	
}