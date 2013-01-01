<?php

// sjekk parameter
if (count($_SERVER['argv']) == 1)
{
	die("Mangler parameter (nettverk: quakenet, smafia)\n");
}

$network = strtolower($_SERVER['argv'][1]);

global $irc_networks;

/*

dette må settes i lokale innstillinger:

global $irc_networks;
$irc_networks = array(
	"<NAVN>" => array(
		"settings" => array(
			"name" => "<nettverknavn>",
			"server" => "<serveradresse>",
			"port" => 6667,
			"pass" => false, // evt string passord
			"channels" => array(
				array("#kanal1"),
				array("#kanal2"),
				array("#kanal3medpassord", "passord"),
				...
			)
		),
		"clients" => array(
			array(
				"nick" => "<nick>",
				"user" => "<user>",
				"name" => "<realname>",
				"bind" => "<bind ip>",
				"tmp" => ""
			)
		),
		"loglimit" => 1,
		"logdelay" => 1.5,
		"allow_commands" => false
	),
	...
);

*/

require "../essentials.php";

if (!isset($irc_networks[$network]))
{
	die("Invalid network $network.\n");
}
$network = $irc_networks[$network];

// sørg for at det ikke er noen time limit
@set_time_limit(0);

class irc_info_bot extends irc_info
{
	/**
	 * @var hs_irc
	 */
	public $irc;
	public $end_node;
	
	public function __construct($obj)
	{
		$this->irc = $obj;
	}
	
	public function send_output($text)
	{
		$this->irc->msg($this->irc->connected_cid, $this->end_node, $text);
	}
}

// start bot
$irc = new hs_irc($network);

// irc greia
class hs_irc
{
	public $settings = array(
		/*"server" => "127.0.0.1",
		"port" => 9992,
		"pass" => "trust",
		"channels" => array(
			array("#StreetzMafia")
		)*/
	);
	public $sockets = array();
	public $sockets_c = array();
	public $clients = array(
		/*array(
			"nick" => "SMAnnounce2",
			"user" => "smafia",
			"name" => "StreetzMafia Announcer 2",
			"bind" => "83.143.87.204",
			"tmp" => ""
		)*/
	);
	
	public $timeout = 5;
	public $connect = true;
	public $select_delay = 250000;
	public $loglimit = 1;
	
	/** @var irc_info_bot */
	public $irc_info;
	
	public $next = 0;
	public $timers = array(
		// navn, delay, next, skip
		#"STATUS" => array("STATUS", 300, 0),
		"SOCKET_STATUS" => array("SOCKET_STATUS", 18, 0, 1),
		"CHECKLOG" => array("CHECKLOG", 1, 0, 4)
	);
	
	public $connected_cid = false;
	public $connected;
	//public $nick = "";
	
	// init
	function hs_irc($network)
	{
		$this->settings = $network['settings'];
		$this->clients = $network['clients'];
		$this->loglimit = $network['loglimit'];
		$this->timers['CHECKLOG'][1] = $network['logdelay'];
		
		if ($network['allow_commands']) $this->irc_info = new irc_info_bot($this);
		
		$this->settings['server'] = gethostbyname($this->settings['server']);
		#$this->timers["SOCKET_STATUS"][2] = microtime(true)+10;
		
		$next = array();
		foreach ($this->timers as $id => $timer)
		{
			$this->timers[$id][2] = microtime(true) + $timer[1] * $timer[3];
			$next[] = $this->timers[$id][2];
		}
		$this->next = min($next);
		
		foreach (array_keys($this->clients) as $cid)
		{
			$this->connect($cid);
			break;
		}
		
		$this->check_data();
		#$this->clear();
	}
	
	// tøm alt og sett det opp "riktig"
	function clear()
	{
		$this->socket = false;
		$this->motd = "";
		$this->nick = "";
		$this->channels = array();
	}
	
	// feilbehandling
	function handle_error($cid, $errstr)
	{
		if ($this->connected_cid == $cid)
		{
			$this->connected_cid = false;
			$this->connected = false;
			foreach (array_keys($this->sockets) as $scid)
			{
				if ($scid == $cid) continue;
				$this->connected_cid = $scid;
				$this->connected = true;
				break;
			}
		}
		
		@socket_close($this->sockets[$cid]);
		unset($this->sockets[$cid]);
		unset($this->sockets_c[$cid]);
		echo "Error (" . $cid . "): " . $errstr . "\n";
		//die("ERR: ".$errstr."\n");
	}
	
	// kritisk feil
	function critical_error($err)
	{
		foreach ($this->sockets as $cid => $socket)
		{
			$this->send_data($cid, "QUIT :Something went very wrong! :(");
			unset($this->sockets[$cid]);
			unset($this->sockets_c[$cid]);
			socket_close($socket);
		}
		
		$err = preg_replace("/[\r\n]/", "", $err);
		die($err."\n");
	}
	
	// koble til
	function connect($cid)
	{
		$ip = $this->settings['server'];
		
		// opprett socket
		$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
		if ($socket === false)
		{
			$this->handle_error($cid, socket_strerror(socket_last_error()));
		}
		
		socket_bind($socket, $this->clients[$cid]['bind']);
		
		// koble til
		$result = socket_connect($socket, $ip, $this->settings['port']);
		if ($result === false)
		{
			$this->handle_error($cid, socket_strerror(socket_last_error($socket)));
			return;
		}
		
		socket_set_nonblock($socket);
		$this->sockets[$cid] = $socket;
		$this->sockets_c[$socket] = $cid;
		
		// send info
		if ($this->settings['pass'])
		{
			$this->send_data($cid, "PASS {$this->settings['pass']}\n");
		}
		$this->send_data($cid, "USER {$this->clients[$cid]['user']} 2 3 :{$this->clients[$cid]['name']}\n");
		$this->send_data($cid, "NICK {$this->clients[$cid]['nick']}\n");
	}
	
	function debug($cid, $message)
	{
		$message = preg_replace("/[\r\n]/", "", $message);
		if ($cid !== NULL && $cid !== false)
		{
			echo "DEBUG ".date("r")." ($cid): " . $message . "\n";
		}
		else
		{
			echo "DEBUG ".date("r").": " . $message . "\n";
		}
		
		return true;
	}
	
	function check_timers()
	{
		global $_base;
		
		// kjøre timer?
		$time = microtime(true);
		if ($time > $this->next)
		{
			$this->debug(NULL, "Timertest..");
			$next = array();
			
			// sjekk hvilke timere som har delayed
			foreach ($this->timers as $id => $timer)
			{
				if ($time > $timer[2])
				{
					$this->timers[$id][2] = $time + $timer[1];
					$this->debug(NULL, "Timer: {$timer[0]}");
					
					// kjør timer
					switch ($timer[0])
					{
						case "STATUS":
							// har vi en aktiv kobling?
							if (!$this->connected)
							{
								$this->timers[$id][2] = $time + 0.5;
							}
							
							else
							{
								// hent antall pålogget
								$time = 300;
								
								$last = time()-$time;
								$result = $_base->db->query("SELECT COUNT(up_id) FROM users_players WHERE up_last_online >= $last");
								$ant = game::format_number(mysql_result($result, 0));
								mysql_free_result($result);
								
								$time = game::timespan($time, game::TIME_FULL | game::TIME_NOBOLD);
								$this->msg($this->connected_cid, "#kofradia", "%c3php-cron: %bAntall pålogget siste $time%b: %u$ant%u");
							}
							
						break;
						
						case "SOCKET_STATUS":
							
							// skal vi koble til eller fra?
							if ($this->connect)
							{
								// sjekk hvilke tilkoblinger som ikke er koblet til
								foreach (array_keys($this->clients) as $cid)
								{
									if (!isset($this->sockets[$cid]))
									{
										// ikke tilkoblet - forsøk å koble til
										$this->connect($cid);
										break;
									}
								}
							}
							
							else
							{
								foreach (array_keys($this->sockets) as $cid)
								{
									// koble fra
									$this->disconnect($cid);
									break;
								}
							}
							
						break;
						
						case "CHECKLOG":
							// ignorere?
							if (!$this->connected) break;
							
							$limit = $this->loglimit;
							
							// hent meldinger
							$network = $this->settings['name'] == "SMAFIA_BETA" ? '' : " AND li_network = ".$_base->db->quote($this->settings['name']);
							$result = $_base->db->query("SELECT li_network, li_channel, li_time, li_message FROM log_irc WHERE 1$network ORDER BY li_time LIMIT $limit", false);
							
							if (!$result)
							{
								// feilet
								break;
							}
							
							if (($num = mysql_num_rows($result)) == 0)
							{
								// ingen rader
								break;
							}
							
							// gå gjennom hver melding og legg til der dem skal
							while ($row = mysql_fetch_assoc($result))
							{
								$delay = time() - $row['li_time'];
								$delay = $delay > 1 ? ' %c3(logdelay: '.game::timespan($delay, game::TIME_NOBOLD).')' : '';
								$prefix = $this->settings['name'] == "QuakeNet" || ($this->settings['name'] == "SMAFIA" && $row['li_channel'] == "#opers") ? '%c3' : '';
								if ($this->settings['name'] == "SMAFIA_BETA")
								{
									if ($row['li_network'] == "QuakeNet")
									{
										$row['li_channel'] = "#opers";
										$prefix = "BETAQ -%c3 ";
									}
									elseif ($row['li_channel'] == "#opers")
									{
										$prefix = "BETA -%c3 ";
									}
									elseif ($row['li_channel'] == "#SMAbuse")
									{
										$prefix = "BETA - ";
									}
									elseif ($row['li_channel'] != "#SMDF")
									{
										$row['li_channel'] = "#SMBeta";
									}
								}
								$this->msg($this->connected_cid, $row['li_channel'], "$prefix{$row['li_message']}$delay");
							}
							
							mysql_free_result($result);
							
							// slett meldingene
							$limit = min($limit, $num);
							#$_base->db->query("UPDATE log_irc SET li_deleted = 1, li_deleted_time = ".time()." WHERE li_network = ".$_base->db->quote($this->settings['name'])." AND li_deleted = 0 ORDER BY li_time LIMIT $limit");
							
							// forsøk å slette meldingene 3 ganger
							for ($i = 0; $i < 3; $i++)
							{
								$val = $i == 2;
								
								if ($_base->db->query("DELETE FROM log_irc WHERE 1$network ORDER BY li_time LIMIT $limit", $val)) break;
								else sysreport::log("Feil ved sletting av log_irc rader: ".mysql_error($_base->db->link));
							}
						break;
						
						default:
							$this->debug(NULL, "Unknown timer handler: {$timer[0]}");
					}
				}
				
				$next[] = $this->timers[$id][2];
			}
			
			$this->next = min($next);
		}
	}
	
	// les for data
	function check_data()
	{
		while (true)
		{
			$this->check_timers();
			
			// ingen flere sockets?
			if (count($this->sockets) == 0)
			{
				$this->critical_error("No more sockets..");
			}
			
			$this->debug(NULL, "Waiting for data");
			$read = $this->sockets;
			$write = NULL;
			$except = NULL;
			
			$select = socket_select($read, $write, $except, 0, $this->select_delay);
			
			// ingenting å lese?
			if ($select === false)
			{
				$this->critical_error("socket_select returned false (Err: ".socket_strerror(socket_last_error()).")");
			}
			
			// ingen endringer?
			if ($select == 0)
			{
				$this->debug(NULL, "No data is changed.");
				continue;
			}
			
			$this->debug(NULL, "Data is changed (".$select.")");
			
			// les hver socket som er endret
			foreach ($read as $socket)
			{
				$cid = $this->sockets_c[$socket];
				$this->debug($cid, "READING DATA");
				
				// hent data
				$data = socket_read($socket, 1024, PHP_BINARY_READ);
				
				// frakoblet?
				if ($data === false)
				{
					$this->handle_error($cid, "Socket disonnected (".socket_strerror(socket_last_error()).")");
					continue;
				}
				
				// ikke noe data?
				if ($data == "")
				{
					$this->handle_error($cid, "Is socket disconnected? (".socket_strerror(socket_last_error()).")");
					continue;
				}
				
				// legg til i tmp
				$this->clients[$cid]['tmp'] .= $data;
				
				// gå gjennom hver linje utenom den siste (siden den mangler linjebrudd)
				while (($pos = strpos($this->clients[$cid]['tmp'], "\n")) !== false)
				{
					$line = substr($this->clients[$cid]['tmp'], 0, $pos);
					$this->clients[$cid]['tmp'] = substr($this->clients[$cid]['tmp'], $pos + 1);
					
					if ($line == "")
					{
						$this->debug($cid, "Empty line.");
						continue;
					}
					
					// behandle data
					$this->handle_data($cid, $line);
					if (!isset($this->clients[$cid])) break;
				}
			}
			
			// ingen flere sockets?
			if (count($this->sockets) == 0)
			{
				$this->critical_error("No more sockets..");
			}
		}
	}
	
	// hent ut info om brukeren
	function nickinfo($nick)
	{
		list($nick, $ident, $host) = preg_split("/[!@]/", $nick."!!");
		return array("nick" => $nick, "ident" => $ident, "host" => $host);
	}
	
	// behandle data
	function handle_data($cid, $line)
	{
		global $_base;
		
		$this->debug($cid, "Handling data: $line");
		if (!isset($this->sockets[$cid]))
		{
			$this->debug($cid, "Socket not found. Aborting data check.");
			return;
		}
		
		#$this->debug($cid, "Data received: " . $line);
		
		$matches = false;
		if (preg_match('/^:([^ ]+)\s+(.+?)\s+(.+?)(?:\s+:(.+?))?\r?$/s', $line, $matches))
		{
			$from = $matches[1];
			$type = $matches[2];
			
			$arg = $matches[3];
			$content = isset($matches[4]) ? $matches[4] : '';
			
			$user = $this->nickinfo($from);
			
			switch ($type)
			{
				// nick i bruk
				case "433":
					$this->handle_error($cid, "Nick is in use..");
				return;
				
				// motd innhold
				case "372":
					$this->debug($cid, "MOTD: " . $content);
				break;
				
				// motd mangler
				case "422":
					#$this->debug($cid, "MOTD mangler");
				case "376":
					$this->debug($cid, "MOTD avsluttet");
					
					// sette som den aktive?
					if (!$this->connected_cid)
					{
						$this->connected_cid = $cid;
					}
					
					// join kanaler
					foreach ($this->settings['channels'] as $chan)
					{
						$args = isset($chan[1]) ? " :" . $chan[1] : "";
						$this->send_data($cid, "JOIN {$chan[0]}$args\n");
					}
					
					// oper
					if ($this->settings['name'] == "SMAFIA" || $this->settings['name'] == "SMAFIA_BETA")
					{
						$this->send_data($cid, "OPER SMAFIA StreetzMafiaBoten\n");
					}
					
					// merk som klar for timers
					$this->connected = true;
				break;
				
				// invitasjon
				case "INVITE":
					$chan = explode(" ", $arg);
					if ($chan != "#StreetzMafia" && $chan != "#kofradia") break;
					$this->send_data($cid, "JOIN {$chan[1]}\n");
				break;
				
				// meldinger
				case "PRIVMSG":
				case "NOTICE":
					if ((($this->settings['name'] == "SMAFIA" || $this->settings['name'] == "SMAFIA_BETA") && $this->irc_info) || ($this->settings['name'] == "QuakeNet" && $user["nick"] == "henrist" && $user["ident"] == "henrik" && $user["host"] == "hsw.no"))
					{
						$match = false;
						$log = true;
						$break = false;
						
						if (substr($content, 0, 3) == ".r ")
						{
							$this->send_data($cid, "NOTICE {$user['nick']} :Sending raw data..\n");
							$this->send_data($cid, substr($content, 3)."\n");
							$break = true;
						}
						
						elseif ($content == ".vars")
						{
							$this->msg($cid, $arg, "Kofradia Announcer - Loglimit: {$this->loglimit} - Skiptime: ".game::format_number($this->select_delay/1000, 1)." ms - Log delay: ".game::format_number($this->timers["CHECKLOG"][1]*1000)." ms");
						}
						
						elseif ($content == ".queue")
						{
							$w = $this->settings['name'] == "SMAFIA_BETA" ? '1' : " li_network = ".$_base->db->quote($this->settings['name']);
							$result = $_base->db->query("SELECT COUNT(*) FROM log_irc WHERE $w");
							$ant = mysql_result($result, 0);
							mysql_free_result($result);
							
							$this->msg($cid, $arg, "Message queue: " . game::format_number($ant));
						}
						
						elseif (preg_match("/^\\.loglimit\\s(\\d+)$/", $content, $match))
						{
							$num = intval($match[1]);
							if ($num <= 0)
							{
								$msg = "Must be more than 0.";
							}
							elseif ($num > 1500)
							{
								$msg = "Must be less than or equal to 1500.";
							}
							else
							{
								$this->loglimit = $num;
								$msg = "Log limit set to $num.";
							}
							
							$this->msg($cid, $arg, $msg);
						}
						
						elseif (preg_match("/^\\.logdelay\\s(\\d+)$/", $content, $match))
						{
							$num = intval($match[1]);
							if ($num < 100)
							{
								$msg = "Must be more than or equal 100.";
							}
							else
							{
								$this->timers["CHECKLOG"][1] = $num/1000;
								$msg = "Log delay set to ".game::format_number($num/1000, 1)." sec.";
							}
							
							$this->msg($cid, $arg, $msg);
						}
						
						elseif ($content == ".logtimer")
						{
							$this->timers["CHECKLOG"][2] = 0;
							$this->next = 0;
							
							$msg = "Timer reset.";
							$this->msg($cid, $arg, $msg);
						}
						
						elseif (preg_match("/^\\.skiptime\\s(\\d+)$/", $content, $match))
						{
							$num = intval($match[1]);
							if ($num < 5)
							{
								$msg = "Must be more than or equal to 5.";
							}
							elseif ($num > 60000)
							{
								$msg = "Must be less than or equal to 60000.";
							}
							else
							{
								$this->select_delay = $num*1000;
								$msg = "Skiptime set to $num.";
							}
							
							$this->msg($cid, $arg, $msg);
						}
						
						elseif (preg_match("/^\\.s(\\s|$)/", $content) && ($arg{0} != "#" || $this->connected_cid == $cid))
						{
							if (strstr($content, " ") == " disconnect")
							{
								$this->connect = false;
								$this->msg($cid, $arg, "State set to disconnect.");
							}
							else
							{
								$this->connect = true;
								$this->msg($cid, $arg, "State set to connect.");
							}
						}
						
						elseif (preg_match("/^\\.addbot\\s(.+)$/", $content, $match) && ($arg{0} != "#" || $this->connected_cid == $cid))
						{
							$info = explode(" ", $match[1], 4);
							if (count($info) == 4)
							{
								$this->clients[] = array(
									"nick" => $info[1],
									"user" => $info[2],
									"name" => $info[3],
									"bind" => $info[0],
									"tmp" => ""
								);
								
								end($this->clients);
								$key = key($this->clients);
								$this->msg($cid, $arg, "New bot added to list. (#$key)");
							}
							else
							{
								$this->msg($cid, $arg, "Wrong parameter count.");
							}
						}
						
						elseif (preg_match("/^\\.disconnect (\\d+)$/", $content, $match))
						{
							if ($cid == $match[1])
							{
								$this->msg($cid, $arg, ":(");
								$this->debug($cid, "Disconnect command applied.");
								$this->disconnect($cid);
								$break = true;
							}
						}
						
						elseif (preg_match("/^\\.disconnect$/", $content))
						{
							$this->debug($cid, "Disconnect command applied.");
							$this->disconnect($cid);
							$break = true;
						}
						
						elseif (preg_match("/^\\.die$/", $content))
						{
							$this->debug($cid, "Die!!!");
							unset($this->clients[$cid]);
							$this->msg($cid, $arg, "Bye, bye..");
							$this->disconnect($cid, "Killed in action!");
							$break = true;
						}
						
						elseif (preg_match("/^\\.die (\\d+)$/", $content, $match))
						{
							if (isset($this->clients[$match[1]]))
							{
								if ($cid != $match[1])
								{
									$this->msg($cid, $arg, "Lets kill! heaheaha");
								}
								else
								{
									$this->msg($cid, $arg, "Bye, bye.. :(");
								}
								
								$this->debug($match[1], "Die!!!");
								unset($this->clients[$match[1]]);
								if (isset($this->sockets[$match[1]])) $this->disconnect($match[1], "Killed in action!");
								if ($cid == $match[1]) $break = true;
							}
							
							else
							{
								#$this->msg($cid, $arg, "I don't know that person....?");
							}
						}
						
						elseif (substr($content, 0, 5) == ".sql " && ($arg{0} != "#" || $this->connected_cid == $cid))
						{
							$query = substr($content, 5);
							if (strpos($query, ";") !== false || substr($query, 0, 7) != "SELECT ")
							{
								$this->send_data($cid, "PRIVMSG $arg :Invalid query..\n");
							}
							else
							{
								//$arg = "mysql --user=".escapeshellarg(DBUSER)." --pass=".escapeshellarg(DBPASS)." ".escapeshellarg(DBNAME)." --execute=".escapeshellarg($query);
								//$val = shell_exec($arg);
								
								$this->send_data($cid, "PRIVMSG $arg :Executing query..\n");
								$result = $_base->db->query($query, false);
								
								if (!$result)
								{
									$this->send_data($cid, "PRIVMSG $arg :Query failed: ".mysql_error()."\n");
								}
								else
								{
									// list opp feltene
									$fields = array();
									while ($field = mysql_fetch_field($result)) $fields[] = $field->name;
									$this->send_data($cid, "PRIVMSG $arg :Fields: " . implode(", ", $fields)."\n");
									
									if (mysql_num_rows($result) == 0)
									{
										$this->send_data($cid, "PRIVMSG $arg :No data in result.\n");
									}
									else
									{
										// vis hver rad
										$i = 1;
										while ($row = mysql_fetch_row($result)) {
											$data = array();
											foreach ($row as $value) { $data[] = preg_replace("/[\r\n]/", "", $value); }
											$this->send_data($cid, "PRIVMSG $arg :Row $i: ".implode(", ", $data)."\n");
											$i++;
										}
									}
									
									$this->send_data($cid, "PRIVMSG $arg :Query completed..\n");
								}
							}
							
							$break = true;
						}
						
						else
						{
							$log = false;
						}
						
						if ($log)
						{
							// logg forespørselen
							file_put_contents("irclog-".$this->settings['name'].".log", date("r") . " {$user['nick']}!{$user['ident']}@{$user['host']} $type $arg $content\n", FILE_APPEND);
						}
						
						if ($break) break;
					}
					
					// ikke kanal melding
					if (substr($arg, 0, 1) != "#")
					{
						if ($user['ident'] != "" && $type == "PRIVMSG")
						{
							$this->send_data($cid, "NOTICE {$user['nick']} :Doh?\n");
						}
					}
					
					// kanalmelding
					else
					{
						// aktiv cid?
						if ($this->connected_cid == $cid)
						{
							$info = preg_split("/\\s+/", $content, 2);
							if (!isset($info[1])) $info[1] = NULL;
							switch ($info[0])
							{
								case "!status":
									$time = intval($info[1]);
									if ($time == 0 || $time < 0)
									{
										$time = 300;
									}
									
									$last = time()-$time;
									$result = $_base->db->query("SELECT COUNT(up_id) FROM users_players WHERE up_last_online >= $last");
									$ant = game::format_number(mysql_result($result, 0));
									mysql_free_result($result);
									
									$time = game::timespan($time, game::TIME_FULL | game::TIME_NOBOLD);
									$this->msg($cid, $arg, "%bAntall pålogget siste $time%b: %u$ant%u");
								break;
								
								case "!info":
									if ($this->irc_info)
									{
										$this->irc_info->end_node = $arg;
										$this->irc_info->parse_request($info[1]);
									}
								break;
								
								case "!search":
									$this->node_search($cid, $arg, $info[1]);
								break;
							}
						}
					}
				break;
				
				default:
					$this->debug($cid, "UNKNOWN USER DATA: $line");
			}
			
			return;
		}
		
		$info = explode(" ", $line, 2);
		switch ($info[0])
		{
			case "PING":
			$this->debug($cid, "PING PONG");
			$this->send_data($cid, "PONG {$info[1]}");
			break;
			
			case "ERROR":
			$this->debug($cid, $line);
			$this->handle_error($cid, "Error occured..");
			
			// koble til på nytt
			#$this->connect($cid);
			break;
			
			default:
			$this->debug($cid, "UNKNOWN DATA: $line");
		}
	}
	
	// send data
	function send_data($cid, $data)
	{
		if (!isset($this->sockets[$cid]))
		{
			$this->debug($cid, "Socket is not connected. Cannot send data: " . $data);
			return;
		}
		
		$this->debug($cid, "Sending data: $data");
		$status = socket_write($this->sockets[$cid], $data);
		
		// feilet den?
		if ($status === false)
		{
			$this->handle_error($cid, "Coult not write to socket (".socket_strerror(socket_last_error()).")");
		}
	}
	
	// send melding
	function msg($cid, $to, $msg)
	{
		$msg = preg_replace("/[\r\n]/", "", strtr($msg, array(
			"%b" => "",
			"%c" => "",
			"%u" => ""
		)));
		$this->send_data($cid, "PRIVMSG $to :$msg\n");
	}

	
	// koble fra
	function disconnect($cid, $message = "Bye bye folks!")
	{
		$this->send_data($cid, "QUIT :$message!\n");
		
		if ($this->connected_cid == $cid)
		{
			$this->connected_cid = false;
			$this->connected = false;
			foreach (array_keys($this->sockets) as $scid)
			{
				if ($scid == $cid) continue;
				$this->connected_cid = $scid;
				$this->connected = true;
				break;
			}
		}
		
		$this->handle_error($cid, "Disconnecting..");
	}
	
	// CTCP quote
	function ctcp_quote($data)
	{
		$data = strtr($data, array(
			0x0 => "\\0",
			0x1 => "\\1",
			"\n" => "\\n",
			"\r" => "\\r",
			" " => "\\@",
			"\\" => "\\\\")
		);
		
		return 0x1 . $data . 0x1;
	}
	
	// CTCP unquote
	function ctcp_unquote($data)
	{
		$data = strtr($data, array(
			"\\0" => 0x0,
			"\\1" => 0x1,
			"\\n" => "\n",
			"\\r" => "\r",
			"\\@" => " ",
			"\\\\" => "\\")
		);
		
		return substr($data, 1, $data - 2);
	}
	
	// CTCP meldinger
	/*function handle_message_ctcp($data)
	{
		
	}*/
	
	/**
	 * Nodesøk
	 */
	protected function node_search($cid, $dest, $search)
	{
		$search = utf8_decode($search);
		
		// hent all informasjon
		$result = ess::$b->db->query("SELECT node_id, node_parent_node_id, node_title, node_type, node_params, node_show_menu, node_expand_menu, node_enabled, node_priority, node_change FROM nodes");
		$nodes = array();
		while ($row = mysql_fetch_assoc($result))
		{
			$row['params'] = new params($row['node_params']);
			$row['enheter'] = array();
			$row['plain'] = "";
			$nodes[$row['node_id']] = $row;
		}
		
		if (count($nodes) == 0)
		{
			$this->msg($cid, $dest, "Ingen siden eksisterer.");
			return;
		}
		
		// sett opp søkekriteriene
		$search_list = search_query($search);
		$search_list = $search_list[1];
		$search_list2 = $search_list; // for delvise treff
		
		foreach ($search_list as &$q)
		{
			$q = '/(\\P{L}|^)'.preg_replace(array('/([\\/\\\\\\[\\]()$.+?|{}])/', '/\\*\\*+/', '/\\*/'), array('\\\\$1', '*', '\\S*'), $q).'(\\P{L}|$)/i';
		}
		
		// sett opp søkeliste hvor vi søker med * på slutten av ordene
		foreach ($search_list2 as &$q)
		{
			$q = '/'.preg_replace(array('/([\\/\\\\\\[\\]()$.+?|{}])/', '/\\*\\*+/', '/\\*/'), array('\\\\$1', '*', '\\S*'), $q).'/i';
		}
		
		// hent alle enhetene
		$result = ess::$b->db->query("SELECT ni_id, ni_node_id, ni_type, nir_content, nir_params, nir_time FROM nodes_items LEFT JOIN nodes_items_rev ON nir_id = ni_nir_id WHERE ni_enabled != 0 AND ni_deleted = 0 ORDER BY ni_priority");
		while ($row = mysql_fetch_assoc($result))
		{
			if (!isset($nodes[$row['ni_node_id']])) continue;
			
			$data = nodes::content_build($row);
			$nodes[$row['ni_node_id']]['enheter'][] = $data;
			
			// bygg opp plain tekst
			$plain = preg_replace("/<br[^\\/>]*\\/?>/", "\n", $data);
			$plain = preg_replace("/(<\\/?(h[1-6]|p)[^>]*>)/", "\n\\1", $plain);
			$plain = html_entity_decode(strip_tags($plain));
			$plain = preg_replace("/(^ +| +$|\\r)/m", "", $plain);
			#$plain = preg_replace("/(?<![!,.\\n ])\\n/", " ", $plain);
			$plain = preg_replace("/\\n/", " ", $plain);
			$plain = preg_replace("/  +/", " ", $plain);
			$plain = trim($plain);
			$nodes[$row['ni_node_id']]['plain'] .= $plain . " ";
		}
		
		// sett opp riktige referanser og lag tree
		$sub = array();
		foreach (nodes::$nodes as $row)
		{
			if ($row['node_enabled'] != 0)
			{
				$sub[$row['node_parent_node_id']][] = $row['node_id'];
			}
		}
		$tree = new tree($sub);
		$data = $tree->generate(0, NULL, $nodes);
		
		// sett opp paths
		$paths = array();
		$path = array();
		$number = 1;
		foreach ($data as $row)
		{
			for (; $row['number'] <= $number; $number--)
			{
				// fjern fra path
				array_pop($path);
			}
			
			if ($row['number'] >= $number)
			{
				// legg til i path
				$path[] = $row['data']['node_title'];
			}
			
			$paths[$row['data']['node_id']] = $path;
			$number = $row['number'];
		}
		
		// sett opp søkeresultater
		$result = array();
		$points = array();
		$points2 = array();
		
		foreach ($data as $row)
		{
			if ($row['data']['node_type'] != "container") continue;
			
			// utfør søk
			$found = true;
			$p = 0;
			$p2 = 0;
			foreach ($search_list as $key => $regex)
			{
				$ok = false;
				$matches = null;
				
				// søk i teksten
				if (preg_match_all($regex, $row['data']['plain'], $matches))
				{
					$ok = true;
					$p += count($matches[0]);
				}
				
				if (preg_match_all($search_list2[$key], $row['data']['plain'], $matches))
				{
					$ok = true;
					$p2 += count($matches[0]);
				}
				
				// søk i tittelen
				if (preg_match_all($regex, $row['data']['node_title'], $matches))
				{
					$ok = true;
					$p += count($matches[0]);
				}
				if (preg_match_all($search_list2[$key], $row['data']['node_title'], $matches))
				{
					$ok = true;
					$p2 += count($matches[0]);
				}
				
				if ($ok) continue;
				$found = false;
				break;
			}
			
			// fant?
			if ($found)
			{
				$result[] = $row;
				$points[] = $p;
				$points2[] = $p2;
			}
		}
		
		// vis søkeresultater
		if (count($result) == 0)
		{
			$this->msg($cid, $dest, "Ingen treff ble funnet.");
		}
		
		// sorter søkeresultatene
		array_multisort($points, SORT_DESC, SORT_NUMERIC, $points2, SORT_DESC, SORT_NUMERIC, $result);
		
		$this->msg($cid, $dest, count($result)." treff ble funnet - ".ess::$s['path'].'/node/search?q='.urlencode($search));
		
		$i = 0;
		foreach ($result as $key => $row)
		{
			if ($i++ == 3) break;
			$partial = $points2[$key] - $points[$key];
			
			if ($row['data']['node_id'] == nodes::$default_node)
			{
				$url = ess::$s['path'].'/node';
			}
			else
			{
				$url = ess::$s['path'].'/node/'.$row['data']['node_id'];
			}
			
			$m = array();
			if ($points[$key] > 0) $m[] = $points[$key].' treff';
			if ($partial > 0) $m[] = fwords("%d delvis treff", "%d delvise treff", $partial);
			$this->msg($cid, $dest, implode(", ", $m).": ".implode(" -> ", $paths[$row['data']['node_id']])." $url");
		}
	}
}
