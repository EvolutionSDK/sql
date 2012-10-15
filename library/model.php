<?php

namespace Bundles\SQL;
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
	private $_virtual = false;
	private $_flagConnection = null;
	private $_flags = array();

	/**
	 * Cache and used memory
	 */
	private static $_memory;
	private static $_this_memory;
	private static $_cache = array();

	/**
	 * Stored Data
	 */
	protected $_data;

	/**
	 * Extension Handler
	 */
	private $_extensionHandler;

	/**
	 * Has the model been modified
	 */
	private $_modified = false;

	/**
	 * Make virtual
	 * @author Nate Ferrero
	 */
	public function __makeVirtual() {
		$this->_virtual = true;
	}

	/**
	 * Check if virtual
	 * @author Nate Ferrero
	 */
	public function __isVirtual() {
		return $this->_virtual;
	}
	
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
	 * Get flags
	 */
	public function __getFlags() {
		return $this->_flags;
	}

	/**
	 * Get flags
	 */
	public function __setFlags($connection, $flags) {
		$this->_flagConnection = $connection;
		$flags = (int) $flags;
		if(isset(Bundle::$connection_flags[$connection])) {
			foreach(Bundle::$connection_flags[$connection] as $flag => $label)
				$this->_flags[$label] = (pow(2, $flag) & $flags) > 0 ? true : false;
		}
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
			case 'url':
				return $this->_bundle . '/' . $this->_name;
			break;
			case 'url-id':
				return $this->_bundle . '/' . $this->_name . '/' . $this->id;
			break;
			case 'map':
			default:
				return $this->_bundle . '.' . $this->_name . ':' . $this->id;
			break;
		}
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
			if(!is_array($id) && isset(self::$_cache[$table][$id]))
				$this->_data =& self::$_cache[$table][$id];
			
			else if(is_numeric($id)) {
				self::$_cache[$table][$id] = $this->_connection->select_by_id($table, $id)->row();
				$this->_data =& self::$_cache[$table][$id];
			}
		
			else if(is_array($id)) {
				self::$_cache[$table][$id['id']] = $id;
				$this->_data =& self::$_cache[$table][$id['id']];
			}
			
			/**
			 * If no data was loaded assume that false was passed
			 */
			if(empty($this->_data)) $this->_data = $this->_connection->get_fields($table, true);
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
		if(array_key_exists($field, $this->_data))
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
		if(array_key_exists($field, $this->_data))
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
	public function __toArray() {
		$data = $this->_data;
		$data['_flags'] = $this->_flags;
		return $data;
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
		 * Disable save if virtual
		 * @author Nate Ferrero
		 */
		if($this->_virtual)
			return;

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
			if($this->_data['id'] === 0)
				throw new Exception("Model insert failed on `$this->_table`: ");
		}

		/**
		 * Modify the updated timestamp from mysql
		 * @author Nate Ferrero
		 */
		$row = $this->_connection->select_by_id($this->_table, $this->id)->row();
		if(isset($row['updated_timestamp']))
			$this->updated_timestamp = $row['updated_timestamp'];
		
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

		/**
		 * Disable delete if virtual
		 * @author Nate Ferrero
		 */
		if($this->_virtual)
			return;
		
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
		$methods = array('get', 'link', 'unlink', 'haslink', 'is');
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
		 * If a flag is... return true/false
		 * @author Kelly Becker
		 */
		if($method === 'is') {
			return $this->_flags[strtolower($search)];
		}

		/**
		 * Grab the data for the active table
		 */
		$relations = Bundle::$db_structure_clean[$this->_table];

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
			$plural_w = strtolower(Bundle::$db_structure_clean[$table]['plural']);
			$singular_w = strtolower(Bundle::$db_structure_clean[$table]['singular']);

			if((strrpos($search, $plural_w) === false) && (strrpos($search, $singular_w) === false)) continue;

			if(($tmp = strrpos($search, $plural_w)) !== false && (!isset($found) || !$found)) {
				$bundle = $tmp === 0 ? $this->_bundle : substr($search, 0, -strlen(substr($search, $tmp)));
				$model = substr($search, $tmp === 0 ? 0 : strlen($bundle));
				$plural = true;
				$found = true;

				$spec = explode('.', $table);
				if($bundle !== array_shift($spec) || $model !== $plural_w)
					{ unset($plural, $bundle, $model); $found = false; }
			}

			if(($tmp = strrpos($search, $singular_w)) !== false && (!isset($found) || !$found)) {
				$bundle = $tmp === 0 ? $this->_bundle : substr($search, 0, -strlen(substr($search, $tmp)));
				$model = substr($search, $tmp === 0 ? 0 : strlen($bundle));
				$plural = false;
				$found = true;

				$xtable = explode('.', $table);
				if($bundle !== array_shift($xtable) || $model !== $singular_w) {
					unset($plural, $bundle, $model);
					$found = false;
				}
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
		 * Ensure there was a match
		 * @author Nate Ferrero
		 */
		if(empty($matched))
			$found = false;
				
		/**
		 * If no results stop everything
		 */
		if(isset($found) && $found == false) throw new NoMatchException("`$search` could not be be mapped to a table when calling `$func(...)` on the `$this->_table` model.");
		
		/**
		 * Handle flags on connections
		 * @author Nate Ferrero
		 */
		$flags = 0;
		$last = end($args);
		if(is_string($last) && !is_numeric($last)) {
			$tmp = explode(' ', array_pop($args));
			$fconn = $this->_table.'-v-'.$matched;
			if(!isset(Bundle::$connection_flags[$fconn]))
				throw new Exception("Trying to use connection flags `$last` on the `$this->_table` &harr; `$matched`
					connection when no flags are specified.");
			foreach($tmp as $flag) {
				if(!isset(Bundle::$connection_flags[$fconn][$flag]))
					throw new Exception("Trying to use an undefined connection flag `$flag` on the `$this->_table` &harr; `$matched`
					connection.");
				$flags |= pow(2, Bundle::$connection_flags[$fconn][$flag]);
			}
		}

		switch($method) {
			case 'get':
				if(isset($relation_tables['y']) && in_array($matched, $relation_tables['y'])) {

					/**
					 * Ensure flags only on many to many
					 * @author Nate Ferrero
					 */
					if($flags)
						throw new Exception("Using flags on the `$this->_table` &harr; `$matched` connection that is not many-to-many.");

					$conds = array('$'.$this->_table.'_id' => (string) $this->id);
				}

				else if(isset($relation_tables['o']) && in_array($matched, $relation_tables['o'])) {

					/**
					 * Ensure flags only on many to many
					 * @author Nate Ferrero
					 */
					if($flags)
						throw new Exception("Using flags on the `$this->_table` &harr; `$matched` connection that is not many-to-many.");

					$var = '$'.$matched.'_id';
					$conds = array('id' => (string) $this->$var);
				}
				
				else if(isset($relation_tables['x']) && in_array($matched, $relation_tables['x'])) {
					$table1 = '$connect '."$matched $this->_table";
					$table2 = '$connect '."$this->_table $matched";
					$table3 = '$connect '."$this->_table";
					
					if($this->_exists($table1)) $use = $table1;
					else if($this->_exists($table2)) $use = $table2;
					else if($this->_exists($table3)) { $use = $table3; $same = true; }



					if($plural) {
						$return = new ListObj($matched, $this->_connection->slug);
						return $return->m2m($use, (isset($same) ? 1 : $this->_table), $this->id, $flags);
					}
					
					else if(!$plural) {
						$return = new ListObj($matched, $this->_connection->slug);
						$return = $return->m2m($use, $this->_table, $this->id, $flags);
						return $return;
					}
					
				}
				
				if($plural) {
					$return = new ListObj($matched, $this->_connection->slug);
					return $return->condition_array($conds);
				}
				
				else if(!$plural) {
					$row = $this->_connection->select($matched, $conds)->row();
					if(empty($row)) return false;;
					
					list($bundle, $model) = explode('.', $matched);
					$type = Bundle::$db_structure_clean[$matched]['singular'];
					
					if(!isset(e::$$bundle))
						throw new Exception("Bundle `$bundle` is not installed");
					
					return e::$$bundle->{"get".ucfirst($type)}($row);
				}
			break;
			case 'link':
			
				if(!$args[0]) return false;

				/**
				 * Let everything know
				 * @todo Account for simultaneous linking of multiple models
				 * @author Nate Ferrero
				 */
				e::$events->deferred_register('sql_model_event', 'linked', $this->__map(), $matched.':'.$args[0]);
				
				if(isset($relation_tables['y']) && in_array($matched, $relation_tables['y'])) {

					/**
					 * Ensure flags only on many to many
					 * @author Nate Ferrero
					 */
					if($flags)
						throw new Exception("Using flags on the `$this->_table` &harr; `$matched` connection that is not many-to-many.");

					if($plural) foreach($args as $id) {
						$update =  array('$'.$this->_table.'_id' => (string) $this->id);
						$where = "WHERE `id` = '".$id."'";
						$this->_connection->update($matched, $update, $where);
					}
					
					else if(!$plural) {
						$update =  array('$'.$this->_table.'_id' => (string) $this->id);
						$where = "WHERE `id` = '".$args[0]."'";
						$this->_connection->update($matched, $update, $where);
					}
					
					$this->save();
					return true;
				}
				
				else if(isset($relation_tables['o']) && in_array($matched, $relation_tables['o'])) {
					
					/**
					 * Ensure flags only on many to many
					 * @author Nate Ferrero
					 */
					if($flags)
						throw new Exception("Using flags on the `$this->_table` &harr; `$matched` connection that is not many-to-many.");

					$update =  array('$'.$matched.'_id' => (string) $args[0]);

					$this->{'$'.$matched.'_id'} = (string) $args[0];
					$where = "WHERE `id` = '".$this->id."'";
					$this->_connection->update($this->_table, $update, $where);
					
					$this->save();
					return true;
				}
				
				else if(isset($relation_tables['x']) && in_array($matched, $relation_tables['x'])) {
					$table1 = '$connect '."$matched $this->_table";
					$table2 = '$connect '."$this->_table $matched";
					$table3 = '$connect '."$this->_table";
					
					if($this->_exists($table1)) $use = $table1;
					else if($this->_exists($table2)) $use = $table2;
					else if($this->_exists($table3)) { $use = $table3; $same = true; }
					
					if($plural) foreach($args as $id) {
						if(isset($same)) $insert = array(
							'$id_a' => (string) $this->id,
							'$id_b' => (string) $id,
							'$flags' => $flags
						);
						
						else $insert = array(
							'$'.$this->_table.'_id' => (string) $this->id,
							'$'.$matched.'_id' => (string) $id,
							'$flags' => $flags
						);

						try { $this->_connection->insert($use, $insert); }
						catch(\PDOException $e) {
							/**
							 * Update Flags
							 * $flags column must be last in the insert!
							 * @author Nate Ferrero
							 */
							$where = '';
							foreach($insert as $key => $value) {
								if($key == '$flags') {
									$insert = array($key => $value);
									break;
								} else
									$where .= ($where == '' ? '' : ' AND ') . "`$key` = $value";
							}
							$this->_connection->update($use, $insert, "WHERE $where");
						}
					}
					
					else if(!$plural) {
						if(isset($same)) $insert = array(
							'$id_a' => (string) $this->id,
							'$id_b' => (string) $args[0],
							'$flags' => $flags
						);
						
						else $insert = array(
							'$'.$this->_table.'_id' => (string) $this->id,
							'$'.$matched.'_id' => (string) $args[0],
							'$flags' => $flags
						);
						try { $this->_connection->replace($use, $insert); }
						catch(\PDOException $e) {

							/**
							 * Update Flags
							 * $flags column must be last in the insert!
							 * @author Nate Ferrero
							 */
							$where = '';
							foreach($insert as $key => $value) {
								if($key == '$flags') {
									$insert = array($key => $value);
									break;
								} else
									$where .= ($where == '' ? '' : ' AND ') . "`$key` = $value";
							}
							$this->_connection->replace($use, $insert, "WHERE $where");
						}
					}
					
					$this->save();
					return true;
				}

				return false;
			break;
			case 'unlink':
				/**
				 * Let everything know
				 */
				e::$events->deferred_register('sql_model_event', 'unlinked', $this->__map(), $matched.':'.$args[0]);
			
				if(isset($relation_tables['y']) && in_array($matched, $relation_tables['y'])) {
					if($plural) foreach($args[0] as $id) {
						$update =  array('$'.$this->_table.'_id' => (string) 0);
						$where = "WHERE `id` = '".$id."'";
						$this->_connection->update($matched, $update, $where);
					}
					
					else if(!$plural) {
						$update =  array('$'.$this->_table.'_id' => (string) 0);
						$where = "WHERE `id` = '".$args[0]."'";
						$this->_connection->update($matched, $update, $where);
					}
					
					$this->save();
					return true;
				}
				
				else if(isset($relation_tables['o']) && in_array($matched, $relation_tables['o'])) {
					$update =  array('$'.$matched.'_id' => (string) 0);
					$where = "WHERE `id` = '".$this->id."'";
					$this->_connection->update($this->_table, $update, $where);
					
					$this->save();
					return true;
				}
				
				else if(isset($relation_tables['x']) && in_array($matched, $relation_tables['x'])) {
					$table1 = '$connect '."$matched $this->_table";
					$table2 = '$connect '."$this->_table $matched";
					
					if($this->_exists($table1)) $use = $table1;
					else if($this->_exists($table2)) $use = $table2;
					
					foreach($args as $id) {
						$delete = array(
							'$'.$this->_table.'_id' => (string) $this->id,
							'$'.$matched.'_id' => (string) $id,
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
							'$'.$this->_table.'_id' => $this->id,
							"id" => $id
						);
						
						if($this->_connection->select($matched, $where)->row())
							return true;
						else return false;
					}
					
					else if(!$plural) {
						$where = array(
							'$'.$this->_table.'_id' => $this->id,
							"id" => $args[0]
						);

						if($this->_connection->select($matched, $where)->row())
							return true;
						else return false;
					}
				}
				
				else if(isset($relation_tables['o']) && in_array($matched, $relation_tables['o'])) {
					if(isset($this->{'$'.$matched.'_id'}) && $this->{'$'.$matched.'_id'} == $args[0])
						return true;
					else return false;
				}
				
				else if(isset($relation_tables['x']) && in_array($matched, $relation_tables['x'])) {
					$table1 = '$connect '."$matched $this->_table";
					$table2 = '$connect '."$this->_table $matched";
					
					if($this->_exists($table1)) $use = $table1;
					else if($this->_exists($table2)) $use = $table2;
					
					foreach($args as $id) {
						$where = array(
							'$'.$this->_table.'_id' => (string) $this->id,
							'$'.$matched.'_id' => (string) $id,
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
				throw new InvalidRequestException("`$method` is not a valid request as `$func(...)` on the `$this->_table` model. Valid requests are `get`, `link`, `unlink`, and `haslink`.");
			break;
		}
		
	}
	private function _exists($table = false) {
		static $cache = array();

		$table = $table ? $table : $this->_table;

		if(isset($cache[$table]))
			return $cache[$table];
		
		if(!$this->_connection->query("SHOW TABLES LIKE '$table'")->row())
			return $cache[$table] = false;
		else return $cache[$table] = true;
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

		/**
		 * Flags
		 * @author Nate Ferrero
		 */
		if($extension == 'flags')
			return $this->model->__getFlags();

		/**
		 * Load extension
		 * @author Nate Ferrero
		 */
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
		if(!is_object($this->extension)) throw new Exception("Extension not included: $this->extension");
		if(method_exists($this->extension, $method))
			return $this->extension->$method($this->model, $var, $val);
	}

	public function __get($var) {
		$method = 'modelGet';
		if(!is_object($this->extension)) throw new Exception("Extension not included: $this->extension");
		if(method_exists($this->extension, $method))
			return $this->extension->$method($this->model, $var);
	}

	public function __isset($var) {
		$method = 'modelIsset';
		if(!is_object($this->extension)) throw new Exception("Extension not included: $this->extension");
		if(method_exists($this->extension, $method))
			return $this->extension->$method($this->model, $var);
	}

	public function __call($method, $args) {
		$method = "model" . ucfirst($method);
		if(!is_object($this->extension)) throw new Exception("Extension not included: $this->extension");
		array_unshift($args, $this->model);
		return call_user_func_array(array($this->extension, $method), $args);
	}

}