<?php

namespace bundles\SQL;
use bundles\SQL;
use Exception;
use e;

/**
 * @todo Make all paging functions part of the list method
 */

class ListObj implements \Iterator, \Countable {
	
	/**
	 * DB Connection
	 */
	public $_connection = 'default';
	
	/**
	 * Tables
	 */
	public $_table;

	/**
	 * History
	 */
	public $_query_history = array();

	/**
	 * Extension Handler
	 */
	private $_extensionHandler;

	/**
	 * Table Config Data
	 */
	private $_tableConfig;

	/**
	 * m2m record connection
	 */
	protected $_m2m;
	
	/**
	 * Results
	 */
	protected $_result_array;
	protected $_result_model;
	protected $_results;
	public $position = 0;
	protected $_has_query = false;
	protected $_raw;
	
	/**
	 * Query Conditions
	 */
	protected $_fields_select = '*';
	protected $_tables_select;
	protected $_join = false;
	protected $_query_cond = array();
	protected $_order_cond = array();
	protected $_group_cond = array();
	protected $_distinct_cond = false;
	protected $_custom_query;
	
	/**
	 * Limit Conditions
	 */
	protected $_limit = false;
	protected $_limit_size = false;
	protected $_page_length = 5;
	protected $_on_page = 1;
	
	/**
	 * Count of all items int the result
	 */
	protected $_count = 0;
	protected $_sum = array();
	protected $_average = array();
	
	protected $_tb_singular;
	protected $_tb_plural;

	/**
	 * Virtual list models
	 * @author Nate Ferrero
	 */
	protected $_models = array();

	/**
	 * Joined tables array
	 * @author Kelly Becker
	 */
	protected $_join_table = array();

	/**
	 * Special features
	 */
	protected $_ensure_page_contains_id = null;
	
	/**
	 * List constructor
	 *
	 * @param string $table 
	 * @author Kelly Lauren Summer Becker
	 */
	public function __construct($table = false, $connection = false, $config = array()) {
		if($table) $this->_table = $table;
		if($connection) $this->_connection = $connection;
		
		if(empty($config)) $this->_config = Bundle::$db_structure_clean[$this->_table];
		else $this->_config = $config;

		$spec = explode('.',$this->_table);
		$bundle = array_shift($spec);

		// Determine Raw
		if((empty($this->_config["singular"]) && empty($this->config["plural"])) || !empty($this->_config["raw"]))
			$this->_raw = true;

		// Singular / Plurals for Model Usage
		else {
			if(empty($this->_config['singular']))
				throw new Exception("Double check your `singular:` key and value in `$bundle`'s bundle `./configure/sql_structure.yaml` file on the `$table` table");
			if(empty($this->_config['plural']))
				throw new Exception("Double check your `plural:` key and value in `$bundle`'s bundle `./configure/sql_structure.yaml` file on the `$table` table");
			
			$this->_tb_singular = $this->_config['singular'];
			$this->_tb_plural = $this->_config['plural'];
		}
		
		/**
		 * Add default table to tables select
		 */
		$this->_tables_select = "`$this->_table`";
		
		/**
		 * Run any initialization functions
		 */
		$this->initialize();
	}
	
	/**
	 * Placeholder for extendable list object
	 *
	 * @return void
	 * @author Kelly Lauren Summer Becker
	 */
	protected function initialize() {
		
	}

	/**
	 * Returns the table name
	 * @author Kelly Becker
	 */
	public function __getTable() {
		return $this->_table;
	}

	/**
	 * Returns the last join table
	 * @author Kelly Becker
	 */
	public function __getJoinTable() {
		return array_pop($this->_join_table);
	}
	
	/**
	 * Debug the query
	 *
	 * @return void
	 * @author Kelly Lauren Summer Becker
	 */
	public function debug() {
		$query = $this->_query_cond;
		$table = $this->_table;
		
		
		
		// $query $table
		eval(d);
	}

	public function __clone() {
		$this->_count = 0;
		$this->_sum = array();
		$this->_average = array();
	}

	/**
	 * Ensure that page contains ID
	 * @author Nate Ferrero
	 */
	public function ensurePageContainsID($id) {
		$this->_ensure_page_contains_id = $id;
		return $this;
	}
	
	/**
	 * Add a condition to your list query
	 *
	 * @param string $field 
	 * @param string $value 
	 * @param string $table 
	 * @param string $verify 
	 * @return void
	 * @author Kelly Lauren Summer Becker
	 */
	public function condition($field, $value = false, $table = false, $verify = false) {
		/**
		 * Reset query
		 */
		$this->_has_query = false;
		$this->_results = null;

		/**
		 * Prepare condition values
		 */
		$signal	= strpos($field, ' ') ? substr($field, strpos($field, ' ') + 1) : '=';
		$field 	= strpos($field, ' ') ? substr($field, 0, strpos($field, ' ')) 	: $field;
		$value 	= strpos($value, ':') === 0 && ctype_alpha(substr($value, 1) == true) ? '`'.substr($value, 1).'`' : $value;
		$value 	= $value === false || is_null($value) || $this->_is_numeric($value) || strpos($value, '`') === 0 ? $value : "'$value'";
		
		/**
		 * If is null make sure we are checking NULL not 'NULL' or '' or 0
		 */
		if(is_null($value)) $value = 'NULL';
		
		/**
		 * Make sure that if we join tables this condition stays on this (or the provided) table
		 */
		if(!$table) $table = $this->_table;
		$field	= strpos($field, '`') === 0 ? $field : "`$table`.`$field`";

		if($verify) return "$field $signal $value";
		else $this->_query_cond[] = "$field $signal $value";
		
		return $this;
	}
	
	/**
	 * Add a Left/Right Join
	 *
	 * @param string $type 
	 * @param string $use 
	 * @return void
	 * @author Kelly Lauren Summer Becker
	 */
	public function join($type = 'LEFT', $use = null, $cond = null) {

		if(!is_null($use) && !is_null($cond)) {
			/**
			 * @todo more join table support
			 */
			$this->_join_table[] = $use;
			$this->_join .= " $type JOIN `$use` ON $cond";
		}
		else {
			/**
			 * @todo Add table name to _join_table[]
			 */
			$this->_join .= " $type";
		}
		
		return $this;
	}
	
	/**
	 * Many to many Left Join
	 *
	 * @param string $use 
	 * @param string $join 
	 * @param string $id 
	 * @return void
	 * @author Kelly Lauren Summer Becker
	 */
	public function m2m($use, $join, $id, $flags = 0) {

		$tmp = explode(' ', $use);
		array_shift($tmp);
		$this->_m2m = implode('-^-', $tmp);
		
		if(is_numeric($join)) $cond = "`$this->_table`.`id` = `$use`.`\$id_b`";
		else $cond = "`$this->_table`.`id` = `$use`.`\$".$this->_table."_id`";
		
		if($flags > 0) {
			$cond .= " AND `$use`.\$flags & $flags = $flags";
		}
		
		$this->join('LEFT', $use, $cond);
		if(is_numeric($join)) $this->condition("`$use`.`\$id_a` =", $id);
		else $this->condition("`$use`.`\$".$join."_id` =", $id);
		
		return $this;
	}
	
	/**
	 * Process Multiple Field Conditions
	 * Use: Comparing multiple fields to a single condition
	 *
	 * @param string $condition 
	 * @param string $fields 
	 * @param string $verify 
	 * @return void
	 * @author Kelly Lauren Summer Becker
	 */
	public function multiple_field_condition($condition, $fields, $verify = false) {
		if(!is_array($fields)) $fields = explode(' ', $fields);
		if(count($fields) == 0) return $this;
		
		$query = '';
		foreach($fields as $field) {
			if(strtoupper($field) == 'OR') $query .= ' OR ';
			else if(strtoupper($field) == 'AND') $query .= ' AND ';
			else $query .= "`$field` $condition";
		}
		
		if($verify) return "($query)";
		else $this->_query_cond[] = "($query)";
		
		return $this;
	}
	
	/**
	 * Add a manually formatted condition to your list query
	 *
	 * @param string $condition 
	 * @return void
	 * @author Kelly Lauren Summer Becker
	 */
	public function manual_condition($condition) {
		$this->_query_cond[] = $condition;
		return $this;
	}
	
	/**
	 * Process an array of conditions
	 *
	 * @param string $array 
	 * @return void
	 * @author Kelly Lauren Summer Becker
	 */
	public function condition_array($array) {
		if(!is_array($array)) return $this;
		
		foreach($array as $col=>$val) {
			$this->condition($col, $val);
		}
		
		return $this;
	}
	
	/**
	 * Create an isolated condition and add it to your list query
	 *
	 * @param string $condition 
	 * @return void
	 * @author Kelly Lauren Summer Becker
	 */
	public function isolated_condition($condition) {
		$this->_query_cond[] = "($condition)";
		return $this;
	}
	
	/**
	 * Searching fields for a specific thing
	 *
	 * @param string $term 
	 * @param string $fields 
	 * @param string $verify 
	 * @return void
	 * @author Kelly Lauren Summer Becker
	 */
	public function multiple_field_search($term, $fields, $verify = false) {
		$term = mysql_escape_string($term);
		if(strlen($term) == 0) return $verify ? '' : $this;
		
		$like 	= '`'.implode('` LIKE "%'.$term.'%" OR `', explode(' ', $fields)). '` LIKE "%'.$term.'%"';
		$fields = '`'.implode('`,`',explode(' ', $fields)). '`';
		
		if($verify) return "($like OR MATCH($fields) AGAINST('$term'))";
		else $this->_query_cond[] = "($like OR MATCH($fields) AGAINST('$term'))";
		
		return $this;
	}
	
	/**
	 * Clear the condition array
	 *
	 * @return void
	 * @author Kelly Lauren Summer Becker
	 */
	public function clear_query() {
		$this->_query_cond = array();
		return $this;
	}
	
	/**
	 * Add field to the selection
	 *
	 * @param string $field 
	 * @return void
	 * @author Kelly Lauren Summer Becker
	 */
	public function add_select_field($field) {
		$this->_fields_select .= ", $field";
		return $this;
	}
	public function replace_select_field($field) {
		$this->_fields_select = "$field";
		return $this;
	}
	
	/**
	 * Add item to group by
	 *
	 * @param string $field 
	 * @return void
	 * @author Kelly Lauren Summer Becker
	 */
	public function group_by($field) {
		$this->_group_cond[] = $field;
		return $this;
 	}
	
	public function distinct($field) {
 		$this->_distinct_cond = "`$field`";
 		return $this;
 	}
	
	/**
	 * Order SQL Results
	 *
	 * @param string $field 
	 * @param string $dir 
	 * @param string $reset 
	 * @return void
	 * @author Kelly Lauren Summer Becker
	 */
	public function order($field, $dir = 'ASC', $reset = false) {
		if($reset) $this->_order_cond = array();
		$field = ctype_alnum($field) ? "`$field`" : $field;
		if(!$field) return $this;
		$dir = ctype_alnum($dir) ? strtoupper($dir) : 'ASC';
		$this->_order_cond[] = "$field $dir";
		
		return $this;
	}
	
	/**
	 * Limit the SQL Results
	 *
	 * @param string $start 
	 * @param string $limit 
	 * @return void
	 * @author Kelly Lauren Summer Becker
	 */
	public function limit($start, $limit = false) {
		if(!is_numeric($start) || !(is_numeric($limit) || $limit == false)) return $this;
		$this->_limit_size = $limit == false ? $start : $limit;
		$this->_limit = $limit == false ? "0, $start" : "$start, $limit";
		return $this;
	}
	
	/**
	 * Clear the limit and show all results
	 *
	 * @return void
	 * @author Kelly Lauren Summer Becker
	 */
	public function clear_limit() {
		$this->_limit_size = false;
		$this->_limit = false;
		$this->_has_query = false;
		return $this;
	}
	
	/**
	 * Count Results
	 *
	 * @param string $all 
	 * @param string $fresh 
	 * @return void
	 * @author Kelly Lauren Summer Becker
	 */
	public function count($all = false, $fresh = false) {
		if($all == false && $this->_has_query != false) return count($this->_results);
		else if($all == false) {
			if(!$this->_count) $this->_run_query('count');
			if($this->_limit_size !== false)
				$c = $this->_count > $this->_limit_size ? $this->_limit_size : $this->_count;
			else $c = $this->_count;
			
			return $c;
		}
		else {
			if(!$this->_count || $fresh) $this->_run_query('count');
			return $this->_count;
		}
	}
	
	/**
	 * Show specific page of results
	 *
	 * @param string $page 
	 * @param string $length 
	 * @return void
	 * @author Kelly Lauren Summer Becker
	 */
	public function page($page = 1, $length = false) {
		if($length) $this->_page_length = $length;
		$page = $page < 1 ? 1 : $page;

		/**
		 * Check for current page requirement
		 * @author Nate Ferrero
		 */
		if(!empty($this->_ensure_page_contains_id)) {
			$id = (int) $this->_ensure_page_contains_id;
			$idList = $this->_run_query('ids');
			$pos = array_search($id, $idList);
			if($pos !== false) {
				$page = floor($pos / $this->_page_length) + 1;
			}
		}
		
		$this->_on_page = $page; $page --;
		$this->limit($page * $this->_page_length, $this->_page_length);
		return $this;
	}
	
	/**
	 * Return Paging Info
	 *
	 * @return void
	 * @author Kelly Lauren Summer Becker
	 */
	public function paging() {
		$pages = ceil($this->count('all') / $this->_page_length);
		return (object) array(
			'pages' => $pages,
			'page' => $this->_on_page,
			'length' => $this->_page_length,
			'items' => $this->count('all')
		);
	}
	
	/**
	 * Return Paging Navigation
	 *
	 * @return void
	 * @author Kelly Lauren Summer Becker
	 */
	public function paging_html($class = '', $gvar = "page") {
		$paging = $this->paging(); $output = ''; $i=1;
		if($paging->page > 1) 
			$output .= "<a class=\"$class\" href=\"?".http_build_query(array_merge(e::$resource->get, array($gvar => $paging->page - 1)))."\">&laquo;</a>";
		while($i<=$paging->pages) {
			$tmp = $class.($paging->page == $i ? ' selected disabled' : '');
			$output .= "<a class=\"$tmp\" href=\"?".http_build_query(array_merge(e::$resource->get, array($gvar => $i)))."\">$i</a>";
			if($i == 5 && $paging->pages > 10 && !isset($inc)) {
				$output .= '...';
				$i = $paging->pages - 4;
				$inc = true;
			} else $i++;
		}
		if($paging->page < $paging->pages) 
			$output .= "<a class=\"$class\" href=\"?".http_build_query(array_merge(e::$resource->get, array($gvar => $paging->page + 1)))."\">&raquo;</a>"; $i=1;
		return $output;
	}
	
	/**
	 * Return Button Paging Navigation
	 *
	 * @return void
	 * @author Nate Ferrero
	 */
	public function buttonPaging($gvar = "page") {
		$class = 'button';
		$paging = $this->paging(); $output = ''; $i=1;
		while($i<=$paging->pages) {
			$tmp = $class.($paging->page == $i ? ' selected disabled' : '');
			$output .= "<a class=\"$tmp\" href=\"?$gvar=$i\">$i</a>";
			if($i == 5 && $paging->pages > 10 && !isset($inc)) {
				$output .= '...';
				$i = $paging->pages - 4;
				$inc = true;
			} else $i++;
		}
		return $output;
	}
	
	/**
	 * Get the average amount of a specific column
	 *
	 * @param string $column 
	 * @return float
	 * @author David Boskovic
	 */
	public function average($column) {
		if(!$this->_average[$column]) $this->_run_query('average', $column);
		return $this->_average[$column];
	}


	/**
	 * Get the sum of a specific column
	 *
	 * @param string $column 
	 * @return void
	 * @author Kelly Lauren Summer Becker
	 */
	public function sum($column) {
		if(!$this->_sum[$column]) $this->_run_query('sum', $column);
		return $this->_sum[$column];
	}
	
	/**
	 * Get the current total page count
	 *
	 * @return void
	 * @author Kelly Lauren Summer Becker
	 */
	public function current_page_count() {
		$paging = $this->paging();
		return max(0, min($paging->length, $paging->items - ($paging->page - 1) * $paging->length));
	}

	/**
	 * Checks for hasOne connections
	 * @author Nate Ferrero
	 */
	public function hasOne($map) {
		if($map instanceof Model);
		else $map = e::map($map);

		$table = $map->__getTable();
		$id = $map->id;

		$column = '$' . $table . '_id';

		return $this->condition($column, $id);
	}

	/**
	 * Checks for specific connections
	 * @author Kelly Lauren Summer Becker
	 */
	public function m2mConnection($map) {
		if($map instanceof Model);
		else $map = e::map($map);

		$table = $map->__getTable();
		$id = $map->id;

		$table1 = "\$connect $table $this->_table";
		$table2 = "\$connect $this->_table $table";
		$table3 = "\$connect $this->_table";
					
		if($this->_exists($table1)) $use = $table1;
		else if($this->_exists($table2)) $use = $table2;
		else if($this->_exists($table3)) { $use = $table3; $same = true; }

		return $this->m2m($use, (isset($same) ? 1 : $table), $id);
	}

	public function raw() {
		return $this->_run_query(false, false, true);
	}
	
	public function _run_query($count = false, $extra = false, $raw = false) {
		/**
		 * Run all extensions with this method
		 */
		$this->_->all('_on_run_query');

		if($count === 'debug') {
			$count = false;
			$debug = true;
		}
		
		/**
		 * Create a blank condition statement
		 */
		$cond = ' ';
		
		/**
		 * Process query conditions
		 */
		if(count($this->_query_cond) > 0) {
			$cond .= 'WHERE ';
			foreach($this->_query_cond as $key => $condi) {
				if(count($this->_query_cond) > 1 && $key != 0) $cond .= '&& ';
				$cond .= $condi.' ';
			}
		}

		/**
		 * Dump Query Conditions
		 * @author Nate Ferrero
		 */
		if(isset($_GET['--sql-cond'])) {
			//$this;$cond;
			if(e\after($_GET['--sql-cond']))
				eval(d);
		}

		/**
		 * Cannot add JOINS or GROUP BY's to the count
		 * @todo make sure this doesnt' break other stuff.
		 * @author David Boskovic
		 */
		if($count == 'count') {

			/**
			 * Prepare the query to run
			 */
			$query = "SELECT COUNT(*) as `ct` FROM $this->_tables_select".($this->_join ? $this->_join : '')." $cond";
			/**
			 * Run query
			 */
			$results = e::sql($this->_connection)->query($query)->row();
			
			/**
			 * Return Count
			 */
			$this->_count = (int) ($results['ct'] ? $results['ct'] : 0);
			return $this->_count;
		}
		elseif($count == 'sum') {
			/**
			 * Prepare the query to run
			 */
			$query = "SELECT SUM(`$extra`) as `ct` FROM $this->_tables_select".($this->_join ? $this->_join : '')." $cond";

			/**
			 * Run query
			 */
			$results = e::sql($this->_connection)->query($query)->row();
			
			/**
			 * Return Sum
			 */
			$this->_sum[$extra] = ($results['ct'] ? $results['ct'] : 0);
			return $this->_sum[$extra];
		}
		elseif($count == 'average') {
			/**
			 * Prepare the query to run
			 */
			$query = "SELECT AVG(`$extra`) as `ct` FROM $this->_tables_select".($this->_join ? $this->_join : '')." $cond";

			/**
			 * Run query
			 */
			$results = e::sql($this->_connection)->query($query)->row();
			
			/**
			 * Return Sum
			 */
			$this->_average[$extra] = ($results['ct'] ? $results['ct'] : 0);
			return $this->_average[$extra];
		}

		/**
		 * Process Group By Conditions
		 */
		if(count($this->_group_cond) > 0) {
			foreach($this->_group_cond as $key => $condi) {
				$gc[] = $count == 'sum' ? "`_group`" : "`$condi`";
			}
			$gc = implode(', ', $gc);
			$cond .= 'GROUP BY '.$gc;
		}
		
		/**
		 * Process Order Conditions
		 */
		if((!$count || $count == 'sum' || $count == 'ids') && count($this->_order_cond) > 0) {
			$cond .= 'ORDER BY ';
			foreach($this->_order_cond as $key => $condi) {
				if(count($this->_order_cond) > 1 && $key != 0) $cond .= ', ';
				$cond .= $condi.' ';
			}
		}

		/**
		 * Allow getting ID list
		 * @author Nate Ferrero
		 */
		if($count == 'ids') {
			/**
			 * Prepare the query to run
			 */
			$fields_select = "`$this->_table`.`id`";
			$query = "SELECT $fields_select FROM $this->_tables_select".($this->_join ? $this->_join : '')." $cond";
			
			/**
			 * Return the query that will be run for debug purposes
			 */
			if(isset($debug) && $debug) return $query;
			
			/**
			 * Run query
			 */
			$results = e::sql($this->_connection)->query($query)->all();
			
			/**
			 * Return IDs
			 */
			return e\array_get_keys($results, 'id');
		}
		


		/**
		 * Set Result Limit
		 */
		if(!$count && $this->_limit) $cond .= 'LIMIT '.$this->_limit.' ';
		
		/**
		 * Grab the fields to select and add join if one exists
		 */
		$fields_select = $this->_fields_select;
		
		/**
		 * Set us to grab the row count
		 */
		if($count && $count != 'sum') 
			$fields_select = $this->_distinct_cond ? "COUNT(DISTINCT $this->_distinct_cond) AS `ct`" : "COUNT(*) as `ct`";
		
		/**
		 * Get the sum of a row
		 */
		
		/**
		 * Grab the distinct query item if one exists
		 */
		$distinct = $this->_distinct_cond ? "DISTINCT $this->_distinct_cond, " : '';
		
		/**
		 * Prepare the query to run
		 */
		$query = $this->_custom_query ? ($count ? $this->_custom_count_query : $this->_custom_query) : "SELECT $fields_select FROM $this->_tables_select".($this->_join ? $this->_join : '')." $cond";
		
		/**
		 * Return the query that will be run for debug purposes
		 */
		if(isset($debug) && $debug) return $query;
		
		/**
		 * Record queries run
		 * @author Nate Ferrero
		 */
		if(isset($_GET['--sql-history']) && strpos($query, $_GET['--sql-history']) !== false) {
			//$stack = debug_backtrace();
			//foreach($stack as &$trace) {
			//	if(isset($trace['object']))
			//		$trace['object'] = '[Object '.get_class($trace['object']).']';
			//	$trace['args'] = e\ToArray($trace['args']);
			//}



			// $query $stack 
			eval(d);
			$this->_query_history[] = array('query' => $query, 'stack' => $stack);
		} else {
			$this->_query_history[] = $query;
		}

		/**
		 * Run query
		 */
		if($raw) return $query;

		$results = e::sql($this->_connection)->query($query);
		
		/**
		 * Return the count total count of the rows
		 */
		if($count && $count != 'sum') {
			$cr = $results->row();
			$this->_count = $cr['ct'];
		}
		
		/**
		 * Return the sum of the row
		 */
		else if($count == 'sum') {
			if(count($this->_group_cond) == 0) {
				$cr = $results->row();
				$this->_sum[$extra] = $ct['ct'];
			}
			
			else if(count($this->_group_cond) == 1) {
				while($row = $results->row()) $this->_sum[$extra][$row['_group_cond']] = $row['ct'];
			}
			
			return true;
		}
		
		/**
		 * Return the raw results
		 */
		if($this->_raw) {
			$this->_results = $results->all();
			if($count === false)
				$this->_has_query = true;
			return;
		}
		
		$pp = array();
		list($bundle, $model) = explode('.', strtolower($this->_table));
		$model = "get".ucwords($this->_tb_singular);
		while($row = $results->row()) {

			if(!isset(e::$$bundle))
				throw new Exception("Bundle `$bundle` is not installed");
			
			$ppm = $this->_custom_query ? $row : e::$$bundle->$model($row);

			/**
			 * Set Flags
			 * @author Nate Ferrero
			 */
			if(isset($row['$flags']))
				$ppm->__setFlags($this->_m2m, $row['$flags']);

			$pp[] = $ppm;
		}

		/**
		 * Include virtual models
		 * @author Nate Ferrero
		 */
		foreach($this->_models as $model) {
			$pp[] = $model;
		}

		/**
		 * Don't reset results on count
		 * @author Nate Ferrero
		 */
		if($count === false) {
			$this->_results = $pp;
			$this->_has_query = true;
		}
	}
	
	/**
	 * Return Output
	 *
	 * @return void
	 * @author Kelly Lauren Summer Becker
	 */
	public function all($callback = false, $reload = false) {
		if($reload || $this->_has_query == false) $this->_run_query();
		if(!is_callable($callback)) return $this->_results;

		$return = array();
		/**
		 * Added flags
		 * @author Nate Ferrero
		 */
		foreach($this->_results as $result)
			$return[] = $callback($result, $result->__getFlags());

		return $return;
	}

	/**
	 * Include a model in the virtual list
	 * @author Nate Ferrero
	 */
	public function __includeModel($model) {
		$this->_models[] = $model;
	}

	/**
	 * Return sets of objects
	 *
	 * @author Robbie Trencheny
	 *
	 */
	public function setsOf($number) {
		$all = $this->all();
		$return = array();
		$index = 0;
		foreach($all as $item) {
			if($index % $number === 0) {
				if(isset($current)) {
					$return[] = $current;					
				}
				$current = array();
			}
			$current[] = $item;
			$index++;
		}
		if(isset($current)) {
			$return[] = $current;					
		}
		return $return;
	}

	/**
	 * Get first record
	 */
	public function first() {
		$this->rewind();
		return $this->current();
	}

	/**
	 * Implode all records with field
	 * @author Nate Ferrero
	 */
	public function implode($field, $separator = ', ') {
		$out = array();
		foreach($this->all() as $model) {
			if(method_exists($model, $field))
				$out[] = $model->$field();
			else
				$out[] = $model->$field;
		}
		return implode($separator, $out);
	}
	
	/**
	 * BEGIN ITERATOR METHODS ----------------------------------------------------------------
	 */
	
	public function rewind() {
		if($this->_has_query == false) $this->_run_query();
		$this->position = 0;
	}
	public function keys() {
		if($this->_has_query == false) $this->_run_query();
		return array_keys($this->_results[$this->position]);
	}

	public function current() {
		return $this->_results[$this->position];
	}

	public function key() {
		return $this->_results[$this->position]->id;
	}

	public function next() {
		++$this->position;
	}

	public function valid() {
		return isset($this->_results[$this->position]);
	}

	/**
	 * END ITERATOR METHODS ----------------------------------------------------------------
	 */
	
	/**
	 * Standard query access
	 */
	public function auto() {
		$fields = $this->_config[$this->_table]['fields'];
		foreach($_REQUEST as $key => $value) {
			if(empty($value)) continue;
			$value = preg_replace('[^a-zA-Z0-9_.-]', '', $value);
			if($key === 'search') {
				$cond = array();
				$search = func_get_args();
				foreach($search as $field) {
					if(isset($fields[$field]))
						$cond[] = "`$field` LIKE '%$value%'";
				}
				$cond = implode(' OR ', $cond);
				$this->manual_condition($cond);
			} else if(isset($fields[$key])) {
				$this->condition("$key LIKE", "%$value$%");
			}
		}
		return $this;
	}

	/**
	 * Isset to allow read-only extension loading
	 * @author Nate Ferrero
	 */
	public final function __isset($field) {

		/**
		 * Extension handler
		 * @author Nate Ferrero
		 */
		if($field === '_') {
			return true;
		}

		return false;
	}

	/**
	 * Get to allow read-only extension loading
	 * @author Nate Ferrero
	 */
	public final function __get($field) {

		/**
		 * Extension handler
		 * @author Nate Ferrero
		 */
		if($field === '_') {
			if(!isset($this->_extensionHandler))
				$this->_extensionHandler = new ListExtensionHandler($this);
			return $this->_extensionHandler;
		}

		return null;
	}

	/**
	 * Ignore uninstantiated Functions
	 * @author Kelly Becker
	 */
	public final function __call($method, $args) {
		return $this;
	}

	/**
	 * Find the right table
	 * @author Kelly Becker
	 */
	private function _exists($table = false) {
		static $cache = array();

		$table = $table ? $table : $this->_table;

		if(isset($cache[$table]))
			return $cache[$table];
		
		if(!e::sql($this->_connection)->query("SHOW TABLES LIKE '$table'")->row())
			return $cache[$table] = false;
		else return $cache[$table] = true;
	}

	/**
	 * Is Numeric (Not Double)
	 */
	private function _is_numeric($v) {
		return preg_match('/^[0-9]+\.?[0-9]+$/', $v) ? true : false;
	}
}


/**
 * List Extension Handler
 * @author Nate Ferrero
 */
class ListExtensionHandler {

	private $list;
	private $extensions = array();
	private $lextensions = array();

	public function __construct($list) {
		$this->list = $list;
	}

	public function __isset($extension) {
		return true;
	}

	public function __get($extension) {
		$extension = strtolower($extension);
		if(!isset($this->extensions[$extension]))
			$this->extensions[$extension] = new ListExtensionAccess($this->list, Bundle::extension($extension));
		return $this->extensions[$extension];
	}

	public function all($method, $args = array()) {
		$return = array();
		
		if(empty($this->lextensions)) foreach(Bundle::$db_structure_clean as $table) {
			if(isset($table['extensions'])) foreach($table['extensions'] as $ext)
				if(!in_array($ext, $this->lextensions))
					$this->lextensions[] = $ext;
		}

		foreach($this->lextensions as $ext) {
			$ext = strtolower($ext);
			if(!isset($this->extensions[$ext]))
				$this->extensions[$ext] = new ListExtensionAccess($this->list, Bundle::extension($ext));

			if($this->extensions[$ext]->method_exists($method))
				$return[] = $this->extensions[$ext]->__call($method, $args);
		}

		return $return;
	}

}

/**
 * List Extension Access
 * @author Nate Ferrero
 */
class ListExtensionAccess {

	private $list;
	private $extension;

	public function __construct($list, $extension) {
		$this->list = $list;
		$this->extension = $extension;
	}

	public function method_exists($method) {
		$method = "list" . ucfirst($method);
		if(!is_object($this->extension)) throw new Exception("Extension `$this->extension` is not installed.");
		return method_exists($this->extension, $method);
	}

	public function __call($method, $args) {
		$method = "list" . ucfirst($method);
		array_unshift($args, $this->list);
		if(!is_object($this->extension)) throw new Exception("Extension `$this->extension` is not installed.");
		return call_user_func_array(array($this->extension, $method), $args);
	}

}