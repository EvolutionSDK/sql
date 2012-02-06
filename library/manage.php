<?php

namespace Bundles\SQL;
use Bundles\Manage\Tile;
use e;

/**
 * Evolution SQL Manage
 * @author Nate Ferrero
 */
class Manage {
	
	public $title = 'SQL';
	
	public function page($path) {
		return 'SQL';
	}
	
	public function tile() {
		$tile = new Tile('sql');
		$tile->body .= '<h2>Manage your database backup schedule, schema upgrades, and view statistics.</h2>';
		return $tile;
	}
}