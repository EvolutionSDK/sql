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
		
		// Load up the database connection from environment
		$default = e::$environment->requireVar("sql.connection.$slug", 
			'service://username[:password]@hostname[:port]/database');
		
		// Try to make the connection
		try {
			$conn = $this->addConnection($default, $slug);
		} catch(Exception $e) {
			e::$environment->invalidVar("sql.connection.$slug", $e);
		}
		
		$conn->checkTimeSync();
		
		return $conn;
	}

	/**
	 * Return structure without indexes
	 * @author Kelly Becker
	 */
	public function __structure() {
		static $struct = array();

		if(!empty($struct))
			return $struct;

		$array = self::$db_structure;
		foreach($array as $table => &$opts) {

			$fields = array();
			foreach($opts['fields'] as $field => $type) {
				if($field[0] === '+') $field = substr($field, 1);

				$fields[$field] = $type;
			}

			$opts['fields'] = $fields;
		}

		return $array;
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
	private function build_relationships() {
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

	}

	public static function extension($ext) {
		$class = "\\Bundles\\SQL\\Extensions\\$ext";
		return new $class;
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
			
			e::$sql->query("RENAME TABLE `$table` TO `\$archived $table`");
		}
	}

}
