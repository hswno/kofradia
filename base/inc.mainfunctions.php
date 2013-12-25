<?php

// sett autoloader
spl_autoload_register(array("essentials", "load_module"));

/**
 * Dumpe en verdi
 */
function dump($value)
{
	header("Content-Type: text/plain");
	var_dump($value);
	die;
}

/**
 * for å starte _SESSION og evt. bruke egen verdi
 * kjøres kun om session ikke allerede er startet
 */
function sess_start($value = false)
{
	if (session_id() != "") return false;
	
	if (!empty($value) && (!isset($_COOKIE[session_name()]) || $value != $_COOKIE[session_name()]))
	{
		session_id($value);
	}
	
	ess::$b->dt("sess_start_pre");
	
	// sett slik at __autoload behandler mulige objekt pent
	$GLOBALS['load_module_ignore'] = true;
	@session_start();
	unset($GLOBALS['load_module_ignore']);
	
	ess::$b->dt("sess_start_post");
	return true;
}


/** Lagre til logg systemet */
function putlog($area, $msg)
{
	global $_base;
	
	static $b = "";
	static $c = "";
	static $u = "";
	
	static $locations = array(
		"LOG" => array("#SMLogs", "SMAFIA"),
		"SPAM" => array("#SMLogs", "SMAFIA"), #, "#SMSpam", "QuakeNet"),
		"INFO" => array("#SMLogs", "SMAFIA", "#kofradia", "QuakeNet"),
		"SPAMLOG" => array("#SMLogs", "SMAFIA"),
		"ANTIBOT" => array("#SMLogs", "SMAFIA"),
		"SUPERLOG" => array("#SMLogs", "SMAFIA"),
		"PROFILVIS" => array("#SMLogs", "SMAFIA"),
		"CREWCHAN" => array("#SMLogs", "SMAFIA", "#opers", "SMAFIA"),
		"ANTIBOT_ERROR" => array("#opers", "SMAFIA"),
		"FF" => array("#SMLogs", "SMAFIA", "#FF", "SMAFIA"),
		"ABUSE" => array("#SMLogs", "SMAFIA", "#SMAbuse", "SMAFIA"),
		"NOTICE" => array("#SMLogs", "SMAFIA", "#SMNotice", "SMAFIA"),
		"DF" => array("#SMDF", "SMAFIA")
	);
	
	// bytt ut juksetegnene med spesialtegn
	$msg = str_replace(array("%b", "%c", "%u", "\r", "\n"), array($b, $c, $u, "", ""), $msg);
	
	$file = $area == "INT" ? LOGFILE_INT : LOGFILE;
	if ($area == "LOG")
	{
		$sid = 0;
		if (login::$logged_in) $sid = login::$info['ses_id'];
		elseif (isset($_SESSION[$GLOBALS['__server']['session_prefix'].'login_info']['ses_id'])) $sid = $_SESSION[$GLOBALS['__server']['session_prefix'].'login_info']['ses_id'];
		$msg .= " {$c}15(SID: ".login::$info['ses_id'].")";
	}
	
	// skriv til databasen og
	if ($area != "INT")
	{
		global $_base;
		
		if (isset($locations[$area]))
		{
			for ($i = 0; $i < count($locations[$area]); $i += 2)
			{
				$chan = $locations[$area][$i];
				$net = $locations[$area][$i+1];
				
				$_base->db->query("INSERT INTO log_irc SET li_network = ".$_base->db->quote($net).", li_channel = ".$_base->db->quote($chan).", li_time = ".time().", li_message = ".$_base->db->quote($msg));
			}
		}
		
		else
		{
			$err = "UKJENT($area): ";
			$_base->db->query("INSERT INTO log_irc SET li_time = ".time().", li_message = ".$_base->db->quote($err.$msg));
		}
	}
	
	// fjern IRC-tegn fra loggmeldingen som legges i tekstloggen
	$text = str_replace(array($b, $u), array("", "'"), preg_replace("/$c(\\d{1,2}(,\\d{1,2})?)?/", "", $msg));
	
	// vis informasjon til konsoll for scheduler bakgrunn-scriptet
	if (defined("SCHEDULER_REPEATING"))
	{
		$t = microtime(true);
		$m = sprintf("%02d", round(($t - (int) $t)*100));
		echo ess::$b->date->get((int)$t)->format("H:i:s.").$m.": ".$text."\n";
	}
	
	// finnes ikke loggfilen?
	if (!file_exists($file))
	{
		$fh = fopen($file, "a");
		if (!$fh) throw new HSException("error (putlog_mf)", sysreport::EXCEPTION_ANONYMOUS);
		fwrite($fh, "\r\n".$_base->date->get()->format("d-m-Y H:i:s ")."$area: $text");
		fclose($fh);
		
		chmod($file, 0777);
	}
	
	// legg til i allerede eksisterende loggfil
	else
	{
		$fh = fopen($file, "a");
		if (!$fh) throw new HSException("error (putlog_mf)", sysreport::EXCEPTION_ANONYMOUS);
		fwrite($fh, "\r\n".$_base->date->get()->format("d-m-Y H:i:s ")."$area: $text");
		fclose($fh);
	}
}

function safe_val($input)
{
	return str_replace(array('$', "\r", "\n", "\t"), array('\$', '\r', '\n', '\t'), addslashes($input));
}

function get_val($input)
{
	if (is_bool($input))
	{
		return $input ? 'true' : 'false';
	}
	
	if (is_numeric($input))
	{
		return $input;
	}
	
	if (is_null($input))
	{
		return "NULL";
	}
	
	return '"'.safe_val($input).'"';
}

// fiks lange ord
/* (kun benyttet for sms)
function fix_words($data, $max_length = 12)
{
	// sett mellomrom mellom ord som har lengre lengde
	$result = false;
	if (preg_match_all("/(\\S{{$max_length},})/", $data, $result))
	{
		foreach ($result[1] as $row)
		{
			$data = str_replace($row, chunk_split($row, $max_length, "<wbr />"), $data);
		}
	}

	return $data;
}*/

/**
 * Hent ut en bestemt verdi fra en array hvis den finnes
 * @param $array
 * @param $item_name
 * @param $default
 */
function arrayval(&$array, $item_name, $default = NULL)
{
	if (!isset($array[$item_name])) return $default;
	return $array[$item_name];
}

function postval($name, $default = "")
{
	if (!isset($_POST[$name])) return $default;
	return $_POST[$name];
}

function getval($name, $default = "")
{
	if (!isset($_GET[$name])) return $default;
	return $_GET[$name];
}

function requestval($name, $default = "")
{
	if (!isset($_REQUEST[$name])) return $default;
	return $_REQUEST[$name];
}

// fikse negative verdier
function to_float($int)
{
	if ($int < 0)
	{
		return 4294967296 + $int;
	}
	
	return $int;
}

function show_button($text, $attr = '', $class = '')
{
	return '<input type="button" value="'.htmlspecialchars($text).'" class="button'.($class != '' ? ' '.$class : '').'" onmouseover="this.className=\'button_hover\'" onmouseout="this.className=\'button\'"'.(!empty($attr) ? ' ' . $attr : '').' />';
}
function show_sbutton($text, $attr = '', $class = '')
{
	return '<input type="submit" value="'.htmlspecialchars($text).'" class="button'.($class != '' ? ' '.$class : '').'" onmouseover="this.className=\'button_hover\'" onmouseout="this.className=\'button\'"'.(!empty($attr) ? ' ' . $attr : '').' />';
}

/**
 * Funksjon for å kontrollere SID
 * @param bool $redirect redirect hvis ugyldig
 */
function validate_sid($redirect = true)
{
	global $_base;
	if (!login::$logged_in || ((!isset($_POST['sid']) || $_POST['sid'] != login::$info['ses_id']) && (!isset($_GET['sid']) || $_GET['sid'] != login::$info['ses_id'])))
	{
		$_base->page->add_message("Ugyldig forespørsel.", "error");
		if ($redirect) redirect::handle();
		return false;
	}
	return true;
}

// søkefunksjoner
function search_query($input, $regexp = true)
{
	// hent ut hele søke ord (de som er med anførelsestegn rundt)
	$matches = false;
	preg_match_all('/(?:^|\s)"([^"]+)"(?:$|\s)/', $input, $matches, PREG_PATTERN_ORDER);
	
	// sett sammen søkeordene (sett sammen anførselstegn ordene og de vanlige ordene)
	$search = array_merge($matches[1], explode(" ", preg_replace('/(?:^|\s)"([^"]+)"(?:$|\s)/', " ", $input)));
	
	// gå gjennom søkeordene og fjern unødvendige ting
	foreach ($search as $key => $value)
	{
		$search[$key] = trim($value);
		
		if (!isset($search[$key]) || $search[$key] == "")
		{
			unset($search[$key]);
		}
	}
	$search = array_unique($search);
	
	// sett opp selve sjekken
	$parts = array();
	foreach ($search as $key => $value)
	{
		$parts[$key] = $regexp
			? " REGEXP '[[:<:]]" . addcslashes(preg_replace(array('/([\[\]()$.+?|{}])/', '/\*/'), array('[$1]', '.+'), mysql_real_escape_string($value)), '') . "[[:>:]]'"
			: " LIKE '%" . strtr(mysql_real_escape_string($value), array('_' => '\\_', '%' => '\\%')) . "%'";
	}
	
	return array($parts, $search);
}

/**
 * Rette på HTML før output
 * Støtter en array med tekst
 * @param array $content
 * @return array
 */
function parse_html_array($content)
{
	// generer unik string som kan brukes som seperator
	$seperator = ":seperator:".uniqid().":";
	
	// hent ut nøklene
	$keys = array_keys($content);
	
	// fiks html
	$content = explode($seperator, parse_html(implode($seperator, $content)));
	
	// legg til nøklene igjen og returner
	return array_combine($keys, $content);
}

/**
 * Rette på HTML før output.
 * 
 * @param string $content
 * @return string
 */
function parse_html($content)
{
	global $__server;
	
	// fikse noen <user="" /> ?
	$matches = false;
	if (preg_match_all("/(<user=\"([0-9a-zA-Z\\-_ ]+)\"( nolink)? \\/>|<user id=\"([0-9]+)\"( nolink)? \\/>)/", $content, $matches))
	{
		$users = array();
		$ids = array();
		
		// sett opp brukernavn liste
		foreach ($matches[2] as $user)
		{
			if (!empty($user))
			{
				if (!in_array($user, $users)) $users[] = $user;
			}
		}
		
		// sett opp ID liste
		foreach ($matches[4] as $id)
		{
			if (!empty($id))
			{
				if (!in_array($id, $ids)) $ids[] = $id;
			}
		}
		
		// fant gyldige treff
		if (count($users) > 0 || count($ids) > 0)
		{
			global $_base;
			$q = array();
			
			// brukernavn
			if (count($users) > 0)
			{
				$q[] = "
					SELECT up_id, up_name, up_access_level FROM (
						SELECT up_id, up_name, up_access_level
						FROM users_players
						WHERE up_name IN (".implode(",", array_map(array($_base->db, "quote"), $users)).")
						ORDER BY up_access_level = 0, up_last_online DESC
					) ref
					GROUP BY up_name";
			}
			
			// id
			if (count($ids) > 0)
			{
				$q[] = "SELECT up_id, up_name, up_access_level FROM users_players WHERE up_id IN (".implode(",", array_unique(array_map("intval", $ids))).")";
			}
			
			// hent info og bytt om
			$result = $_base->db->query(implode(" UNION ", $q));
			while ($row = mysql_fetch_assoc($result))
			{
				$content = preg_replace('/(<user="'.preg_quote($row['up_name'], "/").'" \/>|<user id="'.$row['up_id'].'" \/>)/i', game::profile_link($row['up_id'], $row['up_name'], $row['up_access_level']), $content);
				$content = preg_replace('/(<user="'.preg_quote($row['up_name'], "/").'" nolink \/>|<user id="'.$row['up_id'].'" nolink \/>)/i', game::profile_link($row['up_id'], $row['up_name'], $row['up_access_level'], false), $content);
			}
		}
		
		// ordne de som ikke ble funnet
		$content = preg_replace('~<user="([0-9a-zA-Z\-_ ]+)"( nolink)? />~i', '<a href="'.$__server['relative_path'].'/finn_spiller?finn=$1">$1 (ukjent spiller)</a>', $content);
		$content = preg_replace('~<user id="([0-9]+)"( nolink)? />~i', '<a href="'.$__server['relative_path'].'/finn_spiller">#$1 (ukjent spiller)</a>', $content);
	}
	
	// fikse noen <ff_link>id</ff_link> ?
	$matches = false;
	if (preg_match_all("~(<ff_link>([0-9]+)</ff_link>)~", $content, $matches))
	{
		$ids = array();
		
		// sett opp ID liste
		foreach ($matches[2] as $id)
		{
			if (!in_array($id, $ids)) $ids[] = (int) $id;
		}
		
		// fant gyldige treff
		if (count($ids) > 0)
		{
			// hent info og bytt om
			$result = ess::$b->db->query("SELECT ff_id, ff_name, ff_inactive FROM ff WHERE ff_id IN (".implode(",", $ids).")");
			while ($row = mysql_fetch_assoc($result))
			{
				$link = $row['ff_inactive'] && !access::has("mod")
					? htmlspecialchars($row['ff_name'])
					: '<a href="'.ess::$s['relative_path'].'/ff/?ff_id='.$row['ff_id'].'">'.htmlspecialchars($row['ff_name']).'</a>';
				
				$content = preg_replace('~(<ff_link>'.$row['ff_id'].'</ff_link>)~', $link, $content);
			}
		}
		
		// ordne de som ikke ble funnet
		$content = preg_replace('~<ff_link>([0-9]+)</ff_link>~', '<span class="ff_unknown">ukjent firma/broderskap (#$1)</span>', $content);
	}
	
	// fiks entities
	$content = str_replace(array(
			"&rpath;",
			"&spath;",
			"&path;",
			"&staticlink;"
		),
		array(
			ess::$s['rpath'],
			ess::$s['spath'],
			ess::$s['path'],
			STATIC_LINK
		),
		$content);
	
	return $content;
}


/**
 * Lager sidetall linker. Best egnet til Javascript linker.
 * Kan også brukes til <input> linker ved å sende "input" som $page_1 og navnet på <input> som $page_x.
 *
 * @param string $page_1 (IKKE html safe)
 * @param string $page_x (IKKE html safe) (bruk &lt;page&gt; eller _pageid_)
 * @param int $pages
 * @param int $page
 * @return string
 */
function pagenumbers($page_1, $page_x, $pages, $page)
{
	$pn = $page_1 == "input"
		? new pagenumbers_input($page_x, $pages, $page)
		: new pagenumbers($page_1, str_replace("<page>", "_pageid_", $page_x), $pages, $page);
	return $pn->build();
}

/**
 * Krever HTTPS tilkobling (redirect hvis ikke)
 * 
 * @param boolean $mode (true for ja, false for ikke)
 */
function force_https($mode = true)
{
	if (defined("FORCE_SSL_ALL") && FORCE_SSL_ALL === true) return;

	// hvis login-systemet krever ssl kan vi ikke fravike det
	if (defined("LOGIN_FORCE_SSL")) $mode = true;
	
	// skal være https - er ikke
	if ($mode && !HTTPS)
	{
		// endre til https hvis serveren støtter det
		global $__server;
		if ($__server['https_support'])
		{
			redirect::handle("https://".$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'], redirect::ABSOLUTE);
		}
	}
	
	// skal ikke være https - er https
	elseif (!$mode && HTTPS)
	{
		// endre til http
		redirect::handle("http://".$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'], redirect::ABSOLUTE);
	}
}

/**
 * Formatter data så det kan brukes i JavaScript variabler osv
 * Ikke UTF-8 (slik som json_encode)
 * 
 * @param string $value
 */
function js_encode($value)
{
	if (is_null($value)) return 'null';
	if ($value === false) return 'false';
	if ($value === true) return 'true';
	if (is_scalar($value))
	{
		if (is_string($value))
		{
			static $json_replace_from = array(
				"\\",
				'"',
				"/",
				"\x8",
				"\xC",
				"\n",
				"\r",
				"\t"
			);
			static $json_replace_to = array(
				"\\\\",
				'\\"',
				"\\/",
				"\\b",
				"\\f",
				"\\n",
				"\\r",
				"\\t"
			);
			
			return '"'.str_replace($json_replace_from, $json_replace_to, $value).'"';
		}
		
		return $value;
	}
	
	if (!is_array($value) && !is_object($value)) return false;
	
	$object = false;
	for ($i = 0, reset($value), $len = count($value); $i < $len; $i++, next($value))
	{
		if (key($value) !== $i)
		{
			$object = true;
			break;
		}
	}
	
	$result = array();
	if ($object)
	{
		foreach ($value as $k => $v) $result[] = js_encode($k).':'.js_encode($v);
		return '{'.implode(",", $result).'}';
	}
	
	foreach ($value as $v) $result[] = js_encode($v);
	return '['.implode(",", $result).']';
}

/**
 * Formattere ord (flertallsendinger)
 *
 * @param mixed $single
 * @param mixed $multiple
 * @param int $num
 * @return mixed
 */
function fword($single, $multiple, $num)
{
	return $num == 1 ? $single : $multiple;
}

/**
 * Formattere ord (flertalls-endinger) gjennom sprintf
 * @param string $single
 * @param string $multiple
 * @param int $num
 * @return string
 */
function fwords($single, $multiple, $num)
{
	return sprintf($num == 1 ? $single : $multiple, $num);
}

/**
 * Sett opp en setning basert på en liste (med komma og "og")
 */
function sentences_list($sentences, $combine = ", ", $combine_last = " og ")
{
	$last = array_pop($sentences);
	if (count($sentences) == 0) return $last;
	
	return implode($combine, $sentences).$combine_last.$last;
}

/**
 * Kontroller datoformat
 * @param string input string
 * @param string format
 * @return mixed matches
 */
function check_date($input, $format = "%d\\.%m\\.%y %h:%i:%s")
{
	static $replaces = array(
		"%d2" => "(0[1-9]|[1-2][0-9]|3[0-1])",
		"%m2" => "(0[1-9]|1[0-2])",
		"%y2" => "([0-1][0-9])",
		"%y4" => "(20[0-1][0-9])",
		"%h2" => "([0-1][0-9]|2[0-3])",
		"%i2" => "([0-5][0-9])",
		"%s2" => "([0-5][0-9])",
		"%d" => "(0?[1-9]|[1-2][0-9]|3[0-1])",
		"%m" => "(0?[1-9]|1[0-2])",
		"%y" => "((?:20)?[0-1][0-9])",
		"%h" => "([0-1]?[0-9]|2[0-3])",
		"%i" => "([0-5]?[0-9])",
		"%s" => "([0-5]?[0-9])"
	);
	static $replaces_from = false;
	static $replaces_to = false;
	if (!$replaces_from)
	{
		$replaces_from = array_keys($replaces);
		$replaces_to = array_values($replaces);
	}
	
	$format = str_replace($replaces_from, $replaces_to, $format);
	
	$matches = false;
	preg_match("/^".str_replace("/", "\\/", $format)."$/U", $input, $matches);
	
	return $matches;
}

/**
 * Ugyldig side (404)
 */
function page_not_found($more_info = NULL)
{
	$more_info = empty($more_info) ? '' : $more_info;
	
	// siden finnes ikke (404)
	header("HTTP/1.1 404 Not Found");
	
	// har vi hentet inn page?
	if (isset(ess::$b->page))
	{
		ess::$b->page->add_title("404 Not Found");
		
		echo '
<h1>404 Not found</h1>
<p>Siden du ba om finnes ikke.</p>
<dl class="dl_50px">
	<dt>Adresse</dt>
	<dd>'.htmlspecialchars($_SERVER['REQUEST_URI']).'</dd>
</dl>'.$more_info;
		
		ess::$b->page->load();
	}
	
	// sett opp html etc
	echo '<!DOCTYPE html>
<html lang="no">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1" />
<meta name="author" content="Henrik Steen; http://www.henrist.net" />
<title>404 Not Found</title>
<style>
<!--
body { font-family: tahoma; font-size: 14px; }
h1 { font-size: 23px; }
.hsws { color: #CCCCCC; font-size: 12px; }
.subtitle { font-size: 16px; font-weight: bold; }
-->
</style>
</head>
<body>
<h1>404 Not Found</h1>
<p>Siden du ba om finnes ikke.</p>
<dl>
	<dt>Adresse</dt>
	<dd>'.htmlspecialchars($_SERVER['REQUEST_URI']).'</dd>
</dl>'.$more_info.'
<p class="hsws"><a href="http://www.henrist.net">HenriSt Websystem</a></p>
</body>
</html>';
	
	die;
}

/**
 * Sjekke for mobil
 */
function is_mobile()
{
	static $checked = null;
	if ($checked !== null) return $checked;
	
	// kode hentet fra http://detectmobilebrowser.com/
	$useragent = $_SERVER['HTTP_USER_AGENT'];
	$checked = preg_match('/android|avantgo|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|symbian|treo|up\.(browser|link)|vodafone|wap|windows (ce|phone)|xda|xiino/i', $useragent)
	    || preg_match('/1207|6310|6590|3gso|4thp|50[1-6]i|770s|802s|a wa|abac|ac(er|oo|s\-)|ai(ko|rn)|al(av|ca|co)|amoi|an(ex|ny|yw)|aptu|ar(ch|go)|as(te|us)|attw|au(di|\-m|r |s )|avan|be(ck|ll|nq)|bi(lb|rd)|bl(ac|az)|br(e|v)w|bumb|bw\-(n|u)|c55\/|capi|ccwa|cdm\-|cell|chtm|cldc|cmd\-|co(mp|nd)|craw|da(it|ll|ng)|dbte|dc\-s|devi|dica|dmob|do(c|p)o|ds(12|\-d)|el(49|ai)|em(l2|ul)|er(ic|k0)|esl8|ez([4-7]0|os|wa|ze)|fetc|fly(\-|_)|g1 u|g560|gene|gf\-5|g\-mo|go(\.w|od)|gr(ad|un)|haie|hcit|hd\-(m|p|t)|hei\-|hi(pt|ta)|hp( i|ip)|hs\-c|ht(c(\-| |_|a|g|p|s|t)|tp)|hu(aw|tc)|i\-(20|go|ma)|i230|iac( |\-|\/)|ibro|idea|ig01|ikom|im1k|inno|ipaq|iris|ja(t|v)a|jbro|jemu|jigs|kddi|keji|kgt( |\/)|klon|kpt |kwc\-|kyo(c|k)|le(no|xi)|lg( g|\/(k|l|u)|50|54|e\-|e\/|\-[a-w])|libw|lynx|m1\-w|m3ga|m50\/|ma(te|ui|xo)|mc(01|21|ca)|m\-cr|me(di|rc|ri)|mi(o8|oa|ts)|mmef|mo(01|02|bi|de|do|t(\-| |o|v)|zz)|mt(50|p1|v )|mwbp|mywa|n10[0-2]|n20[2-3]|n30(0|2)|n50(0|2|5)|n7(0(0|1)|10)|ne((c|m)\-|on|tf|wf|wg|wt)|nok(6|i)|nzph|o2im|op(ti|wv)|oran|owg1|p800|pan(a|d|t)|pdxg|pg(13|\-([1-8]|c))|phil|pire|pl(ay|uc)|pn\-2|po(ck|rt|se)|prox|psio|pt\-g|qa\-a|qc(07|12|21|32|60|\-[2-7]|i\-)|qtek|r380|r600|raks|rim9|ro(ve|zo)|s55\/|sa(ge|ma|mm|ms|ny|va)|sc(01|h\-|oo|p\-)|sdk\/|se(c(\-|0|1)|47|mc|nd|ri)|sgh\-|shar|sie(\-|m)|sk\-0|sl(45|id)|sm(al|ar|b3|it|t5)|so(ft|ny)|sp(01|h\-|v\-|v )|sy(01|mb)|t2(18|50)|t6(00|10|18)|ta(gt|lk)|tcl\-|tdg\-|tel(i|m)|tim\-|t\-mo|to(pl|sh)|ts(70|m\-|m3|m5)|tx\-9|up(\.b|g1|si)|utst|v400|v750|veri|vi(rg|te)|vk(40|5[0-3]|\-v)|vm40|voda|vulc|vx(52|53|60|61|70|80|81|83|85|98)|w3c(\-| )|webc|whit|wi(g |nc|nw)|wmlb|wonu|x700|xda(\-|2|g)|yas\-|your|zeto|zte\-/i', substr($useragent,0,4));
	
	return $checked;
}
