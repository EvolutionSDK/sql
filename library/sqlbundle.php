<?php

namespace Evolution\SQL;
use e;

class SQLBundle {
	
	public $bundle;
	public $database = 'default';
	
	private static $local_structure = array();
	
	public function __construct($dir) {
		$bundle = basename($dir);
		$this->bundle = $bundle;
		$sql = e::spyc()->load($dir.'/configure/_sql_structure.yaml', true);
		
		/**
		 * If a relation is on the same table prefix it with its bundle name
		 */
		foreach($sql as $table=>$relations) {
			if(!is_array($relations)) throw new \Exception("Invalid YAML Config Error-ing in table $table in file $dir/configure/_sql_structure.yaml");
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
		foreach($sql as $table=>$val) self::$local_structure[$table] = $val;
		
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
		$search = preg_split('/([A-Z])/', $func, 2, PREG_SPLIT_DELIM_CAPTURE);
		$method = array_shift($search);
		$search = strtolower(implode('', $search));
		
		if(empty(self::$local_structure)) return false;
		
		foreach(self::$local_structure as $table=>$relations) {
			if(isset($relations['singular']) && $relations['singular'] == $search) {
				$plural = false;
				break;
			}
			else if(isset($relations['plural']) && $relations['plural'] == $search) {
				$plural = true;
				break;
			}
			
			unset($relations, $table);
		}
		
		if(!isset($relations) && !isset($table)) throw new NoMatchException("There was no table match when calling `$func(...)` on the `e::$this->bundle()` bundle.");
		switch($method) {
			case 'get':
				if(!$plural) {
					if(isset($args[0])) {
					$class = "\\Evolution\\$this->bundle\\Models\\$table";
					$class2 = "\\Bundles\\$this->bundle\\Models\\$table";
					try { $m = new $class($this->database, "$this->bundle.$table", $args[0]); }
					catch(\Evolution\Kernel\ClassNotFoundException $e) {
							
							try { $m = new $class2($this->database, "$this->bundle.$table", $args[0]); }
							catch(\Evolution\Kernel\ClassNotFoundException $e) 
								{ $m = new Model($this->database, "$this->bundle.$table", $args[0]); }		
					}
					if(is_object($m) && isset($m->id)) return $m;
					else return false;
						
					} else return false;
				}
				else if($plural) {
					$class = "\\Evolution\\$this->bundle\\Lists\\$table";
					$class2 = "\\Bundles\\$this->bundle\\Lists\\$table";
					try { return new $class("$this->bundle.$table", $this->database); }
					catch(\Evolution\Kernel\ClassNotFoundException $e) {
						
						try { return new $class2("$this->bundle.$table", $this->database); }
						catch(\Evolution\Kernel\ClassNotFoundException $e) 
							{ return new ListObj("$this->bundle.$table", $this->database); }
					}
				}
			break;
			case 'new':
				$class = "\\Evolution\\$this->bundle\\Models\\$table";
				$class2 = "\\Bundles\\$this->bundle\\Models\\$table";
				try { return new $class($this->database, "$this->bundle.$table", false); }
				catch(\Evolution\Kernel\ClassNotFoundException $e) {
					
					try { return new $class2($this->database, "$this->bundle.$table", false); }
					catch(\Evolution\Kernel\ClassNotFoundException $e) 
						{ return new Model($this->database, "$this->bundle.$table", false); }
				}
			default:
				throw new InvalidRequestException("`$method` is not a valid request as `$func(...)` on the `e::$this->bundle()` bundle. valid requests are `new` and `get`.");
			break;
		}
		
		throw new NoMatchException("No method was routed when calling `$func(...)` on the `e::$this->bundle()` bundle.");
		
	}
	
}