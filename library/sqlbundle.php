<?php

namespace Evolution\SQL;
use e;

class SQLBundle {
	
	public $bundle;
	public $database = 'default';
	
	public function __construct($dir) {
		
		$bundle = basename($dir);
		$this->bundle = $bundle;
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
	
	/**
	 * Return Models/List If no Extended model/list was declared
	 *
	 * @param string $func 
	 * @param string $args 
	 * @return void
	 * @author Kelly Lauren Summer Becker
	 */
	public function __call($func, $args) {
		$func = strtolower($func);
		if(substr($func, -5) == '_list') {
			$func = substr($func, 0, -5);
			$return = new ListObj("$this->bundle.$func", $this->database);
			return $return->all();
		}
		
		return new Model($this->database, "$this->bundle.$func", (isset($args[0]) ? $args[0] : false));
	}
	
}