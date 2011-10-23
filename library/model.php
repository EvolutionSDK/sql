<?php

namespace Evolution\SQL;
use e;

class Model {

	/**
	 * Database and Table Store
	 */
	private $_connection;
	private $_table;

	/**
	 * Predefined ID on insert
	 */
	private $_set_id;
	private $_new = TRUE;

	/**
	 * Cache and used memory
	 */
	private static $_memory;
	private static $_this_memory;
	private static $_cache = array();

	/**
	 * Stored Data
	 */
	private $data;

	/**
	 * Has the model bee modified
	 */
	private $_modified = false;

	/**
	 * Initialize the model
	 *
	 * @param string $dbh
	 * @param string $table 
	 * @param string $id 
	 * @param string $set_id 
	 * @author Kelly Lauren Summer Becker
	 */
	public function __construct($connection, $table, $id = false, $set_id = false) {
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

		/**
		 * Is an ID being manually set?
		 */
		$this->_set_id = $set_id;

		/**
		 * If an ID is provided load the row, and store it to the cache
		 */
		if($id) {
			if(!isset(self::$_cache[$table][$id])) {
				if(!is_array($id)) $conds = array('id' => (string) $id);
				else $conds = $id;
				self::$_cache[$table][$id] = $this->_connection->select($table, $conds)->row();
			}

			$this->data =& self::$_cache[$table][$id];
		}

		/**
		 * If no ID is provided then load the fields
		 */
		else $this->data = $this->_connection->get_fields($table, true);

		/**
		 * If an id is in the model its not a new model
		 */
		if($this->id) $this->_new = false;
		
		/**
		 * Relationships
		 */
		

		/**
		 * Recalcuate the used memory and store it
		 */
		self::$_this_memory = (memory_get_usage(true) - $init_mem);
		self::$_memory += self::$_this_memory;
	}

	/**
	 * If the model was modified then save it in the destruct
	 *
	 * @return void
	 * @author Kelly Lauren Summer Becker
	 */
	public function __destruct() {
		if($this->_modified) $this->save();
	}

	/**
	 * Return isset() on the object var
	 *
	 * @param string $field 
	 * @return void
	 * @author Kelly Lauren Summer Becker
	 */
	public function __isset($field) {
		return isset($this->data[$field]);
	}

	/**
	 * Return the $this->data value for $field
	 *
	 * @param string $field 
	 * @return void
	 * @author Kelly Lauren Summer Becker
	 */
	public function __get($field) {
		if(!isset($this->data[$field])) return NULL;

		return $this->data[$field];
	}

	/**
	 * Set a new $this->data value for $field
	 *
	 * @param string $field 
	 * @param string $nval 
	 * @return void
	 * @author Kelly Lauren Summer Becker
	 */
	public function __set($field, $nval) {
		if(!array_key_exists($field, $this->data)) return;
		if($field == 'id'&&!$this->_set_id) return;

		$init_mem = memory_get_usage(true);
		$this->_modified = TRUE;

		$this->data[$field] = $nval;

		self::$_memory += (memory_get_usage(true) - $init_mem);
	}

	/**
	 * If no method is called return the DB Model info
	 *
	 * @return void
	 * @author Kelly Lauren Summer Becker
	 */
	public function __toString() {
		return "DB Model: #[$this->id] in table $this->_table. Is using $this->_memory bytes of memory.";
	}

	/**
	 * Return the $this->data model as an array
	 *
	 * @return void
	 * @author Kelly Lauren Summer Becker
	 */
	public function get_array() {
		return $this->data;
	}

	/**
	 * Save the $this->data into the table as a new row or update
	 *
	 * @param string $data 
	 * @return void
	 * @author Kelly Lauren Summer Becker
	 */
	public function save($data = false) {

		/**
		 * If $data is passed then process the array into the various $this->data values
		 */
		if(is_array($data)) {
			foreach($data as $key=>$val) {
				if($key == 'id'&&!$this->_set_id) continue;
				$this->$key = $val;
			}
		}

		/**
		 * If nothing was modified dont spend memory running the query
		 */
		if(!$this->_modified) return false;

		/**
		 * Process the query save
		 */
		$save = array();
		foreach($this->data as $key=>$val) {
			if($key == 'id'&&!$this->_set_id) continue;
			$save[$key] = $val;
		}

		/**
		 * Make the file as modified and then update/insert the values
		 */
		$this->_modified = false;
		if($this->id&&!$this->_new) $this->_connection->update_by_id($this->_table, $save, $this->id);
		else $this->data['id'] = $this->_connection->insert($this->_table, $save)->insertId();
	}

	/**
	 * Delete the row from the db (Poof no more model)
	 *
	 * @return void
	 * @author Kelly Lauren Summer Becker
	 */
	public function delete() {
		if(isset($this->id)) {
			$this->_connection->delete_by_id($this->_table, $this->id);
			unset(self::$_cache[$this->_table][$this->id]);
		}
	}
	
	public function __call($func, $args) {
		$search = preg_split('/([A-Z])/', $func, 2, PREG_SPLIT_DELIM_CAPTURE);
		$method = array_shift($search);
		$search = strtolower(implode('', $search));
		
		/**
		 * If the first argument is an array set it to the args
		 */
		if(isset($args[0]) && is_array($args[0])) $args = $args[0];
		
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
		foreach(Bundle::$db_structure as $table=>$relations) {
			/**
			 * Try and match the requested models
			 */
			if($relations['plural'] == $search && in_array($table, $possible_tables)) {
				$plural = true;
				$found = true;
			}
			else if($relations['singular'] == $search && in_array($table, $possible_tables)) {
				$plural = false;
				$found = true;
			}
			else $found = false;
						
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
		if(isset($found) && !$found) return false;
		
		switch($method) {
			case 'get':
				if(isset($relation_tables['y']) && in_array($matched, $relation_tables['y'])) 
					$conds = array("\$".$this->_table.'_id' => (string) $this->id);
				
				else if(isset($relation_tables['o']) && in_array($matched, $relation_tables['o'])) {
					$var = "\$".$matched.'_id';
					$conds = array('id' => (string) $this->$var);
				}
				
				else if(isset($relation_tables['x']) && in_array($matched, $relation_tables['x'])) {
					$table1 = "\$ $matched $this->_table";
					$table2 = "\$ $this->_table $matched";
					
					if($this->_exists($table1)) $use = $table1;
					else if($this->_exists($table2)) $use = $table2;
					
					$conds = array("\$".$this->_table.'_id' => (string) $this->id);
					$return = $this->_connection->select($use, $conds);
					
					if($plural) return $return->lists();
					else if(!$plural) return $return->model();
				}
				
				$return = $this->_connection->select($matched, $conds);
				
				if($plural) return $return->lists();
				else if(!$plural) return $return->model();
			break;
			case 'set':
			case 'add':
				if(isset($relation_tables['y']) && in_array($matched, $relation_tables['y'])) {
					if($plural) foreach($args as $id) {
						$update =  array("\$".$this->_table.'_id' => (string) $this->id);
						$where = "WHERE `id` = '".$id."'";
						$this->_connection->update($matched, $update, $where);
					}
					
					else if(!$plural) {
						$update =  array("\$".$this->_table.'_id' => (string) $this->id);
						$where = "WHERE `id` = '".$args[0]."'";
						$this->_connection->update($matched, $update, $where);
					}
					
					return true;
				}
				
				else if(isset($relation_tables['o']) && in_array($matched, $relation_tables['o'])) {
					$update =  array("\$".$matched.'_id' => (string) $args[0]);
					$where = "WHERE `id` = '".$this->id."'";
					$this->_connection->update($this->_table, $update, $where);
					
					return true;
				}
				
				else if(isset($relation_tables['x']) && in_array($matched, $relation_tables['x'])) {
					$table1 = "\$ $matched $this->_table";
					$table2 = "\$ $this->_table $matched";
					
					if($this->_exists($table1)) $use = $table1;
					else if($this->_exists($table2)) $use = $table2;
					
					if($plural) foreach($args as $id) {
						$insert = array(
							"\$".$this->_table.'_id' => (string) $this->id,
							"\$".$matched.'_id' => (string) $id,
						);
						$this->_connection->insert($use, $insert);
					}
					
					else if(!$plural) {
						$insert = array(
							"\$".$this->_table.'_id' => (string) $this->id,
							"\$".$matched.'_id' => (string) $args[0],
						);
						$this->_connection->insert($use, $insert);
					}
					
					return true;
				}				
			break;
			case 'unset':
			case 'remove':
				if(isset($relation_tables['y']) && in_array($matched, $relation_tables['y'])) {
					if($plural) foreach($args as $id) {
						$update =  array("\$".$this->_table.'_id' => (string) 0);
						$where = "WHERE `id` = '".$id."'";
						$this->_connection->update($matched, $update, $where);
					}
					
					else if(!$plural) {
						$update =  array("\$".$this->_table.'_id' => (string) 0);
						$where = "WHERE `id` = '".$args[0]."'";
						$this->_connection->update($matched, $update, $where);
					}
					
					return true;
				}
				
				else if(isset($relation_tables['o']) && in_array($matched, $relation_tables['o'])) {
					$update =  array("\$".$matched.'_id' => (string) 0);
					$where = "WHERE `id` = '".$this->id."'";
					$this->_connection->update($this->_table, $update, $where);
					
					return true;
				}
				
				else if(isset($relation_tables['x']) && in_array($matched, $relation_tables['x'])) {
					$table1 = "\$ $matched $this->_table";
					$table2 = "\$ $this->_table $matched";
					
					if($this->_exists($table1)) $use = $table1;
					else if($this->_exists($table2)) $use = $table2;
					
					if($plural) foreach($args as $id) {
						$delete = array(
							"\$".$this->_table.'_id' => (string) $this->id,
							"\$".$matched.'_id' => (string) $id,
						);
						$this->_connection->delete($use, $delete);
					}

					return true;
				}				
			break;
		}
		
	}
	
	private function _exists($table = false) {
		$table = $table ? $table : $this->_table;
		if(!$this->_connection->query("SHOW TABLES LIKE '$table'")->row()) return false;
		else return true;
	}
	
}