<?php

namespace Bundles\SQL;
use Exception;
use e;

class callException extends Exception {}

class SQLBundle {
	
	public $bundle;
	protected $dir;
	public $database = 'default';
	protected $initialized = false;
	private $_changed = false;
	
	protected $local_structure = array();
	protected $local_structure_clean = array();
	
	public function __construct($dir) {
		$this->dir = $dir;
		
		$this->bundle = basename($this->dir);
	}
	
	public function _on_sql_init() {
		$enabled = e::$environment->requireVar('SQL.Enabled', "yes | no");
		if($enabled === true || $enabled === 'yes')
			$this->_sql_initialize();
	}
	
	public function _sql_initialize() {
		$bundle = $this->bundle;
		$this->initialized = true;
		$dirs = e\extend($this->bundle);
		array_walk($dirs, function(&$v) use ($bundle) {
			$v = $v.'/'.$bundle;
		});
		array_unshift($dirs, $this->dir);
		$file = $this->dir.'/configure/sql_structure.yaml';

		/**
		 * Require the initial SQL Structure file
		 */
		if(!is_file($file))
			throw new Exception("Please create SQL Configuration `$file` for the `$this->bundle` bundle.");

		/**
		 * Loop through all the extensions and figure out which files need loading
		 */
		$load = array();
		foreach($dirs as $dir) {
			
			/**
			 * If the file does not exist just skip it
			 */
			if(!is_file($file = $dir.'/configure/sql_structure.yaml'))
				continue;

			/**
			 * Check this specific file for changes
			 */
			if(e::$yaml->is_changed($file)) {
				Bundle::$changed = true;
				$this->_changed = true;
			}

			/**
			 * Set to the merge array
			 */
			$load[] = $file;
		}

		/**
		 * Load and merge all the yaml files
		 */
		$sql = e::$yaml->merge($load, true);

		/**
		 * If a relation is on the same table prefix it with its bundle name
		 */
		foreach($sql as $table=>$relations) {
			if(isset($relations['extensions'])) foreach($relations['extensions'] as $extension) {
				$extension = Bundle::extension($extension);
				if(method_exists($extension, '_tableStructure'))
					$extension->_tableStructure($this->bundle.'.'.$table, $relations);
			}

			if(!is_array($relations)) throw new Exception("Invalid YAML configuration in table `$table` in file `$file`");
			foreach($relations as $kind => $values) {
				if($kind == 'fields' || $kind == 'singular' || $kind == 'plural' || $kind == 'extensions') continue;
				if(!is_array($values))
					throw new Exception("Invalid configuration `$kind: $values` in table `$table` in file `$file`");
				foreach($values as $key=>$val) {

					/**
					 * Check for array connection definitions
					 * @author Nate Ferrero
					 */
					$flags = null;
					if(is_array($val)) {
						if(empty($val['model']))
							throw new Exception("Array connection without `model` specified in table `$table` in file `$file`");

						/**
						 * Get connection flags
						 * @author Nate Ferrero
						 */
						if(!empty($val['flags']))
							$flags = $val['flags'];

						/**
						 * Restore model
						 */
						$val = $val['model'];
					}

					if(strpos($val, '.') === false)
						$val = $this->bundle.'.'.$val;

					/**
					 * Save connection flags
					 * @author Nate Ferrero
					 */
					if(!is_null($flags)) {
						$a = $this->bundle.'.'.$table;
						$b = $val;
						Bundle::$connection_flags["$a-^-$b"] = array();
						Bundle::$connection_flags["$b-^-$a"] = array();
						Bundle::$connection_flags["$a-v-$b"] = array();
						Bundle::$connection_flags["$b-v-$a"] = array();
						foreach($flags as $fkey => $fvalue) {
							Bundle::$connection_flags["$a-^-$b"][$fkey] = $fvalue;
							Bundle::$connection_flags["$b-^-$a"][$fkey] = $fvalue;
							Bundle::$connection_flags["$a-v-$b"][$fvalue] = $fkey;
							Bundle::$connection_flags["$b-v-$a"][$fvalue] = $fkey;
						}
					}

					$values[$key] = $val;
				}
				
				$relations[$kind] = $values;
			}
			$relations['changed'] = $this->_changed;
			$sql[$table] = $relations;
		}

		/**
		 * Save the DB structure
		 */
		foreach($sql as $table=>$val) Bundle::$db_structure[$this->bundle.'.'.$table] = $val;
		foreach($sql as $table=>$val) $this->local_structure[$table] = $val;
		$this->__clean_structure();
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
		e::$sql->null();


		/**
		 * Allow Overriding the Call in the child elements
		 */
		if(method_exists($this, '__callExtend')) {
			try { return call_user_func_array(array($this, '__callExtend'), func_get_args()); }
			catch(callException $e) { }
		}
		
		if(!$this->initialized)
			throw new Exception("SQL for `".__CLASS__."` was not initialized in system startup. Most likely, the environment variable `SQL.Enabled` is off.");

		$func = strtolower($func);
		$methods = array('get', 'new');
		foreach($methods as $m) if($m == substr($func, 0, strlen($m))) {
			$search = substr($func, strlen($m));
			$method = $m;
		}
		
		if(empty($this->local_structure_clean)) return false;
		
		foreach($this->local_structure_clean as $table=>$relations) {
			if($search == $table && (!isset($relations['plural']) || $relations['plural'] != $search)) {
				$plural = false;
				break;
			}
			else if(isset($relations['singular']) && $relations['singular'] == $search) {
				$plural = false;
				break;
			}
			else if(isset($relations['plural']) && $relations['plural'] == $search) {
				$plural = true;
				break;
			}
			
			unset($relations, $table);
		}
		
		if(!isset($relations) && !isset($table)) throw new NoMatchException("There was no table match when calling `$func(...)` on the `e::$$this->bundle` bundle.");
		switch($method) {
			case 'get':
				if(!$plural) {
					if(isset($args[0])) {
						$class = "\\Bundles\\$this->bundle\\Models\\$table";
						try { $m = new $class($this->database, $relations['singular'], "$this->bundle.$table", $args[0]); }
						catch(e\AutoLoadException $e) {
							$m = new Model($this->database, $relations['singular'], "$this->bundle.$table", $args[0]);
						}
					}
					if(isset($m) && is_object($m) && isset($m->id)) return $m;
					else return false;
				}
				else if($plural) {
					$class = "\\bundles\\$this->bundle\\Lists\\$table";
					try { return new $class("$this->bundle.$table", $this->database); }
					catch(e\AutoLoadException $e) {
						 return new ListObj("$this->bundle.$table", $this->database);
					}
				}
			break;
			case 'new':
				$class = "\\bundles\\$this->bundle\\Models\\$table";
				try { return new $class($this->database, $relations['singular'], "$this->bundle.$table", false); }
				catch(e\AutoLoadException $e) {
					return new Model($this->database, $relations['singular'], "$this->bundle.$table", false);
				}
			default:
				throw new InvalidRequestException("`$method` is not a valid request as `$func(...)` on the `e::$$this->bundle` bundle. valid requests are `new` and `get`.");
			break;
		}
		
		throw new NoMatchException("No method was routed when calling `$func(...)` on the `e::$$this->bundle` bundle.");
		
	}

	public function __slugSupport($func, $args, $slug = 'slug') {
		$func = strtolower($func);
		if(!is_numeric($args[0]) && is_string($args[0]) && substr($func, 0, 3) == 'get') {
			
			$which = substr($func, 3);

			foreach($this->local_structure_clean as $table => $stuff) {
				unset($poss);
				if($stuff['singular'] == $which) { $which = $table; break; }
				else if($which == $table) $poss = $table;
			}

			if(isset($poss)) $which = $poss;

			if(!isset($this->local_structure_clean[$which]['fields'][$slug]))
				throw new callException;

			$id = e::$sql->query("Select * From `$this->bundle.$which` Where `$slug` = '$args[0]'")->row();
			$id = $id['id'];

			return call_user_func_array(array($this, $func), array($id));
		}

		throw new callException;
	}

	/**
	 * Return structure without indexes
	 * @author Kelly Becker
	 */
	private function __clean_structure() {
		if(!empty($this->local_structure_clean))
			return $this->local_structure_clean;

		$array = $this->local_structure;
		foreach($array as $table => &$opts) {

			$fields = array();
			foreach($opts['fields'] as $field => $type) {
				if($field[0] === '+') $field = substr($field, 1);

				$fields[$field] = $type;
			}

			$opts['fields'] = $fields;
		}

		return $this->local_structure_clean = $array;
	}
	
}