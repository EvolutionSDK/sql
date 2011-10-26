<?php

namespace Evolution\SQL;
use Evolution\Kernel\Service;
use Evolution\Kernel\Completion;
use Evolution\Kernel\IncompleteException;
use Evolution\Kernel\Configure;
use Evolution\Environment\Bundle as Env;
use Exception;
use e;

/**
 * SQL Exceptions
 */
class NoMatchException extends Exception { }
class InvalidRequestException extends Exception { }

/**
 * Router Bundle
 */
class Bundle {
	
	public static $db_structure;
	
	private $connections = array();
	
	public function __construct($dir) {
		// establish the default mysql connection or throw an error
		// run service binding for connection established
		Service::bind('Evolution\SQL\Bundle::start', 'router:ready');
	}
	public function __bundle_response($method = false) {
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
	public function __invoke_bundle($connection = 'default') {
		
		return $this->useConnection($connection);
		
	}
	
	/**
	 * Get the database architect for a specific connection, leave false for current
	 *
	 * @param string $connection 
	 * @return void
	 * @author David Boskovic
	 */
	public function architect($connection = false) {
		
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
				calling `e::sql(<i>slug</i>)` or `e::sql()->useConnection(<i>slug</i>)`");

		// Load up the database connection from environment
		$default = e::environment()->requireVar("sql.connection.$slug", 
			'service://username[:password]@hostname[:port]/database');

		// Try to make the connection
		try {
			return $this->addConnection($default, $slug);
		} catch(ConnectionException $e) {
			e::environment()->invalidVar("sql.connection.$slug", $e);
		}
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
	 * Start SQL
	 */
	public static function start() {
		
		$check = Env::_require('sql.autoBuildArchitecture', 'yes | no');
		if($check === 'yes') {
			self::build_architecture();
		}
		else if($check !== 'no') {
			Env::_invalid('sql.autoBuildArchitecture', new Exception("The only acceptable values are `yes` or `no`"));
		}
		
		Service::run('sql:ready');
	}
	
	/**
	 * Load the Conglomerate of DB Structure Info and Run it through architect
	 *
	 * @return void
	 * @author Kelly Lauren Summer Becker
	 */
	public static function build_architecture() {
		if(empty(self::$db_structure)) return false;
		
		foreach(self::$db_structure as $table=>$config) {
			/**
			 * Create Many to One connection table and columns
			 */
			if(isset($config['hasOne'])) foreach($config['hasOne'] as $tbl) {
				self::$db_structure[$table]['fields']['$'.$tbl.'_id'] = 'number';
				//self::$db_structure[$tbl]['hasMany'][] = $tbl;
			}

			/**
			 * Create Many to One connection table and columns
			 */
			if(isset($config['hasMany'])) foreach($config['hasMany'] as $tbl) {
				self::$db_structure[$tbl]['fields']['$'.$table.'_id'] = 'number';
				self::$db_structure[$tbl]['hasOne'][] = $table;
			}
			
			$config = array();
		}
				
		$tables = array();
		foreach(self::$db_structure as $table=>$struct) {
			e::sql()->architect($table, $struct);
			$tables[] = $table;
		}
		
		$exists = e::sql()->query("SHOW TABLES")->all();
		foreach($exists as $table) {
			$table = end($table);
			
			if(strpos($table, '$') !== false) continue;
			if(in_array($table, $tables)) continue;
			if(strpos($table, '.') === false) continue;
			
			e::sql()->query("DROP TABLE `$table`");
		}
	}

}
