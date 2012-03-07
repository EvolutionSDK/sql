<?php

namespace bundles\SQL;
use Exception;
use e;

class Model {

	/**
	 * Database and Table Store
	 */
	private $_connection;
	private $_table;
	private $_bundle;
	private $_name;

	/**
	 * Cache and used memory
	 */
	private static $_memory;
	private static $_this_memory;
	private static $_cache = array();

	/**
	 * Stored Data
	 */
	private $_data;

	/**
	 * Extension Handler
	 */
	private $_extensionHandler;

	/**
	 * Has the model bee modified
	 */
	private $_modified = false;
	
	/**
	 * Get table
	 */
	public function __getTable() {
		return $this->_table;
	}

	/**
	 * Get model name
	 */
	public function __getName() {
		return $this->_name;
	}

	/**
	 * Get bundle name
	 */
	public function __getBundle() {
		return $this->_bundle;
	}
	
	/**
	 * Get a unique reference
	 */
	public function __map($return = 'map') {
		if(empty($this->id))
			throw new Exception("Cannot use `__map` on an unsaved model");
		
		switch($return) {
			case 'bundle':
				return $this->_bundle;
			break;
			case 'name':
				return $this->_name;
			break;
			case 'bundlename':
				return $this->_bundle . '.' . $this->_name;
			break;
			case 'map':
			default:
				return $this->_bundle . '.' . $this->_name . ':' . $this->id;
			break;
		}
	}
	
	/**
	 * Get HTML Link
	 */
	public function __getHTMLLink() {
		$ex = explode('.', $this->_table);
		return '<a href="/test/nate/'.array_pop($ex).'/'.$this->id.'">'.$this->title.'</a>';
	}
	
	/**
	 * Feed entry
	 */
	public function feedEntry($name, &$vars, &$scope) {
		
		// Loop through each var
		foreach($vars as $original => $value) {
			
			// Get various attributes
			$filters = explode('|', $original);
			$var = array_shift($filters);
			$properties = explode('.', $var);
			$var = array_shift($properties);
			
			// Check for var in scope
			if(!isset($scope[$var]))
				continue;
			
			// Get property or format model
			$current = $scope[$var];
			
			// Dive into scope
			foreach($properties as $prop) {
				$current = $current->$prop;
			}
			
			// Get model links
			if($current instanceof Model)
				$current = $current->__getHTMLLink();
			
			// Process filters
			foreach($filters as $filter) {
				switch($filter) {
					case 'currency':
						$current = '$' . number_format($current, 2);
				}
			}
			
			// Save the variable
			$vars[$original] = $current;
		}
		
		// Show this story
		return true;
	}

	/**
	 * Initialize the model
	 *
	 * @param string $dbh
	 * @param string $name - Model Name (i.e. member)
	 * @param string $table 
	 * @param string $id  
	 * @author Kelly Lauren Summer Becker
	 */
	public function __construct($connection, $name, $table, $id = false) {
		/**
		 * Get Initial Memory Usage
		 */
		$init_mem = memory_get_usage(true);
		
		$dbh = e::sql($connection);

		/**
		 * Set default db/table in a var
		 */
		$this->_connection = $dbh;
		$this->_table = $table;
		$ex = explode('.', $table);
		$this->_bundle = $ex[0];
		$this->_name = $name;
		
		/**
		 * If an ID is provided load the row, and store it to the cache
		 */
		if($id) {
			if(!is_array($id) && isset(self::$_cache[$table][$id])) {
				$this->_data =& self::$_cache[$table][$id];
			}
			
			else if(is_numeric($id)) {
				self::$_cache[$table][$id] = $this->_connection->select_by_id($table, $id)->row();
				$this->_data =& self::$_cache[$table][$id];
			}
		
			else if(is_array($id)) {
				self::$_cache[$table][$id['id']] = $id;
				$this->_data =& self::$_cache[$table][$id['id']];
			}
			else if(is_string($id) && preg_match("/^[A-Za-z-]+$/", $id)) {
				if(isset(\Bundles\SQL\Bundle::$db_structure[$this->_table]['fields']['slug'])) {
					// check if the slug field is available
					self::$_cache[$table][$id] = $this->_connection->select($table, "WHERE `slug` = '$id'")->row();
					$this->data =& self::$_cache[$table][$id];
				} else {
					throw new Exception("Trying to load a row from the table [$this->_table] with slug[$id], but there is no slug column on this table.");
				}
			}
			
			/**
			 * If no data was loaded assume that false was passed
			 */
			if($this->_data == false) $this->_data = $this->_connection->get_fields($table, true);
		}

		/**
		 * If no ID is provided then load the fields
		 */
		else $this->_data = $this->_connection->get_fields($table, true);

		/**
		 * Recalcuate the used memory and store it
		 */
		self::$_this_memory = (memory_get_usage(true) - $init_mem);
		self::$_memory += self::$_this_memory;

		if(method_exists($this, '__init')) {
			$this->__init();
		}

		//if($name == 'account') dump($this->_data);
	}

	/**
	 * If the model was modified then save it in the destruct
	 *
	 * @return void
	 * @author Kelly Lauren Summer Becker
	 */
	public function __destruct() {
		if($this->_modified === true) $this->save();
	}

	/**
	 * Return isset() on the object var
	 *
	 * @param string $field 
	 * @return void
	 * @author Kelly Lauren Summer Becker
	 */
	public function __isset($field) {

		/**
		 * Extension handler
		 * @author Nate Ferrero
		 */
		if($field === '_') {
			return true;
		}

		/**
		 * Check if the field is set
		 */
		if(isset($this->_data[$field]))
			return true;
		
		if($field == 'updated_timestamp') throw new Exception('wHTA');

		/**
		 * Allow Overriding the Magic Method in the child class
		 */
		if(method_exists($this, '__issetExtend')) {
			try { return $this->__issetExtend($field); }
			catch(Exception $e) { }
		}
		
		return false;
	}

	/**
	 * Return the $this->_data value for $field
	 *
	 * @param string $field 
	 * @return void
	 * @author Kelly Lauren Summer Becker
	 */
	public function __get($field) {

		/**
		 * Extension handler
		 * @author Nate Ferrero
		 */
		if($field === '_') {
			if(!isset($this->_extensionHandler))
				$this->_extensionHandler = new ModelExtensionHandler($this);
			return $this->_extensionHandler;
		}
		
		/**
		 * Local data first
		 */
		if(isset($this->_data[$field]))
			return $this->_data[$field];

		/**
		 * Allow Overriding the Magic Method in the child class
		 */
		if(method_exists($this, '__getExtend')) {
			try { return $this->__getExtend($field); }
			catch(Exception $e) { }
		}

		return null;
	}

	/**
	 * Set a new $this->_data value for $field
	 *
	 * @param string $field 
	 * @param string $nval 
	 * @return void
	 * @author Kelly Lauren Summer Becker
	 */
	public function __set($field, $nval) {
		/**
		 * Allow Overriding the Magic Method in the child class
		 */
		if(method_exists($this, '__setExtend')) {
			try { return $this->__setExtend($field, $nval); }
			catch(Exception $e) { }
		}
		
		if(!array_key_exists($field, $this->_data)) return null;
		if($field == 'id') return false;

		$init_mem = memory_get_usage(true);
		$this->_modified[$field] = TRUE;

		$this->_data[$field] = $nval;

		self::$_memory += (memory_get_usage(true) - $init_mem);
	}

	/**
	 * If no method is called return the DB Model info
	 *
	 * @return void
	 * @author Kelly Lauren Summer Becker
	 */
	public function info() {
		return "DB Model: #[$this->id] in table $this->_table. Is using $this->_memory bytes of memory.";
	}

	/**
	 * DB Model String
	 *
	 * @return string
	 * @author Nate Ferrero
	 */
	public function __toString() {
		return "$this->_table:$this->id";
	}

	/**
	 * Return the $this->_data model as an array
	 *
	 * @return void
	 * @author Kelly Lauren Summer Becker
	 */
	public function get_array() {
		return $this->_data;
	}
	public function getArray() {
		return $this->get_array();
	}

	/**
	 * Save the $this->_data into the table as a new row or update
	 *
	 * @param string $data 
	 * @return void
	 * @author Kelly Lauren Summer Becker
	 */
	public function save($data = false) {

		/**
		 * If $data is passed then process the array into the various $this->_data values
		 */
		if(is_array($data)) {
			foreach($data as $key=>$val) {
				if($key == 'id') continue;
				$this->$key = $val;
			}
		}

		/**
		 * If nothing was modified dont spend memory running the query
		 */
		if(!$this->_modified && $data !== true) return false;
		if(!is_null($this->id) && $data === true) return false;

		/**
		 * Process the query save
		 */
		$save = array();
		foreach($this->_data as $key=>$val) {
			if($key == 'id' || !isset($this->_modified[$key])) continue;
			$save[$key] = $val;
		}

		/**
		 * Make the file as modified and then update/insert the values
		 */
		$this->_modified = false;
		if($this->id) $this->_connection->update_by_id($this->_table, $save, $this->id);
		else {
			$save['created_timestamp'] = date("Y-m-d H:i:s");
			$this->_data['id'] = (int) $this->_connection->insert($this->_table, $save)->insertId();
		}
		
		/**
		 * Let everything know afterward
		 */
		e::$events->deferred_register('sql_model_event', 'saved', $this->__map());
	}

	/**
	 * Delete the row from the db (Poof no more model)
	 *
	 * @return void
	 * @author Kelly Lauren Summer Becker
	 */
	public function delete() {
		if(isset($this->id)) {
			
			// Get the map
			$m = $this->__map();
			
			$this->_connection->delete_by_id($this->_table, $this->id);
			unset(self::$_cache[$this->_table][$this->id]);

			/**
			 * Let everything know
			 */
			e::$events->deferred_register('sql_model_event', 'deleted', $m);
		}
	}
	
	public function __call($func, $args) {

		// Original Model
		$originalModel = isset($args[0]) ? $args[0] : null;

		// Convert models to IDs
		if(isset($args[0]) && $args[0] instanceof Model)
			$args[0] = $args[0]->id;

		$func = strtolower($func);
		$methods = array('get', 'link', 'unlink', 'haslink');
		foreach($methods as $m) if($m == substr($func, 0, strlen($m))) {
			$search = substr($func, strlen($m));
			$method = $m;
			if(strtolower($search === 'generic')) {
				if(strpos($originalModel, ':') !== false) {
					$originalModel = e::map($originalModel);
					$args[0] = $originalModel->id;
				}

				if(!($originalModel instanceof Model))
					throw new Exception('Cannot use link/unlinkGeneric without passing an instance of `Bundles\\SQL\\Model`');
				else
					$search = strtolower($originalModel->__getBundle() . $originalModel->__getName());
			}
		}

		/**
		 * Grab the data for the active table
		 */
		$relations = Bundle::$db_structure[$this->_table];

		/**
		 * Remove unneeded relationship information
		 */
		unset($relations['singular'], $relations['plural'], $relations['fields']);
		
		/**
		 * Made the arrays easy to access
		 */
		extract($relations);
		
		/**
		 * Get rid of the whole table array
		 */
		unset($relations);
		
		/**
		 * Create an array of tables it can be
		 */
		$possible_tables = array();
		$relation_tables = array();
		
		if(isset($hasMany)) foreach($hasMany as $table) {
			$possible_tables[] = $table;
			$relation_tables['y'][] = $table;
		}
		
		if(isset($hasOne)) foreach($hasOne as $table) {
			$possible_tables[] = $table;
			$relation_tables['o'][] = $table;
		}

		if(isset($manyToMany)) foreach($manyToMany as $table) {
			$possible_tables[] = $table;
			$relation_tables['x'][] = $table;
		}
		
		/**
		 * Find the results being called
		 */
		foreach($possible_tables as $table) {
			$plural_w = strtolower(Bundle::$db_structure[$table]['plural']);
			$singular_w = strtolower(Bundle::$db_structure[$table]['singular']);

			if((strrpos($search, $plural_w) === false) && (strrpos($search, $singular_w) === false)) continue;

			if(($tmp = strrpos($search, $plural_w)) !== false && (!isset($found) || !$found)) {
				$bundle = $tmp === 0 ? $this->_bundle : substr($search, 0, -strlen(substr($search, $tmp)));
				$model = substr($search, $tmp === 0 ? 0 : strlen($bundle));
				$plural = true;
				$found = true;

				if($bundle !== array_shift(explode('.', $table)) || $model !== $plural_w)
					{ unset($plural, $bundle, $model); $found = false; }
			}

			if(($tmp = strrpos($search, $singular_w)) !== false && (!isset($found) || !$found)) {
				$bundle = $tmp === 0 ? $this->_bundle : substr($search, 0, -strlen(substr($search, $tmp)));
				$model = substr($search, $tmp === 0 ? 0 : strlen($bundle));
				$plural = false;
				$found = true;



				if($bundle !== array_shift(explode('.', $table)) || $model !== $singular_w)
					{ unset($plural, $bundle, $model); $found = false; }
			}

			if(!isset($found)) $found = false;

			//var_dump(array($search, $bundle,$found));
						
			/**
			 * Now that we found our culprit lets mark the table matched and blow this popsicle stand
			 */
			if($found) {
				$matched = $table;
				unset($possible_tables, $found, $table, $relations);
				break;
			}
		}
				
		/**
		 * If no results stop everything
		 */
		if(isset($found) && $found == false) throw new NoMatchException("`$search` could not be be mapped to a table when calling `$func(...)` on the `$this->_table` model.");
		
		switch($method) {
			case 'get':
				if(isset($relation_tables['y']) && in_array($matched, $relation_tables['y'])) 
					$conds = array("\$".$this->_table.'_id' => (string) $this->id);
				
				else if(isset($relation_tables['o']) && in_array($matched, $relation_tables['o'])) {
					$var = "\$".$matched.'_id';
					$conds = array('id' => (string) $this->$var);
				}
				
				else if(isset($relation_tables['x']) && in_array($matched, $relation_tables['x'])) {
					$table1 = "\$connect $matched $this->_table";
					$table2 = "\$connect $this->_table $matched";
					$table3 = "\$connect $this->_table";
					
					if($this->_exists($table1)) $use = $table1;
					else if($this->_exists($table2)) $use = $table2;
					else if($this->_exists($table3)) { $use = $table3; $same = true; }

					if($plural) {
						$return = new ListObj($matched, $this->_connection->slug);
						return $return->m2m($use, (isset($same) ? 1 : $this->_table), $this->id);
					}
					
					else if(!$plural) {
						$return = new ListObj($matched, $this->_connection->slug);
						$return = $return->m2m($use, $this->_table, $this->id);
						return $return;
					}
					
				}
				
				if($plural) {
					$return = new ListObj($matched, $this->_connection->slug);
					return $return->condition_array($conds);
				}
				
				else if(!$plural) {
					$row = $this->_connection->select($matched, $conds)->row();
					if(empty($row)) throw new NoMatchException("No results were returned when calling `$func(...)` on the `$this->_table` model.");
					
					list($bundle, $model) = explode('.', $matched);
					$type = Bundle::$db_structure[$matched]['singular'];
					
					if(!isset(e::$$bundle))
						throw new Exception("Bundle `$bundle` is not installed");
					
					return e::$$bundle->{"get".ucfirst($type)}($row);
				}
			break;
			case 'link':
			
				if(!$args[0]) return false;
			
				/**
				 * Let everything know
				 */
				e::$events->deferred_register('sql_model_event', 'linked', $this->__map(), $matched.':'.$args[0]);
				
				if(isset($relation_tables['y']) && in_array($matched, $relation_tables['y'])) {
					if($plural) foreach($args[0] as $id) {
						$update =  array("\$".$this->_table.'_id' => (string) $this->id);
						$where = "WHERE `id` = '".$id."'";
						$this->_connection->update($matched, $update, $where);
					}
					
					else if(!$plural) {
						$update =  array("\$".$this->_table.'_id' => (string) $this->id);
						$where = "WHERE `id` = '".$args[0]."'";
						$this->_connection->update($matched, $update, $where);
					}
					
					$this->save();
					return true;
				}
				
				else if(isset($relation_tables['o']) && in_array($matched, $relation_tables['o'])) {
					$update =  array("\$".$matched.'_id' => (string) $args[0]);
					$where = "WHERE `id` = '".$this->id."'";
					$this->_connection->update($this->_table, $update, $where);
					
					$this->save();
					return true;
				}
				
				else if(isset($relation_tables['x']) && in_array($matched, $relation_tables['x'])) {
					$table1 = "\$connect $matched $this->_table";
					$table2 = "\$connect $this->_table $matched";
					$table3 = "\$connect $this->_table";
					
					if($this->_exists($table1)) $use = $table1;
					else if($this->_exists($table2)) $use = $table2;
					else if($this->_exists($table3)) { $use = $table3; $same = true; }
					
					if($plural) foreach($args as $id) {
						if(isset($same)) $insert = array(
							"\$id_a" => (string) $this->id,
							"\$id_b" => (string) $id,
						);
						
						else $insert = array(
							"\$".$this->_table.'_id' => (string) $this->id,
							"\$".$matched.'_id' => (string) $id,
							"\$flags" => $args[1]
						);
						
						try { $this->_connection->insert($use, $insert); }
						catch(\PDOException $e) { }
					}
					
					else if(!$plural) {
						if(isset($same)) $insert = array(
							"\$id_a" => (string) $this->id,
							"\$id_b" => (string) $args[0],
						);
						
						else $insert = array(
							"\$".$this->_table.'_id' => (string) $this->id,
							"\$".$matched.'_id' => (string) $args[0],
						);
						
						try { $this->_connection->insert($use, $insert); }
						catch(\PDOException $e) { }
					}
					
					$this->save();
					return true;
				}				
			break;
			case 'unlink':
				/**
				 * Let everything know
				 */
				e::$events->deferred_register('sql_model_event', 'unlinked', $this->__map(), $matched.':'.$args[0]);
			
				if(isset($relation_tables['y']) && in_array($matched, $relation_tables['y'])) {
					if($plural) foreach($args[0] as $id) {
						$update =  array("\$".$this->_table.'_id' => (string) 0);
						$where = "WHERE `id` = '".$id."'";
						$this->_connection->update($matched, $update, $where);
					}
					
					else if(!$plural) {
						$update =  array("\$".$this->_table.'_id' => (string) 0);
						$where = "WHERE `id` = '".$args[0]."'";
						$this->_connection->update($matched, $update, $where);
					}
					
					$this->save();
					return true;
				}
				
				else if(isset($relation_tables['o']) && in_array($matched, $relation_tables['o'])) {
					$update =  array("\$".$matched.'_id' => (string) 0);
					$where = "WHERE `id` = '".$this->id."'";
					$this->_connection->update($this->_table, $update, $where);
					
					$this->save();
					return true;
				}
				
				else if(isset($relation_tables['x']) && in_array($matched, $relation_tables['x'])) {					
					$table1 = "\$connect $matched $this->_table";
					$table2 = "\$connect $this->_table $matched";
					
					if($this->_exists($table1)) $use = $table1;
					else if($this->_exists($table2)) $use = $table2;
					
					foreach($args as $id) {
						$delete = array(
							"\$".$this->_table.'_id' => (string) $this->id,
							"\$".$matched.'_id' => (string) $id,
						);

						$this->_connection->delete($use, $delete);
					}
					
					$this->save();
					return true;
				}				
			break;
			case 'haslink':
				if(isset($relation_tables['y']) && in_array($matched, $relation_tables['y'])) {
					if($plural) foreach($args[0] as $id) {
						$where =  array(
							"\$".$this->_table.'_id' => $this->id,
							"id" => $id
						);
						
						if($this->_connection->select($matched, $where)->row())
							return true;
						else return false;
					}
					
					else if(!$plural) {
						$where = array(
							"\$".$this->_table.'_id' => $this->id,
							"id" => $args[0]
						);

						if($this->_connection->select($matched, $where)->row())
							return true;
						else return false;
					}
				}
				
				else if(isset($relation_tables['o']) && in_array($matched, $relation_tables['o'])) {
					$where = array(
						"\$".$matched.'_id' => $args[0],
						"id" => $this->id
					);

					if($this->_connection->select($this->_table, $where)->row())
						return true;
					else return false;
				}
				
				else if(isset($relation_tables['x']) && in_array($matched, $relation_tables['x'])) {					
					$table1 = "\$connect $matched $this->_table";
					$table2 = "\$connect $this->_table $matched";
					
					if($this->_exists($table1)) $use = $table1;
					else if($this->_exists($table2)) $use = $table2;
					
					foreach($args as $id) {
						$where = array(
							"\$".$this->_table.'_id' => (string) $this->id,
							"\$".$matched.'_id' => (string) $id,
						);

						if($this->_connection->select($use, $where)->row())
							return true;
						else return false;
					}
				}				
			break;
			default:
				/**
				 * HACK
				 * @author Nate Ferrero
				 * This is to prevent weird bugs until we get LHTML scope figured out
				 */
				return $this->$func;
				throw new InvalidRequestException("`$method` is not a valid request as `$func(...)` on the `$this->_table` model. valid requests are `get`, `link`, and `unlink`");
			break;
		}
		
	}
	private function _exists($table = false) {
		$table = $table ? $table : $this->_table;
		if(!$this->_connection->query("SHOW TABLES LIKE '$table'")->row()) return false;
		else return true;
	}

}

/**
 * Model Extension Handler
 * @author Nate Ferrero
 */
class ModelExtensionHandler {

	private $model;
	private $extensions = array();

	public function __construct($model) {
		$this->model = $model;
	}

	public function __isset($extension) {
		return true;
	}

	public function __get($extension) {
		$extension = strtolower($extension);
		if(!isset($this->extensions[$extension]))
			$this->extensions[$extension] = new ModelExtensionAccess($this->model, Bundle::extension($extension));
		return $this->extensions[$extension];
	}

}

/**
 * Model Extension Access
 * @author Nate Ferrero
 */
class ModelExtensionAccess {

	private $model;
	private $extension;

	public function __construct($model, $extension) {
		$this->model = $model;
		$this->extension = $extension;
	}

	public function __set($var, $val) {
		$method = 'modelSet';
		if(method_exists($this->extension, $method))
			return $this->extension->$method($this->model, $var, $val);
	}

	public function __get($var) {
		$method = 'modelGet';
		if(method_exists($this->extension, $method))
			return $this->extension->$method($this->model, $var);
	}

	public function __isset($var) {
		$method = 'modelIsset';
		if(method_exists($this->extension, $method))
			return $this->extension->$method($this->model, $var);
	}

	public function __call($method, $args) {
		$method = "model" . ucfirst($method);
		array_unshift($args, $this->model);
		return call_user_func_array(array($this->extension, $method), $args);
	}

}