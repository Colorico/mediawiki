<?php
/**
 * Show profiling data.
 *
 * Copyright 2005 Kate Turner.
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 *
 * @file
 */

namespace MediaWiki;

ini_set('zlib.output_compression', 'off');

$wgEnableProfileInfo = false;
require __DIR__ . '/src/WebStart.php';

header('Content-Type: text/html; charset=utf-8');

?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8">
	<title>Profiling data</title>
	<style>
		/* noc.wikimedia.org/base.css */
		
		* { margin: 0; padding: 0; }
		body { padding: 0.5em 1em; background: #fff; font: 14px/1.6 sans-serif; color: #333; }
		p, ul, ol, table { margin: 0.5em 0; }
		a { color: #0645ad; text-decoration: none; }
		a:hover { text-decoration: underline; }

		/*!
		 * Bootstrap v2.1.1
		 *
		 * Copyright 2012 Twitter, Inc
		 * Licensed under the Apache License v2.0
		 * http://www.apache.org/licenses/LICENSE-2.0
		 *
		 * Designed and built with all the love in the world @twitter by @mdo and @fat.
		 */

		table { max-width: 100%; background-color: transparent; border-collapse: collapse; border-spacing: 0; }
		.table { width: 100%; margin-bottom: 20px; }
		.table th, .table td { padding: 0.1em; text-align: left; vertical-align: top; border-top: 1px solid #ddd; }
		.table th { font-weight: bold; }
		.table thead th { vertical-align: bottom; }
		.table thead:first-child tr:first-child th, .table thead:first-child tr:first-child td { border-top: 0; }
		.table tbody + tbody { border-top: 2px solid #ddd; }
		.table-condensed th, .table-condensed td { padding: 4px 5px; }
		.table-striped tbody tr:nth-child(odd) td, .table-striped tbody tr:nth-child(odd) th { background-color: #f9f9f9; }
		.table-hover tbody tr:hover td, .table-hover tbody tr:hover th { background-color: #f5f5f5; }
		hr { margin: 20px 0; border: 0; border-top: 1px solid #eee; border-bottom: 1px solid #fff; }
	</style>
</head>
<body>
<?php

if (!$wgEnableProfileInfo) {
	echo '<p>Disabled</p></body></html>';
	exit(1);
}

$dbr = wfGetDB(DB_SLAVE);

if (!$dbr->tableExists('profiling')) {
	echo '<p>No <code>profiling</code> table exists, so we can\'t show you anything.</p><p>If you want to log profiling data, enable '
		. '<code>$wgProfiler[\'output\'] = \'db\'</code> in your StartProfiler.php and run <code>maintenance/update.php</code> to create '
		. 'the profiling table.</p></body></html>';
	exit(1);
}

$expand = [];
if (isset($_REQUEST['expand'])) {
	foreach (explode(',', $_REQUEST['expand']) as $f) {
		$expand[$f] = true;
	}
}

// @codingStandardsIgnoreStart
class ProfilePoint {
	// @codingStandardsIgnoreEnd

	public $name;
	public $count;
	public $time;
	public $children;

	public static $totaltime, $totalmemory, $totalcount;

	public function ProfilePoint($_name, $_count, $_time, $_memory) {
		$this->name = $_name;
		$this->count = $_count;
		$this->time = $_time;
		$this->memory = $_memory;
		$this->children = [];
	}

	public function add_child($_child) {
		$this->children[] = $_child;
	}

	public function display($_expand, $_indent = 0.0) {
		usort($this->children, 'compare_point');
		$ex = isset($expand[$this->name()]);
		$anchor = str_replace('"', '', $this->name());

		if (!$ex) {
			if (count($this->children)) {
				$url = getEscapedProfileUrl(false, false, $_expand + [$this->name() => true]);
				$extet = " <a id=\"{$anchor}\" href=\"{$url}#{$anchor}\">[+]</a>";
			} else {
				$extet = '';
			}
		} else {
			$e = [];
			foreach ($_expand as $name => $ep) {
				if ($name != $this->name() ) {
					$e += [$name => $ep];
				}
			}
			$url = getEscapedProfileUrl(false, false, $e);
			$extet = " <a id=\"{$anchor}\" href=\"{$url}#{$anchor}\">[–]</a>";
		}
		
		echo '<tr>';
		echo '<th>';
		echo '<div style="margin-left: ' . $_indent . 'em">';
		echo htmlspecialchars(str_replace(',', ', ', $this->name())) . $extet;
		echo '</div>';
		echo '</th>';
		echo '<td class="mw-profileinfo-timep">' . wfPercent($this->time() / self::$totaltime * 100) . '</td>';
		echo '<td class="mw-profileinfo-memoryp">' . wfPercent($this->memory() / self::$totalmemory * 100) . '</td>';
		echo '<td class="mw-profileinfo-count">' . $this->count() . '</td>';
		echo '<td class="mw-profileinfo-cpr"' . round(sprintf('%.2f', $this->callsPerRequest()), 2) . '</td>';
		echo '<td class="mw-profileinfo-tpc"' . round(sprintf('%.2f', $this->timePerCall()), 2) . '</td>';
		echo '<td class="mw-profileinfo-mpc"' . round(sprintf('%.2f', $this->memoryPerCall() / 1024), 2) . '</td>';
		echo '<td class="mw-profileinfo-tpr"' . round(sprintf('%.2f', $this->time() / self::$totalcount), 2) . '</td>';
		echo '<td class="mw-profileinfo-mpr"' . round(sprintf('%.2f', $this->memory() / self::$totalcount / 1024), 2) . '</td>';
		echo '</tr>';
		if ($ex) {
			foreach ($this->children as $child) {
				$child->display($_expand, $_indent + 2);
			}
		}
	}

	public function name() {
		return $this->name;
	}

	public function count() {
		return $this->count;
	}

	public function time() {
		return $this->time;
	}

	public function memory() {
		return $this->memory;
	}

	public function timePerCall() {
		// @codingStandardsIgnoreStart
		return @($this->time / $this->count);
		// @codingStandardsIgnoreEnd
	}

	public function memoryPerCall() {
		// @codingStandardsIgnoreStart
		return @($this->memory / $this->count);
		// @codingStandardsIgnoreEnd
	}

	public function callsPerRequest() {
		// @codingStandardsIgnoreStart
		return @($this->count / self::$totalcount);
		// @codingStandardsIgnoreEnd
	}

	public function timePerRequest() {
		// @codingStandardsIgnoreStart
		return @($this->time / self::$totalcount);
		// @codingStandardsIgnoreEnd
	}

	public function memoryPerRequest() {
		// @codingStandardsIgnoreStart
		return @($this->memory / self::$totalcount);
		// @codingStandardsIgnoreEnd
	}

	public function fmttime() {
		return sprintf('%5.02f', $this->time);
	}
};

function compare_point(MediaWiki\ProfilePoint $_a, MediaWiki\ProfilePoint $_b) {
	// @codingStandardsIgnoreStart
	global $sort;
	// @codingStandardsIgnoreEnd
	
	switch ($sort) {
		case 'name':
			return strcmp($_a->name(), $_b->name());
		case 'time':
			return $_a->time() > $_b->time() ? -1 : 1;
		case 'memory':
			return $_a->memory() > $_b->memory() ? -1 : 1;
		case 'count':
			return $_a->count() > $_b->count() ? -1 : 1;
		case 'time_per_call':
			return $_a->timePerCall() > $_b->timePerCall() ? -1 : 1;
		case 'memory_per_call':
			return $_a->memoryPerCall() > $_b->memoryPerCall() ? -1 : 1;
		case 'calls_per_req':
			return $_a->callsPerRequest() > $_b->callsPerRequest() ? -1 : 1;
		case 'time_per_req':
			return $_a->timePerRequest() > $_b->timePerRequest() ? -1 : 1;
		case 'memory_per_req':
			return $_a->memoryPerRequest() > $_b->memoryPerRequest() ? -1 : 1;
	}
}

$sorts = ['time', 'memory', 'count', 'calls_per_req', 'name', 'time_per_call', 'memory_per_call', 'time_per_req', 'memory_per_req'];
$sort = 'time';
if (isset($_REQUEST['sort'] ) && in_array($_REQUEST['sort'], $sorts)) {
	$sort = $_REQUEST['sort'];
}

$res = $dbr->select('profiling', '*', [], 'profileinfo.php', ['ORDER BY' => 'pf_name ASC']);

if (isset($_REQUEST['filter'])) {
	$filter = $_REQUEST['filter'];
} else {
	$filter = '';
}

?>
<form method="get" action="profileinfo.php">
	<p>
		<input type="text" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
		<input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort); ?>">
		<input type="hidden" name="expand" value="<?php echo htmlspecialchars(implode(",", array_keys($expand))); ?>">
		<input type="submit" value="Filter">
	</p>
</form>

<table class="mw-profileinfo-table table table-striped table-hover">
	<thead>
	<tr>
		<th><a href="<?php echo getEscapedProfileUrl(false, 'name'); ?>">Name</a></th>
		<th><a href="<?php echo getEscapedProfileUrl(false, 'time'); ?>">Time (%)</a></th>
		<th><a href="<?php echo getEscapedProfileUrl(false, 'memory'); ?>">Memory (%)</a></th>
		<th><a href="<?php echo getEscapedProfileUrl(false, 'count'); ?>">Count</a></th>
		<th><a href="<?php echo getEscapedProfileUrl(false, 'calls_per_req'); ?>">Calls/req</a></th>
		<th><a href="<?php echo getEscapedProfileUrl(false, 'time_per_call'); ?>">ms/call</a></th>
		<th><a href="<?php echo getEscapedProfileUrl(false, 'memory_per_call'); ?>">kb/call</a></th>
		<th><a href="<?php echo getEscapedProfileUrl(false, 'time_per_req'); ?>">ms/req</a></th>
		<th><a href="<?php echo getEscapedProfileUrl(false, 'memory_per_req'); ?>">kb/req</a></th>
	</tr>
	</thead>
	<tbody>
	<?php
	MediaWiki\ProfilePoint::$totaltime = 0.0;
	MediaWiki\ProfilePoint::$totalcount = 0;
	MediaWiki\ProfilePoint::$totalmemory = 0.0;

	function getEscapedProfileUrl($_filter = false, $_sort = false, $_expand = false) {
		// @codingStandardsIgnoreStart
		global $filter, $sort, $expand;
		// @codingStandardsIgnoreEnd

		if ($_expand === false) {
			$_expand = $expand;
		}

		return htmlspecialchars('?' .
								wfArrayToCgi([
									'filter' => $_filter ? $_filter : $filter,
									'sort' => $_sort ? $_sort : $sort,
									'expand' => implode(',', array_keys($_expand))
								])
							);
	}

	$points = [];
	$queries = [];
	$sqltotal = 0.0;

	$last = false;
	foreach ($res as $o) {
		$next = new MediaWiki\ProfilePoint($o->pf_name, $o->pf_count, $o->pf_time, $o->pf_memory);
		if ($next->name() == '-total' || $next->name() == 'main()') {
			MediaWiki\ProfilePoint::$totaltime = $next->time();
			MediaWiki\ProfilePoint::$totalcount = $next->count();
			MediaWiki\ProfilePoint::$totalmemory = $next->memory();
		}
		if ($last !== false) {
			if (preg_match( '/^' . preg_quote($last->name(), '/' ) . '/', $next->name())) {
				$last->add_child($next);
				continue;
			}
		}
		$last = $next;
		if (preg_match( '/^query: /', $next->name() ) || preg_match('/^query-m: /', $next->name())) {
			$sqltotal += $next->time();
			$queries[] = $next;
		} else {
			$points[] = $next;
		}
	}

	$s = new MediaWiki\ProfilePoint('SQL Queries', 0, $sqltotal, 0, 0);
	foreach ( $queries as $q ) {
		$s->add_child( $q );
	}
	$points[] = $s;

	usort($points, 'compare_point');

	foreach ($points as $point) {
		if (strlen($filter) && !strstr($point->name(), $filter)) {
			continue;
		}

		$point->display($expand);
	}
	?>
	</tbody>
</table>
<hr>
<p>Total time: <code><?php printf('%5.02f', MediaWiki\ProfilePoint::$totaltime); ?></code></p>

<p>Total memory: <code><?php printf('%5.02f', MediaWiki\ProfilePoint::$totalmemory / 1024); ?></code></p>
<hr />
</body>
</html>
