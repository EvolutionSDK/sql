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

	/**
	 * Show the list of the queries to be run
	 * @author Kelly Becker
	 */
	public function showQueries() {

		/**
		 * Load the relationships and tables to build
		 */
		e::sql('%bundle%')->__buildSQL();

		/**
		 * Load the Style for the page
		 */
		echo "<style>".file_get_contents(__DIR__.'/manage/sql-show.css')."</style>";

		/**
		 * Create the Form
		 */
		echo "<form action=\"/@manage/sql/run\" method=\"POST\" class=\"queries\">";
		foreach(Architect::$queries as $query) {
			$regQuery = preg_replace('/\s+/', ' ', preg_replace('/`[a-zA-Z.]+`/', '', $query));

			/**
			 * Default SQL Methods
			 */
			if(strpos($query, 'DROP TABLE') !== FALSE)
				$color = "red";
			if(strpos($query, 'RENAME TABLE') !== FALSE)
				$color = "red";
			if(strpos($query, 'ALTER TABLE') !== FALSE)
				$color = "yellow";
			if(strpos($query, 'CREATE TABLE') !== FALSE)
				$color = "green";

			/**
			 * More precise alter tables
			 */
			if(strpos($regQuery, 'ALTER TABLE DROP') !== FALSE)
				$color = "red";
			if(strpos($regQuery, 'ALTER TABLE ADD COLUMN') !== FALSE)
				$color = "green";

			/**
			 * Automatically check the box
			 */
			$checked = "checked=\"checked\"";

			/**
			 * If this is a potentially dangerous query dont check by default
			 */
			if($color === 'red') $checked = '';

			/**
			 * Create the element
			 */
			echo "<label style=\"background: $color;\"><input type=\"checkbox\" name=\"query[]\" value=\"$query\" $checked /> $query</label>";
		}

		/**
		 * Submit button
		 */
		echo "<label><input type=\"submit\" value=\"Run Selected Queries\" /></label>";
		echo "</form>";

		return;
	}

	/**
	 * Run the queries
	 * @author Kelly Becker
	 */
	public function runQueries() {
		$post = $_POST;

		/**
		 * Load the page style
		 */
		echo "<style>".file_get_contents(__DIR__.'/manage/sql-run.css')."</style>";
		
		/**
		 * Page title and wrapper
		 */
		echo "<h1 style=\"margin:15px;\">Run Queries</h1>";
		echo "<div class=\"queries\">";

		/**
		 * Show each query
		 */
		foreach($post['query'] as $query) {
			e::$sql->query($query);
			echo "<div>$query</div>";
		}

		echo "<div><a href=\"/@manage/sql/show\">Run Architect Again</a>";

		/**
		 * Close the page
		 */
		echo "</div>";
		return;
	}

	public function sqlInfo($rchange = false) {
		e::sql('%bundle%')->build_relationships();

		if($rchange) {
			$rchange = 0;
			foreach(Bundle::$db_structure_clean as $info) {
				if($info['changed'] == true) $rchange++;
			}
			if($rchange > 0) return $rchange.' Pending Changes'; 
			else return;
		}

		ob_start();
		foreach(Bundle::$db_structure_clean as $table => $info) {
			list($bundle, $tbl) = explode('.', $table);
			if($info['changed']) $changed = "red";
			else $changed = "green";
			echo "<li class='category'><h1 style='padding-left:25px;'><div class='led led-$changed' title='$var'></div>".ucwords($bundle)." &mdash; $table</h1>";
			echo "<h4>".(empty($info['singular']) ? '<span style="color:red;">You need to set a Singular value</span>' : 'Singular: '.$info['singular'])."</h4>";
			echo "<h4>".(empty($info['plural']) ? '<span style="color:red;">You need to set a Plural value</span>' : 'Plural: '.$info['plural'])."</h4>";
			echo "<br /><br />";
			echo '<ul class="bundles">';
			foreach($info as $type => $contents) {
				if($type == 'singular' || $type == 'plural' || $type == 'changed') continue;
				if($type == 'fields') echo "<h3>Table Columns</h3>";
				else echo "<h3>".ucwords($type)." Relationships</h3>";
				echo "<li class='bundle'><ul class='tests'>";
				foreach($contents as $var => $val) {
					if($val == '_suppress') continue;
					if(strpos($var, '$') === 0) $var = "<div class='led' style='border:none;box-shadow:none;margin-top:-3px;' title=".$var.">%</div>Connection to ".ucwords(substr(array_shift(explode('.',$var)),1)).": <span style='color:green;'>".$var."</span>";
					echo '<li class="test"><span class="description"><strong>'.$var.'</strong> - '.(is_array($val) ? $val['Type'] : $val).'</span></li>';
				}
				echo '</ul></li>';
			}
			echo '</ul>';
		}
		$return = ob_get_contents();
		ob_end_flush();
		return $return;
	}
	
	public function page($path) {
		if($path[0] == 'show') return $this->showQueries();
		if($path[0] == 'run') return $this->runQueries();

		$all = array();
		
		echo '<style>' . file_get_contents(__DIR__ . '/manage/sql-style.css') . '</style>';

		echo '<div class="controls">
				<span class="state-init"><a href="/@manage/sql/show">Run Architect</a></span>
			</div>';
		
		echo '<ul class="categories" style="margin: 0; padding: 0;">';
		echo $this->sqlInfo();
		echo '</ul>';
	}
	
	public function tile() {
		$tile = new Tile('sql');
		$tile->body .= '<h2>Manage your database backup schedule, schema upgrades, and view statistics.</h2>';
		$tile->alert = $this->sqlInfo(true);
		return $tile;
	}
}