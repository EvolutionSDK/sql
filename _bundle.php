<?php

namespace Bundles\SQL;
use Exception;
use PDOException;
use e;

/**
 * SQL Exceptions
 */
class NoMatchException extends Exception {
	public $severity = 5;
}

class InvalidRequestException extends Exception {
	public $severity = 2;
}

/**
 * SQL Bundle
 */
class Bundle {
	
	public static $db_structure = array();
	public static $db_structure_clean = array();
	public static $connection_flags = array();
	public static $changed = false;
	
	private $connections = array();

	public function _on_framework_database() {
		
		// Add manager
		e::configure('manage')->activeAddKey('bundle', __NAMESPACE__, 'sql');

		// SQL Init event
		e::$events->sql_init();

		/**
		 * Check flags
		 * @author Nate Ferrero
		 */
		if(isset($_GET['--sql-flags']))
			dump(Bundle::$connection_flags);

		e::configure('autoload')->activeAddKey('special', 'Bundles\\SQL\\callException', __DIR__ . '/library/sqlbundle.php');
	}
	
	public function __initBundle() {
		e\Trace("SQL Initializing.");
		$enabled = e::$environment->requireVar('SQL.Enabled', "yes | no");
		
		/**
		 * Build Relationships
		 */
		if($enabled === true || $enabled === 'yes') $this->build_relationships();
		
		/**
		 * Build Architecture
		 */
		if($enabled === true || $enabled === 'yes')
			if(e::$sql->query("SHOW TABLES")->count() == 0) $this->build_architecture();

		/**
		 * SQL Ready
		 */
		e::$events->sql_ready();

		/**
		 * Extend environment variables through SQL
		 * @author Nate Ferrero
		 */
		//$this->_sql_environment();
	}

	/**
	 * Load environment overrides from database
	 * @todo Make this more anonymous with regard to environment storage mechanism
	 * @todo Move this logic into SQL bundle responding to environment_load event
	 * @author Nate Ferrero
	 */
	public function _sql_environment() {

		try {
			$vars = e::$sql->query('SELECT * FROM `environment.variable`')->all();dump($vars);
			foreach($vars as $key => $value)
				self::$environment[strtolower($key)] = $value;
		} catch(Exception $e) {

			/**
			 * Create the table
			 */
			e::$sql->query("CREATE TABLE `environment.variable` (
			  `updated_timestamp` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			  `created_timestamp` datetime DEFAULT NULL,
			  `name` varchar(255) NOT NULL,
			  `value` text NOT NULL,
			  PRIMARY KEY (`name`)
			) ENGINE=MyISAM DEFAULT CHARSET=latin1;");

		}
	}
	
	public function __getBundle($method = false) {
		//if(!isset($this->connections['default']))
			// if it doesnt have a connection add the default connection
		// return useConnection on the default bundle
		return $this->useConnection('default');
	}
	
	/**
	 * Run a query e::sql("query string");
	 *
	 * @param string $query 
	 * @return void
	 * @author David Boskovic
	 */
	public function __callBundle($connection = 'default') {
		if($connection == '%bundle%') return $this;
		
		return $this->useConnection($connection);
	}
	
	/**
	 * Return a query builder object on an established connection.
	 *
	 * @param string $slug 
	 * @return void
	 * @author David Boskovic
	 */
	public function useConnection($slug='default') {
		if(isset($this->connections[$slug]) && $this->connections[$slug] instanceof Connection)
			return $this->connections[$slug];
		
		// Check that slug is a string
		if(!is_string($slug))
			throw new Exception("Database connection slug must be a string when
				calling `e::sql(<i>slug</i>)` or `e::$sql->useConnection(<i>slug</i>)`");

		/**
		 * Check for default and use environment if set
		 * @author Nate Ferrero
		 */
		if($slug == 'default') {
			$vars = array('XEROUND_DATABASE_INTERNAL_URL', 'XEROUND_DATABASE_URL', 'DATABASE_URL', 'CLEARDB_DATABASE_URL');
			foreach($vars as $var) {
				if(!empty($_SERVER[$var])) {
					$default = $_SERVER[$var];
					break;
				}
			}
		}
		
		// Load up the database connection from environment if not already defined
		if(empty($default)) {
			$default = e::$environment->requireVar("sql.connection.$slug", 
			'service://username[:password]@hostname[:port]/database');
		}
		
		// Try to make the connection
		try {
			$conn = $this->addConnection($default, $slug);
		} catch(Exception $e) {
			e::$environment->invalidVar("sql.connection.$slug", $e);
		}

		if(empty($conn))
			throw new Exception("Invalid SQL Connection");

		$conn->checkTimeSync();
		
		return $conn;
	}

	/**
	 * Return structure without indexes
	 * @author Kelly Becker
	 */
	private function __clean_structure() {
		if(!empty(self::$db_structure_clean))
			return self::$db_structure_clean;

		$array = self::$db_structure;
		foreach($array as $table => &$opts) {

			$fields = array();
			foreach($opts['fields'] as $field => $type) {
				if($field[0] === '+') $field = substr($field, 1);

				$fields[$field] = $type;
			}

			$opts['fields'] = $fields;
		}

		return self::$db_structure_clean = $array;
	}
	
	/**
	 * Create a new mysql server connection.
	 *
	 * @param string $slug 
	 * @param string $info 
	 * @return void
	 * @author David Boskovic
	 */
	public function addConnection($info, $slug = 'default') {
		$this->connections[$slug] = new Connection($info, $slug);
		return $this->connections[$slug];
	}

	/**
	 * Build SQL in Manager
	 * @author Kelly Becker
	 */
	public function __buildSQL() {
		$this->build_relationships();
		$this->build_architecture();
		return true;
	}
	
	/**
	 * Build hasOne and hasMany Relationships
	 *
	 * @return void
	 * @author Kelly Lauren Summer Becker
	 */
	public function build_relationships() {
		if(empty(self::$db_structure)) return false;
		
		foreach(self::$db_structure as $table=>$config) {
			/**
			 * Create Many to One connection table and columns
			 */
			if(isset($config['hasOne'])) foreach($config['hasOne'] as $tbl) {
				self::$db_structure[$table]['fields']['$'.$tbl.'_id'] = 'number';
				self::$db_structure[$tbl]['hasMany'][] = $table;
			}

			/**
			 * Create Many to One connection table and columns
			 */
			if(isset($config['hasMany'])) foreach($config['hasMany'] as $tbl) {
				self::$db_structure[$tbl]['fields']['$'.$table.'_id'] = 'number';
				self::$db_structure[$tbl]['hasOne'][] = $table;
			}

			/**
			 * Create Many to Many relationship
			 */
			if(isset($config['manyToMany'])) foreach($config['manyToMany'] as $tbl) {
				if(is_array($tbl))
					dump($tbl);
				self::$db_structure[$tbl]['manyToMany'][] = $table;
			}
			
			$config = array();
		}

		$this->__clean_structure();
	}

	public static function extension($ext) {
		$class = "\\Bundles\\SQL\\Extensions\\$ext";
		try {
			return new $class;
		}
		catch(e\AutoLoadException $e) {
			return $ext;
		}
	} 
	
	/**
	 * Load the Conglomerate of DB Structure Info and Run it through architect
	 *
	 * @return void
	 * @author Kelly Lauren Summer Becker
	 */
	private function build_architecture() {
		if(empty(self::$db_structure)) return false;
				
		$tables = array();
		foreach(self::$db_structure as $table=>$struct) {
			e::$sql->architect($table, $struct);
			$tables[] = $table;
		}
		
		$exists = e::$sql->query("SHOW TABLES")->all();
		foreach($exists as $table) {
			$table = end($table);
			
			if(strpos($table, '$') !== false) continue;
			if(in_array($table, $tables)) continue;
			//if(strpos($table, '.') === false) continue;
			
			Architect::$queries[] = "RENAME TABLE `$table` TO `\$archived $table`";
		}
	}

}
