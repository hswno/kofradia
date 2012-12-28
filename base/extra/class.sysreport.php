<?php

class sysreport
{
	/** For å hindre exceptions i å printe parametere */
	const EXCEPTION_ANONYMOUS = -1;
	
	/**
	 * Sende e-post med div info før data
	 */
	public static function log($data, $title = "Kofradia Email Log")
	{
		global $_base, $up_id;
		
		$msg = "Date: ".$_base->date->get()->format("r")."\n";
		
		// forsøk å hent brukerID fra cookie
		$uid = "ukjent";
		if (isset($_COOKIE[$GLOBALS['__server']['cookie_prefix'] . "id"]))
		{
			$uid = explode(":", $_COOKIE[$GLOBALS['__server']['cookie_prefix'] . "id"]);
			$uid = (int) $uid[1];
		}
		
		if (class_exists("login", false))
		{
			if (login::$logged_in)
			{
				$up_id = login::$user->player->id;
				$uid = login::$user->id;
			}
			elseif (isset($up_id)) $up_id = $up_id."(?)";
			else $up_id = 0;
			$msg .= "SpillerID: #".$up_id.(login::$logged_in ? ' ('.login::$user->player->data['up_name'].')' : '')."\n";
			$msg .= "Bruker: ".(login::$logged_in ? ' ('.login::$user->data['u_email'].')' : '')." ($uid).\n";
		}
		else
		{
			$msg .= "Bruker: Ukjent ($uid?).\n";
		}
		
		$msg .= "IP: ".$_SERVER['REMOTE_ADDR']."\n";
		$msg .= "User Agent: ".$_SERVER['HTTP_USER_AGENT']."\n";
		$msg .= "HTTP Host: ".$_SERVER['HTTP_HOST']."\n";
		$msg .= "Request URI: ".$_SERVER['REQUEST_URI']." (".$_SERVER['REQUEST_METHOD'].")\n";
		$msg .= "HTTP Referer: ".$_SERVER['HTTP_REFERER']."\n\n";
		$msg .= $data;
		
		return self::email($title, $msg);
	}
	
	/**
	 * Hent ut stack til exception
	 */
	public static function exception_format_stack($exception, $html = true, $show_parameters = true)
	{
		$data_pre = @ob_get_contents();
		@ob_clean();
		@ob_start();
		
		$trace = $exception->getTrace();
		$trace[] = "main";
		$trace = array_reverse($trace);
		$text = '';
		
		foreach ($trace as $row)
		{
			if (is_string($row))
			{
				$str = $html ? '<b>'.$row.'</b>' : $row;
			}
			else
			{
				if ($html)
				{
					$row['file'] = htmlspecialchars($row['file']);
					if (isset($row['class']))
					{
						$row['class'] = htmlspecialchars($row['class']);
						$row['type'] = htmlspecialchars($row['type']);
					}
					$row['function'] = htmlspecialchars($row['function']);
				}
				
				$fileinfo = ' ('.$row['file'].':'.$row['line'].')';
				
				$str = '';
				
				// class
				if (isset($row['class'])) $str .= ($html ? '<b>' : '').$row['class'].($html ? '</b>' : '').$row['type'];
				
				$str .= $html ? '<b>'.$row['function'].'</b>' : $row['function'];
				
				if (isset($row['args']) && count($row['args']) > 0)
				{
					var_dump($row['args']);
					$args = ob_get_contents();
					if ($html) $args = htmlspecialchars($args);
					@ob_clean();
					
					$str .= '(parametere)'.$fileinfo;
					
					if ($show_parameters)
					{
						if ($html)
						{
							$str .= '<pre style="margin-left: 20px">parametere:<br />'.$args.'</pre>';
						}
						
						else
						{
							$str .= "\n\t\tparametere:\n".preg_replace("/^/m", "\t\t", preg_replace("/[\r\n]+$/D", "", $args));
						}
					}
				} else { $str .= '()' . $fileinfo; }
			}
			
			if ($html)
			{
				$text .= '
	<li>'.$str.'</li>';
			}
			else
			{
				$text .= "\t".$str."\n";
			}
		}
		
		if ($html)
		{
			$text = '<ul>'.$text."\n</ul>";
		}
		
		echo $data_pre;
		return $text;
	}
	
	/**
	 * Logge caught exception
	 */
	public static function exception_caught($exception, $subtitle = "")
	{
		$stack_text = self::exception_format_stack($exception, false, true);
		
		$text = '
Fanget unntak '.get_class($exception).' med melding '.$exception->getMessage().'.
Kastet i '.$exception->getFile().' på linje '.$exception->getLine().'

Stabel:

'.$stack_text;
		
		self::log($text, "Kofradia Fanget Exception".($subtitle == "" ?  "" : " [$subtitle]"));
	}
	
	/**
	 * Exceptions handler
	 */
	public static function exception_handler(Exception $exception)
	{
		@ob_clean();
		
		// hent stack
		$stack_text = self::exception_format_stack($exception, false, true);
		
		// filtrer bort evt. databaseinfo
		$html = '
<p><b>Fatal feil:</b> Uoppfanget unntak &laquo;'.htmlspecialchars(get_class($exception)).'&raquo;.</p>
<p><b>Kastet i</b> '.htmlspecialchars($exception->getFile()).' <b>på linje</b> '.$exception->getLine().'.</p>
<div style="margin: 1em 0; border: 2px solid #BBB; padding: 5px; background-color: #EEE"><b>Melding:</b> <pre>'.htmlspecialchars($exception->getMessage()).'</pre></div>';
		
		if (!MAIN_SERVER)
		{
			$stack_html = self::exception_format_stack($exception, true, ($exception->getCode() != -1));
			
			// filtrer enkelte verdier
			$user = htmlspecialchars(DBUSER);
			$pass = htmlspecialchars(DBPASS);
			$stack_html = str_replace(array($user, $pass), "*filtrert*", $stack_html);
			
			$html .= '
<p><b>Stabel:</b></p>
'.$stack_html.'
<p>_GET:</p>
<pre>'.htmlspecialchars(print_r($_GET, true)).'</pre>
<p>_POST:</p>
<pre>'.htmlspecialchars(print_r($_POST, true)).'</pre>
<p>_COOKIE:</p>
<pre>'.htmlspecialchars(print_r($_COOKIE, true)).'</pre>'.(isset($_SESSION) ? '
<p>_SESSION:</p>
<pre>'.htmlspecialchars(print_r($_SESSION, true)).'</pre>' : '');
		}
		
		$text = 'Fatal feil: Uoppfanget unntak '.get_class($exception).'.
Kastet i '.$exception->getFile().' på linje '.$exception->getLine().'

Melding: '.$exception->getMessage().'

Stabel:

'.$stack_text.'

_GET:

'.print_r($_GET, true).'

_POST:

'.print_r($_POST, true).'

_COOKIE:

'.print_r($_COOKIE, true).(isset($_SESSION) ? '

_SESSION:

'.$_SESSION : '');
		
		// deadlock? hent innodb status
		if (is_a($exception, "SQLQueryException") && $exception->getSQLErrorNum() == 1205)
		{
			$result = @mysql_query("SHOW ENGINE INNODB STATUS", ess::$b->db->link);
			if ($result)
			{
				$text .= '

INNODB STATUS:

'.mysql_result($result, 0, "Status");
				
				if (!MAIN_SERVER)
				{
					$html .= '
<p>InnoDB status:</p>
<pre>'.htmlspecialchars(mysql_result($result, 0, "Status")).'</pre>';
				}
			}
		}
		
		// send e-post og logg
		if (!self::log($text, "Kofradia Exception"))
		{
			// lagre til errorlog dersom e-posten ikke ble sendt
			error_log('('.$_SERVER['REMOTE_ADDR'].') '.$text);
		}
		
		header("HTTP/1.0 503 Service Unavailiable");
		echo self::html_template('En feil har oppstått', '
<h1>En feil har oppstått</h1>'.$html.'
<p>Feilen er automatisk rapportert.</p>');
		
		die;
	}
	
	/**
	 * HTML tempate
	 */
	public static function html_template($title, $data, $head = '')
	{
		return '<!DOCTYPE html>
<html lang="no">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1" />
<meta name="author" content="Henrik Steen; http://www.henrist.net" />
<title>'.$title.'</title>
<style>
<!--
body { font-family: tahoma; font-size: 14px; }
h1 { font-size: 23px; }
.hsws { color: #CCCCCC; font-size: 12px; }
.subtitle { font-size: 16px; font-weight: bold; }
-->
</style>'.$head.'
</head>
<body>'.$data.'
<p class="hsws"><a href="http://hsw.no/">hsw.no</a></p>
</body>
</html>';
	}
	
	/**
	 * Sende enkel e-post til Henrik
	 */
	public static function email($title, $data)
	{
		$to = "Henrik Steen <henrist@henrist.net>";
		
		$email = new email();
		$email->text($data);
		return $email->send($to, $title);
	}
}