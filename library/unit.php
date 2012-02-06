<?php

namespace Bundles\SQL;
use Exception;
use e;

/**
 * SQL Unit Tests
 * @author Nate Ferrero
 */
class Unit {
	
	public function tests() {
		
		e::$unit
			->test('database_connection')
			->description('Test database connection')
			->equals(true);
		
	}
	
	public function database_connection() {
		return e::$sql->query('SHOW TABLES')->row();
	}

}