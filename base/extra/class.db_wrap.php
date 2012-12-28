<?php

// Kofradia
// (db funksjoner)

class db_wrap_debug extends db_wrap
{
	public $queries_text = array();
	public $lastquery = 0;
	public $lastq_s = false;
	public $lastq_r = false;
	
	/** Utfør spørring */
	public function query($query, $critical = true, $debug = false)
	{
		// hent data
		$result = parent::query($query, $critical, $debug);
		$info = mysql_info($this->link);
		
		// tid siden forrige spørring
		if ($this->lastquery)
		{
			$time = $this->time_last_begin - $this->lastquery;
		}
		else
		{
			$time = 0;
		}
		
		// lagre debug
		$this->queries_text[] = array(
			"script_time____" => (microtime(true)-SCRIPT_START)*1000,
			"time_last_query" => round($time, 6)*1000,
			"query_time_____" => round($this->time_last, 6)*1000,
			"query_info_____" => $info,
			"query__________" => $query
		);
		$this->lastquery = microtime(true);
		
		// send svaret tilbake
		return $result;
	}
}

class db_wrap
{
	public $link = false;
	public $queries = 0;
	public $time = 0;
	public $time_last_begin = 0;
	public $time_last_end = 0;
	public $time_last = 0;
	
	public $transaction = false;
	
	public function __construct()
	{
		// koble til
		//$this->connect();
	}
	
	/** Opprett kobling mot databasen */
	public function connect($host, $user, $pass, $dbname = null)
	{
		// koble til databasen
		$this->link = @mysql_connect($host, $user, $pass);
		
		if (!$this->link)
		{
			throw new SQLConnectException(mysql_error(), mysql_errno());
		}
		
		if ($dbname)
		{
			$this->set_database($dbname);
		}
	}
	
	/**
	 * Sett aktiv database
	 */
	public function set_database($dbname)
	{
		// ikke tilkoblet?
		if (!$this->link)
		{
			throw new SQLNoConnectionException();
		}
		
		// velg riktig database
		if (!@mysql_select_db($dbname, $this->link))
		{
			throw new SQLSelectDatabaseException(mysql_error($this->link), mysql_errno($this->link));
		}
	}
	
	/** Lukk tilkoblingen til databasen */
	public function close()
	{
		if ($this->link)
		{
			@mysql_close($this->link);
			$this->link = false;
		}
	}
	
	/** Utfør spørring */
	public function query($query, $critical = true, $debug = false)
	{
		// ikke tilkoblet?
		if (!$this->link)
		{
			throw new SQLNoConnectionException();
		}
		
		// øk teller
		++$this->queries;
		
		// utfør spørring
		$this->time_last_begin = microtime(true);
		$result = @mysql_query($query, $this->link);
		$this->time_last_end = microtime(true);
		
		$this->time_last = $this->time_last_end - $this->time_last_begin;
		$this->time += $this->time_last;
		
		// feil ved spørring (ikke vis dersom $critical = false)
		if (!$result && $critical)
		{
			$err = mysql_error($this->link);
			$errnum = mysql_errno($this->link);
			throw new SQLQueryException($err, $errnum);
		}
		elseif (!$result)
		{
			// legg til feilmelding
			global $_base;
			if (isset($_base->page)) $_base->page->add_message("Ukritisk databasefeil: ".mysql_error($this->link), "error");
		}
		
		// debug?
		if ($debug)
		{
			$this->debug($result, $query);
		}
		
		// send svaret tilbake
		return $result;
	}
	
	/** Hent siste ID som ble satt inn */
	public function insert_id()
	{
		return mysql_insert_id($this->link);
	}
	
	/** Quote verdi */
	public function quote($text, $null = true)
	{
		if (empty($text) && $null) return 'NULL';
		return "'".mysql_real_escape_string($text)."'";
	}
	
	/** Antall rader berørt */
	public function affected_rows()
	{
		return mysql_affected_rows($this->link);
	}
	
	/** Start transaksjon */
	public function begin($force = false)
	{
		if ($this->transaction && !$force) return false;
		$this->query("BEGIN");
		$this->transaction = true;
		return true;
	}
	
	/** Avslutt (fullfør) transaksjon */
	public function commit()
	{
		$this->query("COMMIT");
		$this->transaction = false;
	}
	
	/** Avbryt transaksjon */
	public function rollback()
	{
		$this->query("ROLLBACK");
		$this->transaction = false;
	}
	
	/** Debug spørring */
	public function debug($result, $query = "")
	{
		// fjern det som allerede har blitt sendt til buffer
		@ob_clean();
		
		// skriv xhtml
		echo '<!DOCTYPE html>
<html lang="no">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1" />
<meta name="author" content="Henrik Steen; HenriSt.net" />
<title>Query Debug</title>
<style type="text/css">
<!--
.q_debug td {
	white-space: nowrap;
}
-->
</style>
</head>
<body>
<h1>Query Debug</h1>
<p>
	Debug of MySQL query:<br />
	<pre>'.htmlspecialchars($query).'</pre>
</p>
<table cellpadding="2" cellspacing="0" border="1" frame="hidden" rules="all" class="q_debug">
	<thead>
		<tr>';
		
		// list opp feltene
		while ($field = mysql_fetch_field($result)) {
			echo '
			<th bgcolor="#EEEEEE">'.htmlspecialchars($field->name).'</th>';
		}
		
		echo '
		</tr>
	</thead>
	<tbody>';
		
		if (mysql_num_rows($result) == 0) {
			// ingen rader?
			echo '
		<tr>
			<td colspan="'.mysql_num_fields($result).'">No row exists.</td>
		</tr>';
		} else {
			// gå til første rad
			mysql_data_seek($result, 0);
			
			// vis hver rad
			while ($row = mysql_fetch_row($result)) {
				echo '
		<tr>';
				
				// gå gjennom hvert felt
				foreach ($row as $value) {
					echo '
			<td>'.($value == NULL ? '<i style="color: #CCC">NULL</i>' : ($value === "" ? '<i style="color: #CCC">TOMT</i>' : nl2br(htmlspecialchars($value)))).'</td>';
				}
				
				echo '
		</tr>';
			}
		}
		
		echo '
	</tbody>
</table>';
		
		echo '
<p>
	<a href="http://hsw.no/">hsw.no</a>
</p>
</body>
</html>';
		
		die;
	}
}



/** Exception type for database */
class SQLException extends HSException
{
	protected $sql_err;
	protected $sql_errnum;
	public function __construct($err, $errnum)
	{
		parent::__construct($err, $errnum);
		$this->sql_err = $err;
		$this->sql_errnum = $errnum;
	}
	public function getSQLError() { return $this->sql_err; }
	public function getSQLErrorNum() { return $this->sql_errnum; }
}

/** Exception: Databasetilkobling */
abstract class SQLConnectionException extends SQLException {}

/** Exception: Ingen databasetilkobling */
class SQLNoConnectionException extends SQLConnectionException {
	public function __construct()
	{
		parent::__construct("", 0);
		$this->message = "Det finnes ingen tilkobling til databasen.";
	}
}

/** Exception: Databasetilkobling: Selve tilkoblingen */
class SQLConnectException extends SQLConnectionException
{
	public function __construct($err, $errnum)
	{
		parent::__construct($err, $errnum);
		$this->message = "Kunne ikke opprette kobling med databasen: ($errnum) $err";
	}
}

/** Exception: Databasetilkobling: Velge database */
class SQLSelectDatabaseException extends SQLConnectionException
{
	public function __construct($err, $errnum)
	{
		parent::__construct($err, $errnum);
		$this->message = "Kunne ikke velge riktig database: ($errnum) $err";
	}
}

/** Exception: Databasespørring */
class SQLQueryException extends SQLException {
	public function __construct($err, $errnum)
	{
		parent::__construct($err, $errnum);
		$this->message = "Kunne ikke utføre spørring: ($errnum) $err";
	}
}