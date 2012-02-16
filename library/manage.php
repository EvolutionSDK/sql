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

	public function buildSQL() {
		return e::sql('%bundle%')->__buildSQL();
	}

	public function sqlInfo($rchange = false) {
		if($rchange) {
			$rchange = 0;
			foreach(Bundle::$db_structure as $info) {
				if($info['changed'] == true) $rchange++;
			}
			if($rchange > 0) return $rchange.' Pending Changes'; 
			else return;
		}

		ob_start();
		foreach(Bundle::$db_structure as $table => $info) {
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
		if(array_shift($path) == 'sync') return $this->buildSQL();
		if(array_shift($path) == 'uptd') return $this->sqlInfo();

		$all = array();
		
		echo '<style>' . file_get_contents(__DIR__ . '/manage/sql-style.css') . '</style>';
		echo '<script type="text/javascript">' . file_get_contents(__DIR__ . '/manage/jquery-1.7.min.js') . '</script>';
		echo '<script type="text/javascript">' . file_get_contents(__DIR__ . '/manage/sql-script.js') . '</script>';
		echo '<div class="controls">
				<span class="state-init"><em>Ready</em> | <a onclick="sql.sync()">Sync</a></span>
				<span class="state-running"><em>Running sync...</em></span>
				<span class="state-complete"><em>Complete!</em> | <a onclick="sql.sync()">Sync Again</a></span>
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