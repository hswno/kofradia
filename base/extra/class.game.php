<?php

// Kofradia
// game funksjoner

// hent inn innstillinger, info om bydelene og rankene
// hentes fra cache hvis mulig
if (!game::$settings) game::init();

class game
{
	/** Innstillinger */
	public static $settings;
	
	/** Bydeler */
	public static $bydeler;
	
	/** Rankene */
	public static $ranks;
	
	/** For [nobb] */
	public static $nobb = array();
	
	/** For [linkonly] */
	public static $linkonly = array();
	
	public static $bb_music = array();
	public static $bb_music_c = 0;
	
	// init
	public static function init()
	{
		// hent innstillinger
		self::$settings = cache::fetch("settings");
		if (!self::$settings)
		{
			// hent ny data
			require ROOT."/base/scripts/update_db_settings.php";
		}
		
		// hent bydeler
		self::$bydeler = cache::fetch("bydeler");
		if (!self::$bydeler)
		{
			// hent ny data
			require ROOT."/base/scripts/update_db_bydeler.php";
		}
		
		// hent ranker
		self::$ranks = cache::fetch("ranks");
		if (!self::$ranks)
		{
			// hent ny data
			require ROOT."/base/scripts/update_db_ranks.php";
		}
	}
	
	// formatter rank % av poeng
	public static function format_rank($points, $rank = false)
	{
		if ($rank === false)
		{
			// bruk ranken til brukere
			$rank = login::$user->player->rank;
		}
		
		// bruke siste rank?
		elseif ($rank === "all")
		{
			$rank = end(game::$ranks['items_number']);
		}
		
		if ($rank['need_points'] == 0)
		{
			// i forhold til alle rankene (siste rank)
			$p = round($points/$rank['points'], 6) * 100;
			$p = game::format_num($p, 4) . " %";
		}
		else
		{
			// i forhold til neste rank
			$p = round($points/$rank['need_points'], 5) * 100;
			$p = game::format_num($p, 3) . " %";
		}
		
		return $p;
	}
	
	// finn ut rankinfo
	public static function rank_info($points, $pos = 0, $access_level = 1)
	{
		global $_game;

		$rank = array("number" => 1, "id" => 0, "name" => "Ukjent", "points" => 0, "need_points" => 0, "number" => 0, "pos" => 0, "orig" => false, "pos_id" => 0);

		// gå gjennom alle rankene baklengs til vi finner denne ranken
		end(game::$ranks['items']);
		while ($row = current(game::$ranks['items']))
		{
			if ($row['points'] <= $points)
			{
				$rank = $row;
				break;
			}
			prev(game::$ranks['items']);
		}
		$rank['orig'] = false;
		$rank['pos_id'] = 0;
		$rank['pos'] = $pos;

		// død?
		if ($access_level == 0)
		{
			$rank['orig'] = $rank['name'];
			$rank['name'] = $_game['rank_death'];
		}

		// access?
		elseif (array_key_exists($access_level, $_game['ranks_access_levels']))
		{
			$rank['orig'] = $rank['name'];
			$rank['name'] = $_game['ranks_access_levels'][$access_level];
		}

		// finn rank nummer
		elseif ($pos > 0 && $pos <= game::$ranks['pos_max'])
		{
			foreach (game::$ranks['pos'] as $row)
			{
				if ($row['pos'] >= $pos) break;
			}
			$rank['orig'] = $rank['name'];
			$rank['name'] = $row['name'];
			$rank['pos_id'] = $row['pos'];
		}

		return $rank;
	}

	// finn ut neste rank
	public static function next_rank($points)
	{
		global $_game;

		foreach (game::$ranks['items'] as $row)
		{
			// har denne ranken flere poeng enn vi så etter? -> neste rank
			if ($row['points'] > $points)
			{
				return $row;
			}
		}

		return false;
	}

	// finn ut pengenavn
	public static function cash_name($cash)
	{
		global $_game;

		// gå gjennom alle pengebeløpene baklengs til vi finner riktig pengebeløp
		end($_game['cash']);
		while (($min = current($_game['cash'])) !== false)
		{
			if ($min <= $cash)
			{
				return key($_game['cash']);
			}
			prev($_game['cash']);
		}

		return "Ukjent";
	}
	
	/**
	 * Finn ut hvilke nummer av pengenavn vi har
	 */
	public static function cash_name_number($cash)
	{
		global $_game;
		
		// gå gjennom alle pengebeløpene baklengs til vi finner riktig pengebeløp
		end($_game['cash']);
		$i = count($_game['cash']);
		while (($min = current($_game['cash'])) !== false)
		{
			if ($min <= $cash)
			{
				return $i;
			}
			
			prev($_game['cash']);
			$i--;
		}
		
		return 0;
	}
	
	// formater pengebeløp til tekst
	public static function format_cash($cash)
	{
		return game::number_format_large($cash) . " kr";
	}

	// formater pengebeløp til tekst
	public static function format_nok($cash)
	{
		/*$end = ",00";
		if (($pos = strpos($cash, ".")) !== false)
		{
			$decimal = substr($cash, $pos+1, 2);
			$end = "," . str_pad($decimal, 2, "0", STR_PAD_RIGHT);
			$cash = substr($cash, 0, $pos);
		}
		return "kr. " . strrev(chunk_split(strrev($cash), 3, " ")).$end;*/
		return "kr. ".game::number_format_large($cash, 2);
	}

	// formaterer et tall til tall med tusenmellomrom osv
	public static function format_number($float, $decimals = 0)
	{
		/*$end = "";
		if (($pos = strpos($float, ".")) !== false)
		{
			if ($decimals > 0)
			{
				$decimal = substr($float, $pos+1, $decimals);
				$end = "," . str_pad($decimal, $decimals, "0", STR_PAD_RIGHT);
			}
			$float = ($pos == 0 ? '0' : '').substr($float, 0, $pos);
		}
		return strrev(chunk_split(strrev($float), 3, " ")).$end;*/
		return game::number_format_large($float, $decimals, ",", " ");
	}
	
	/**
	 * Formattere små tall (tar ikke høyde for whitespace og er ment for output av integers og floats og ikke tekstinput
	 * @param float $float
	 * @param integer $decimals
	 * @return string
	 */
	public static function format_num($float, $decimals = 0)
	{
		$neg = $float < 0;
		if ($neg) $float = abs($float);
		return ($neg ? '- ' : '').number_format($float, $decimals, ",", " ");
	}
	
	public static function number_format_large($number, $decimals = 0, $dec_seperator = ",", $tho_seperator = " ")
	{
		// funksjonen runder alltid tall NEDOVER
		// brukes for svært store tall

		$negative = preg_match('/^\s*\-/', $number);
		$number = preg_replace('/[^0-9\.Ee\+]/', '', $number);
		$number = preg_replace('/^0*/', '', $number);

		// E tall?
		$matches = false;
		if (preg_match('/^([0-9])(?:\.([0-9]+))?[eE]\+([0-9]+)$/D', $number, $matches))
		{
			$number = $matches[1];

			// før desimaltallet
			$e = intval($matches[3]);
			$matches[2] = str_pad($matches[2], $e + $decimals, "0", STR_PAD_RIGHT);
			for ($i = 0; $i < $e; $i++)
			{
				$number .= substr($matches[2], $i, 1);
			}

			// etter desimaltallet
			if ($decimals > 0)
			{
				$number .= ".";
				$len = $e + $decimals;
				for (; $i < $len; $i++)
				{
					$number .= substr($matches[2], $i, 1);
				}
			}
		}

		$num = explode(".", $number, 2);
		$number = substr(strrev(chunk_split(strrev($num[0]), 3, $tho_seperator)), 1);
		if ($number == "") $number = 0;
		if ($decimals > 0)
		{
			$num[1] = isset($num[1]) ? $num[1] : "";
			$num[1] = str_pad($num[1], $decimals, "0", STR_PAD_RIGHT);
			$number .= $dec_seperator . substr($num[1], 0, $decimals);
		}

		if (empty($number)) $number = 0;

		return ($negative ? '- ' : '').$number;
	}

	// intval
	public static function intval($number)
	{
		$negative = preg_match('/^\s*\-/', $number);
		$number = preg_replace('/[^0-9\.E\+]/', '', $number);
		$number = preg_replace('/^0*/', '', $number);

		// E tall?
		$matches = false;
		if (preg_match('/^([0-9])(?:\.([0-9]+))?E\+([0-9]+)$/D', $number, $matches))
		{
			$number = $matches[1];

			// før desimaltallet
			$e = intval($matches[3]);
			$matches[2] = str_pad($matches[2], $e, "0", STR_PAD_RIGHT);
			for ($i = 0; $i < $e; $i++)
			{
				$number .= substr($matches[2], $i, 1);
			}
		}

		$number = preg_replace('/[^0-9\.]/', '', $number);
		$number = explode(".", $number, 2);
		$number = $number[0];
		if (empty($number)) $number = 0;
		return ($negative ? '-' : '').$number;
	}
	
	// lag profillink til en bruker
	public static function profile_link($up_id = false, $name = "", $access_level = 1, $link = true, $linkurl = NULL)
	{
		global $__server;
		if ($name === "")
		{
			if (!login::$logged_in) return "anonym";
			$up_id = login::$user->player->id;
			$name = login::$user->player->data['up_name'];
			$access_level = login::$user->player->data['up_access_level'];
		}
		$at = access::type($access_level);
		$color_class = access::html_class($at);
		$name_f = ($format = access::html_format($at)) ? str_replace("%user", htmlspecialchars($name), $format) : htmlspecialchars($name);
		
		$icons = '';
		$icons_right = '';
		
		$contacts = login::$logged_in ? login::$info['contacts'] : false;
		if ($contacts)
		{
			if ($up_id == login::$user->player->id) // meg selv?
			{
				$icons .= '<img src="'.STATIC_LINK.'/other/myself_contact_small.gif" alt="(meg selv)" title="Deg selv" />';
			}
			elseif (isset($contacts[1][$up_id])) // kontakt
			{
				$icons .= '<img src="'.STATIC_LINK.'/other/user_small.gif" alt="(kontakt)" title="Din kontakt" />';
			}
			if (isset($contacts[2][$up_id])) // blokk
			{
				$icons .= '<img src="'.STATIC_LINK.'/other/block.png" alt="(blokkert)" title="Blokkert" />';
			}
		}
		
		// deaktivert?
		if ($access_level == 0)
		{
			$icons_right .= '<img src="'.STATIC_LINK.'/other/kors_lite.png" alt="(deaktivert)" title="Deaktivert" class="deactivated" />';
		}
		
		// link
		if ($link)
		{
			$p = "";
			if (!$linkurl) { $linkurl = $__server['relative_path'].'/p/'.rawurlencode($name).'/'.$up_id; $p = ' title="Vis profil"'; }
			return '<a href="'.$linkurl.'"'.$p.' rel="'.$up_id.'" class="profile_link'.($color_class ? ' '.$color_class : '').'">'.$icons.'<span>'.$name_f.'</span>'.$icons_right.'</a>';
		}
		
		// tekst
		return '<span rel="'.$up_id.'" class="profile_link'.($color_class ? ' '.$color_class : '').'">'.$icons.'<span>'.$name_f.'</span>'.$icons_right.'</span>';
	}
	
	// sikre adresser til bilder
	public static function secure_img_addr($addr)
	{
		/*if (substr($addr, 0, 1) == "/" || substr($addr, 0, 1) == "\\" || substr($addr, 0, 1) == ".")
		{
			if (($pos = strpos($addr, "?")) !== false)
			{
				$name = substr($addr, 0, $pos);
				$parts = explode("/", $name);
				foreach ($parts as $part)
				{
					if (substr($part, -4) == ".php")
					{
						return "IMG: Mulig exploit hindret";
					}
				}
			}
		}*/
		
		if (preg_match("/(^javascript)/i", $addr))
		{
			return "[img]$addr (EXPLOIT WARNING)[/img]";
		}
		
		return '<span class="bb_image"><img src="'.$addr.'" alt="" /></span>';
	}

	// html i BB kode
	public static function html_add(/*$passphrase, $text*/)
	{
		return "";
		
		/*global $_base;
		
		// fjern html beskyttelse
		$text = htmlspecialchars_decode($text);
		
		// kontroller innhold mot passphrase
		if (game::html_generate_passphrase($text) != $passphrase)
		{
			return "[html-invalid-passphrase]";
		}
		
		// gyldig innhold
		game::$nobb[] = $text;
		end(game::$nobb);
		
		return '<nobb id="'.key(game::$nobb).'" />';*/
	}
	public static function html_generate_passphrase($text)
	{
		$key = "smafia_raw_html";
		$text = preg_replace("/[\n\r\t ]/", "", $text);
		return substr(md5($key . $text), 0, 8);
	}
	
	
	public static function nobb_add($text)
	{
		global $_base;
		
		// har vi musikk?
		$text = preg_replace("|&lt;music id=\\&quot;([0-9]+)\\&quot; /&gt;|e", 'game::music_get(\'$1\', true)', $text);
		
		game::$nobb[] = $text;
		end(game::$nobb);
		
		return '<nobb id="'.key(game::$nobb).'" />';
	}
	
	public static function nobb_get($id)
	{
		global $_base;
		
		if (!isset(game::$nobb[$id])) return '';
		
		$ret = game::$nobb[$id];
		unset(game::$nobb[$id]);
		
		return $ret;
	}
	
	public static function nobb_replace($text)
	{
		return preg_replace("|<nobb id=\"([0-9]+)\" />|e", 'game::nobb_get(\'$1\')', $text);
	}
	
	public static function linkonly_add($text)
	{
		global $_base;
		
		game::$linkonly[] = $text;
		end(game::$linkonly);
		
		return '<linkonly id="'.key(game::$linkonly).'" />';
	}
	
	public static function linkonly_get($id)
	{
		global $_base;
		
		if (!isset(game::$linkonly[$id])) return '';
		
		$ret = game::$linkonly[$id];
		unset(game::$linkonly[$id]);
		
		$rep = array(
			// internettadresser
			'~(?<=[!>:\?\.\s\xA0[\]()*\\\;]|^)((?:https?|ftp)://([\w\d/=;\\\?#\\-%:@+æøå\\~]|[,.](?! )|&amp;)+)~i' => '<a href="$1" target="_blank">$1</a>',
			'~(?<=[!>:\?\.\s\xA0[\]()*\\\;]|^)(www\.([\w\d/=;\\\?#\\-%:@+æøå\\~]|[,.](?! )|&amp;)+)~i' => '<a href="http://$1" target="_blank">$1</a>'
		);
		$code_from = array_keys($rep);
		$code_to = array_values($rep);
		$ret = preg_replace($code_from, $code_to, $ret);
		
		return $ret;
	}
	
	public static function linkonly_replace($text)
	{
		return preg_replace("|<linkonly id=\"([0-9]+)\" />|e", 'game::linkonly_get(\'$1\')', $text);
	}
	
	public static function music_add($text)
	{
		$id = count(game::$bb_music);
		game::$bb_music[] = $text;
		
		return '<music id="'.$id.'" />';
	}
	
	public static function music_get($id, $original = false)
	{
		if (!isset(game::$bb_music[$id])) return '';
		
		if ($original) return '[music]'.game::$bb_music[$id].'[/music]';
		
		// autospille?
		if (game::$bb_music_c == 0 && login::$logged_in && login::$user->data['u_music_auto'] == 1) $as = "&as=true";
		else $as = "";
		
		// trykke for å laste inn?
		$manual = !login::$logged_in || login::$user->params->get("music_manual");
		
		// last inn swfobject
		ess::$b->page->add_js_file(LIB_HTTP.'/swfobject/swfobject.js');
		
		// sett opp adresse til flash filen og legg til
		$path = STATIC_LINK.'/swf/musikkspiller.swf?url='.urlencode(game::$bb_music[$id]);
		$num = ++game::$bb_music_c;
		if ($manual)
		{
			ess::$b->page->add_js_domready('var elm = $("music_player_'.$num.'"); var f = function() { swfobject.embedSWF("'.$path.'&as=true", "music_player_'.$num.'", 160, 60, "9.0.0", false); elm.removeEvent("click", f); }; elm.addEvent("click", f);');
		}
		else
		{
			ess::$b->page->add_js_domready('swfobject.embedSWF("'.$path.$as.'", "music_player_'.$num.'", 160, 60, "9.0.0", false);');
		}
		
		return '<span id="music_player_'.$num.'"><img src="'.STATIC_LINK.'/other/musicplayer_press_to_play.png" style="cursor:pointer" alt="Musikkspiller - trykk for å spille av" /></span>';
	}
	
	public static function music_replace($text, $original = false)
	{
		if ($original)
		{
			return preg_replace("|&lt;music id=\\&quot;([0-9]+)\\&quot; /&gt;|e", 'game::music_get(\'$1\', true)', $text);
		}
		return preg_replace("|&lt;music id=\\&quot;([0-9]+)\\&quot; /&gt;|e", 'game::music_get(\'$1\')', $text);
	}
	
	// formatter tekst til riktig format
	public static function format_data($data, $type = "bb", $args = null)
	{
		global $_base;
		
		switch ($type)
		{
			case "bb":
			case "signature":
				// bb kode
				return game::bb_to_html($data);
			
			// bb-kode eller egendefinert tekst hvis bb-kode er tom
			case "bb-opt":
				$bb = trim(game::bb_to_html($data));
				if ($bb == "")
				{
					return $args;
				}
				return $bb;
			
			case "music_pre":
				return preg_replace('~\[music\](https?://.+?)\[/music\]~ie', 'game::music_add(\'$1\')', $data);
			
			case "music_post":
				return game::music_replace($data);
			
			case "profile":
				$data = game::format_data($data, "music_pre");
				
				// sett opp ranken
				$rank = game::rank_info($args->data['up_points'], $args->data['upr_rank_pos'], $args->data['up_access_level']);
				
				// diverse bb koder
				$data = str_replace(
					array(
						"[counter]",
						"[visits]",
						"[visitor]",
						"[rank]",
						"[bank]",
						"[cash]",
						"[money]",
						"[renter]",
						"[pm_ulest]",
						"[pengerank]"
					),
					array(
						'<img src="&rpath;/counter?count='.$args->data['up_profile_hits'].'" alt="Antall visninger: '.$args->data['up_profile_hits'].'" />',
						$args->data['up_profile_hits'],
						game::profile_link(),
						$rank['orig'] ? $rank['orig'] : $rank['name'],
						game::format_cash($args->data['up_bank']),
						game::format_cash($args->data['up_cash']),
						game::format_cash($args->data['up_cash']+$args->data['up_bank']),
						game::format_cash($args->data['up_interest_last']),
						game::format_number($args->user->data['u_inbox_new']),
						game::cash_name($args->data['up_cash']+$args->data['up_bank'])
					),
					game::bb_to_html($data)
				);
				
				// rankbar
				$type = false;
				$match_rank = preg_match("~\\[rank_(neste_tid|neste_dato|tid|dato)\\]~i", $data);
				if (preg_match("~\\[rankbar( type=(1|2))?\\]~i", $data, $type) || $match_rank)
				{
					// høyeste rank?
					if ($rank['need_points'] == 0)
					{
						global $_game;
						
						$prosent = $args->data['up_points'] / game::$ranks['items_number'][count(game::$ranks['items_number'])]['points'] * 100;
						
						$rankbar_total = '
<div class="progressbar">
	<div class="progress"><p>'.game::format_num($prosent, 3).' %</p></div>
</div>';
						
						$rankbar_total2 = '
<div class="progressbar" style="margin-top: 1em">
	<div class="progress"><p>'.game::format_num($prosent, 3).' %</p></div>
</div>';
						
						$data = str_replace(
							array(
								"[rankbar]",
								"[rankbar type=1]",
								"[rankbar type=2]",
								"[rank_tid]",
								"[rank_dato]",
								"[rank_neste_tid]",
								"[rank_neste_dato]"
							),
							array(
								$rankbar_total . $rankbar_total2,
								$rankbar_total,
								$rankbar_total,
								"Oppnådd",
								"Oppnådd",
								"Oppnådd",
								"Oppnådd"
							),
							$data
						);
					}
					
					// ikke høyeste rank
					// må regne ut diverse tall
					else
					{
						// i forhold til den høyeste ranken
						global $_game;
						
						// antall poeng for den høyeste ranken
						$points_max = game::$ranks['items'];
						end($points_max);
						$points_max = current($points_max);
						$points_max = $points_max['points'];
						
						// hvor langt ifra er vi?
						$percent_total = round($args->data['up_points'] / $points_max, 2) * 100;
						#if ($percent_total > 100) $percent_total = 100;
						if ($percent_total == 0) $percent_total = 0.01;
						
						$rankbar_total = '
<div class="progressbar">
	<div class="progress" style="width: '.floor($percent_total).'%"><p>'.game::format_num($percent_total, 2).' % i forhold til høyeste rank.</p></div>
</div>';
						
						// i forhold til neste rank
						$points_rank = $args->data['up_points'] - $rank['points'];
						$percent = round($points_rank / $rank['need_points'] * 100, 2);
						#if ($percent  )
						
						$rankbar_next = '
<div class="progressbar">
	<div class="progress" style="width: '.floor($percent).'%"><p>'.game::format_num($percent, 2).' % i forhold til neste rank</p></div>
</div>';
						
						$rankbar_next2 = '
<div class="progressbar" style="margin-top: 1em">
	<div class="progress" style="width: '.floor($percent).'%"><p>'.game::format_num($percent, 2).' % i forhold til neste rank</p></div>
</div>';
						
						// fiks bb kodene
						$data = str_replace(
							array(
								"[rankbar]",
								"[rankbar type=1]",
								"[rankbar type=2]"
							),
							array(
								$rankbar_total . $rankbar_next2,
								$rankbar_total,
								$rankbar_next
							),
							$data
						);
						
						// beregn antatt tid det tar å nå høyeste rank
						if ($match_rank)
						{
							// hent ut aktiviteten de siste X dagene
							$expire = ess::$b->date->get();
							$expire->modify("-21 days");
							$expire = max($args->data['up_created_time'], $expire->format("U")); // maks tid: siste 21 dager eller siden reg (om registrert innen 30 dager)
							
							$result = ess::$b->db->query("SELECT SUM(uhi_points) FROM users_hits WHERE uhi_up_id = $args->id AND uhi_secs_hour >= $expire");
							$points = (int) mysql_result($result, 0);
							
							if ($points == 0)
							{
								$data = str_replace(
									array(
										"[rank_tid]",
										"[rank_dato]",
										"[rank_neste_tid]",
										"[rank_neste_dato]"
									),
									array(
										"ukjent",
										"ukjent",
										"ukjent",
										"ukjent"
									),
									$data);
							}
							
							else
							{
								// antall sekunder poengene skal fordeles på
								$time_elapsed = time() - $expire;
								
								// beregn til neste rank og erstatt BB-kode
								$need = $rank['points'] + $rank['need_points'] - $args->data['up_points'];
								$time_left = $need / $points * $time_elapsed;
								if ($time_left > 63072000) // 5 år frem i tid
								{
									$text_left = "over 2 år";
									$text_date = "om over 2 år";
								}
								else
								{
									$text_left = game::timespan($time_left);
									$text_date = ess::$b->date->get($time_left + time())->format(date::FORMAT_NOTIME);
								}
								$data = str_replace(
									array(
										"[rank_neste_tid]",
										"[rank_neste_dato]"
									),
									array(
										$text_left,
										$text_date
									),
									$data);
								
								// beregn til øverste rank og erstatt BB-kode
								$need = $points_max - $args->data['up_points'];
								$time_left = $need / $points * $time_elapsed;
								if ($time_left > 63072000) // 5 år frem i tid
								{
									$text_left = "over 2 år";
									$text_date = "om over 2 år";
								}
								else
								{
									$text_left = game::timespan($time_left);
									$text_date = ess::$b->date->get($time_left + time())->format(date::FORMAT_NOTIME);
								}
								$data = str_replace(
									array(
										"[rank_tid]",
										"[rank_dato]"
									),
									array(
										$text_left,
										$text_date
									),
									$data);
							}
						}
					}
				}
				
				// kontaktliste?
				if (strpos($data, "[kontakter]") !== false)
				{
					// hent kontaktliste
					$result = $_base->db->query("SELECT uc_contact_up_id, up_name, up_access_level, up_last_online FROM users_contacts LEFT JOIN users_players ON uc_contact_up_id = up_id WHERE uc_u_id = {$args->data['up_u_id']} AND uc_type = 1 ORDER BY up_name");
					
					$html = '
<table class="table l tablem">
	<thead>
		<tr>
			<th>Spiller</th>
			<th>Sist pålogget</th>
		</tr>
	</thead>
	<tbody>';

					while ($row = mysql_fetch_assoc($result))
					{
						$html .= '
		<tr>
			<td>'.game::profile_link($row['uc_contact_up_id'], $row['up_name'], $row['up_access_level']).'</td>
			<td class="r">'.game::timespan($row['up_last_online'], game::TIME_ABS).'</td>
		</tr>';
					}

					$html .= '
	</tbody>
</table>';

					// sett inn som bb kode
					$data = str_replace("[kontakter]", $html, $data);
				}

				// blokkeringliste?
				if (strpos($data, "[blokkert]") !== false)
				{
					// hent blokkeringliste
					$result = $_base->db->query("SELECT uc_contact_up_id, up_name, up_access_level, up_last_online FROM users_contacts LEFT JOIN users_players ON uc_contact_up_id = up_id WHERE uc_u_id = {$args->data['up_u_id']} AND uc_type = 2 ORDER BY up_name");
					
					$html = '
<table class="table l tablem">
	<thead>
		<tr>
			<th>Spiller</th>
			<th>Sist pålogget</th>
		</tr>
	</thead>
	<tbody>';
					
					while ($row = mysql_fetch_assoc($result))
					{
						$html .= '
		<tr>
			<td>'.game::profile_link($row['uc_contact_up_id'], $row['up_name'], $row['up_access_level']).'</td>
			<td class="r">'.game::timespan($row['up_last_online'], game::TIME_ABS).'</td>
		</tr>';
					}

					$html .= '
	</thead>
</table>';
					
					// sett inn som bb kode
					$data = str_replace("[blokkert]", $html, $data);
				}
				
				return game::format_data($data, "music_post");
			
			default:
				// ukjent!
				throw new HSException("Ukjent type ($type)");
		}
		
		return false;
	}

	// gjør om bb koder til html
	public static function bb_to_html($bb)
	{
		static $code_from_cache_single = array();
		static $code_to_cache_single = array();
		static $code_from_cache = array();
		static $code_to_cache = array();
		
		// sikre html
		$bb = htmlspecialchars($bb);
		
		if (empty($code_from_cache))
		{
			global $__server;
			
			// kode som kun skal kjøres en gang
			$replaces_single = array(
				// carrage returns
				'~\r~' => '',
				
				// raw html i BB kode (ved hjelp av "passphrase"
				'~\[html=([a-z0-9]+)\](.+?)\[/html=\1\]~ise' => 'game::html_add(\'$1\', \'$2\')',
				
				// nobb -> ikke formatter bb kodene inni denne..
				'~\[nobb\](.+?)\[/nobb\]~ise' => 'game::nobb_add(\'$1\')',
				
				// linkonly -> til bruk for youtube-adresser
				'~\[linkonly\](.+?)\[/linkonly\]~ise' => 'game::linkonly_add(\'$1\')',
				
				// kommentarer -> skjul alt
				'~\[comment\](.+?)\[/comment\]~is' => '#',
				'~\[comment=([^\]]+)\](.+?)\[/comment\]~is' => '<span style="border: 1px solid #333333; background-color: #222222; padding: 2px">#$1</span>',
				'~\[comment hide\](.+?)\[/comment\]~is' => '',
				
				// hide -> ikke vis
				'~\[hide\](.+?)\[/hide\]~is' => '',
				
				// youtube videoer
				'~(?<=[!>:\?\.\s\xA0[\]()*\\\;]|^)(https?://)?(www.)?youtube.com/v/([0-9a-z_\-]{11})[^\s<>&\\\]*~i' => '<object width="425" height="350"><param name="movie" value="http://www.youtube.com/v/$3"></param><param name="wmode" value="transparent"></param><embed src="http://www.youtube.com/v/$3" type="application/x-shockwave-flash" wmode="transparent" width="425" height="350"></embed></object><br />Adresse: <a href="$0">$0</a>',
				
				'~(?<=[!>:\?\.\s\xA0[\]()*\\\;]|^)(https?://)?(www.)?youtube.com/.+v=([0-9a-z_\-]{11})[^\s<]*~i' => '<object width="425" height="350"><param name="movie" value="http://www.youtube.com/v/$3"></param><param name="wmode" value="transparent"></param><embed src="http://www.youtube.com/v/$3" type="application/x-shockwave-flash" wmode="transparent" width="425" height="350"></embed></object>',
				
				'~\[youtube\].+v=([0-9a-z_\.]+).*\[/youtube\]~i' => '<object width="425" height="350"><param name="movie" value="http://www.youtube.com/v/$1"></param><param name="wmode" value="transparent"></param><embed src="http://www.youtube.com/v/$1" type="application/x-shockwave-flash" wmode="transparent" width="425" height="350"></embed></object>',
				
				'~\[youtube\](https?://(www.)?youtube.com/.+?)\[/youtube\]~i' => '<object width="425" height="350"><param name="movie" value="$1"></param><param name="wmode" value="transparent"></param><embed src="$1" type="application/x-shockwave-flash" wmode="transparent" width="425" height="350"></embed></object>',
				
				// bilder
				'~\[img\]([^\["\'\n]+)\[/img\]~ie' => 'game::secure_img_addr(\'$1\')',
				
				// internettadresser
				'~(?<=[!>:\?\.\s\xA0[\]()*\\\;]|^)((?:https?|ftp)://([\w\d/=;\\\?#\\-%:@+æøå\\~]|[,.](?! )|&amp;)+)~i' => '<a href="$1" target="_blank">$1</a>',
				'~(?<=[!>:\?\.\s\xA0[\]()*\\\;]|^)(www\.([\w\d/=;\\\?#\\-%:@+æøå\\~]|[,.](?! )|&amp;)+)~i' => '<a href="http://$1" target="_blank">$1</a>',
				
				// intern adresse på nettstedet
				'~\[iurl=/?([^\]\n]*)\](.+?)\[/iurl\]~ie' => '\'<a href="'.$__server['absolute_path'].'/$1">\'.stripslashes(\'$2\').\'</a>\'',
				
				// brukere
				'~\[user=([0-9a-zA-Z\-_ ]+)\]~i' => '<user="$1" />',
				'~\[user id=([0-9]+)\]~i' => '<user id="$1" />',
				
				// firma/familie-lenke
				'~\[ff=([0-9]+)\]~' => '<ff_link>$1</ff_link>',
				
				// hr
				'~\[hr\](\n)?~i' => '<div class="hr"></div>'
			);
			
			// kode som kan kjøres flere ganger
			$replaces_multiple = array(
				// headers
				'~(?:\n){0,2}\[h([1-6])\](.+?)\[/h\1\]\n{0,2}~i' => '<h$1>$2</h$1>',
				
				// bold
				'~\[b\](.+?)\[/b\]~is' => '<b>$1</b>',
				
				// italic
				'~\[i\](.+?)\[/i\]~is' => '<i>$1</i>',
				
				// understrek
				'~\[u\](.+?)\[/u\]~is' => '<u>$1</u>',
				
				// midtstrek
				'~\[s\](.+?)\[/s\]~is' => '<del>$1</del>',
				
				// tekstjustering
				'~\[left=([0-5]?[0-9]{1,2})(?:px)?\](.+?)\[/left\](\n)?~is' => '<div class="l" style="margin-left:$1px">$2</div>',
				'~\[right=([0-5]?[0-9]{1,2})(?:px)?\](.+?)\[/right\](\n)?~is' => '<div class="r" style="margin-right:$1px">$2</div>',
				'~\[left=([0-9]{1,2})%\](.+?)\[/left\](\n)?~is' => '<div class="l" style="margin-left:$1%">$2</div>',
				'~\[right=([0-9]{1,2})%\](.+?)\[/right\](\n)?~is' => '<div class="r" style="margin-right:$1%">$2</div>',
				'~\[left\](.+?)\[/left\](\n)?~is' => '<div class="l">$1</div>',
				'~\[right\](.+?)\[/right\](\n)?~is' => '<div class="r">$1</div>',
				'~\[center\](.+?)\[/center\](\n)?~is' => '<div class="c">$1</div>',
				'~\[justify\](.+?)\[/justify\](\n)?~is' => '<div class="j">$1</div>',
				
				// float og clear
				'~\[float=(right)\](.+?)\[/float\](\n)?~is' => '<div style="float:$1;margin-left: 5px">$2</div>',
				'~\[float=(left)\](.+?)\[/float\](\n)?~is' => '<div style="float:$1;margin-right: 5px">$2</div>',
				'~\[clear\]~i' => '<div style="clear:both"></div>',
				
				// fast bredde
				'~\[width=([0-6]?[0-9]{1,2})(?:px)?\](.+?)\[/width\](\n)?~is' => '<div style="width:$1px">$2</div>',
				
				// lister
				'~\n?\[ul\](?:.+?)(\[li\].+?)\n*\[/ul\]\n{0,2}~is' => '<ul>$1</ul>',
				'~\n*\[li\]\n?(.+?)\n?\[/li\][^\[<]*~is' => '<li>$1</li>$2',
				
				
				// farger
				'~\[color=(SM|bakgrunn)\](.*?)\[/color\]~is' => '<span style="color: #222222;">$2</span>',
				'~\[color=(#[\da-fA-F]{3}|#[\da-fA-F]{6}|[\w]{1,12})\](.*?)\[/color\]~is' => '<span style="color: $1;">$2</span>',
				'~\[(black|white|red|green|blue)\](.+?)\[/\1\]~is' => '<span style="color: $1;">$2</span>',
				
				// quote
				'~\[quote]\n?(.+?)\n?\[/quote\]\n?~is' => '<div class="quote_box"><span class="quote_header">Sitat:</span>$1</div>',
				
				// senket/hevet skrift
				'~\[sub\](.+?)\[/sub\]~is' => '<sub>$1</sub>',
				'~\[sup\](.+?)\[/sup\]~is' => '<sup>$1</sup>',
				
				// skriftstørrelse
				'~\[size=(1?[\d]{1,2}p[xt]|(?:x-)?small(?:er)?|(?:x-)?large[r]?)\](.+?)\[/size\]~is' => '<span style="font-size: $1;">$2</span>',
				'~\[size=([\d])\](.+?)\[/size\]~is' => '<font size="$1">$2</font>',
			);
			
			$code_from_cache = array_keys($replaces_multiple);
			$code_to_cache = array_values($replaces_multiple);
			
			$code_from_cache_single = array_keys($replaces_single);
			$code_to_cache_single = array_values($replaces_single);
		}
		
		// fiks bb-koder som kun skal kjøres en gang
		$bb = preg_replace($code_from_cache_single, $code_to_cache_single, $bb);
		
		// fiks liste med *
		$matches = false;
		if (preg_match_all("~(?:\n{0,2})^ \\* .+(\n \\* .+)*$(?:\n{0,2})~m", $bb, $matches))
		{
			foreach ($matches[0] as $match)
			{
				$row = explode("\n", $match);
				$html = '<ul>';
				
				foreach ($row as $r)
				{
					$r = substr($r, 3);
					if (empty($r)) continue;
					$html .= '<li>'.$r.'</li>';
				}
				
				$html .= '</ul>';
				
				$bb = str_replace($match, $html, $bb);
			}
		}
		
		// fiks liste med #
		$matches = false;
		if (preg_match_all("~(?:\n{0,2})^ # .+(\n # .+)*$(?:\n{0,2})~m", $bb, $matches))
		{
			foreach ($matches[0] as $match)
			{
				$row = explode("\n", $match);
				$html = '<ol>';
				
				foreach ($row as $r)
				{
					$r = substr($r, 3);
					if (empty($r)) continue;
					$html .= '<li>'.$r.'</li>';
				}
				
				$html .= '</ol>';
				
				$bb = str_replace($match, $html, $bb);
			}
		}
		
		// fiks bb-koder som kan oppstå flere ganger inni hverandre (recursive)
		$count = 0;
		while (($bb = preg_replace($code_from_cache, $code_to_cache, $bb, -1, $count)) && $count > 0);
		
		// fiks smileys
		$bb = game::smileys($bb);
		
		// sett tilbake [nobb] innhold
		$bb = self::nobb_replace($bb);
		
		// sett tilbake [linkonly] innhold
		$bb = self::linkonly_replace($bb);
		
		// fiks linjeskift og returner
		return str_replace("\n", "<br />\n", $bb);
	}
	
	public static function smileys($text)
	{
		static $smileys_from_cache = array();
		static $smileys_to_cache = array();
		
		// ordne smileys
		if (empty($smileys_from_cache)) {
			global $_smileys, $__server;
			foreach ($_smileys as $from => $to)
			{
				$smileys_from_cache[] = '/(?<=[!>:\?\.\s\xA0[\]()*\\\;]|^)(' . preg_quote($from, '/') . '|' . preg_quote(htmlspecialchars($from, ENT_QUOTES), '/') . ')(?=[^\]A-Za-z0-9"]|$)/i';
				$smileys_to_cache[] = '<img src="'.$to.'" alt="'.htmlspecialchars($from).'" />';
			}
		}
		return preg_replace($smileys_from_cache, $smileys_to_cache, $text);
	}
	
	
	/**
	 * Generer HTML tag for counter (timespan)
	 * @param int $time
	 * @param bool/string $redirect = NULL
	 */
	public static function counter($time, $redirect = NULL)
	{
		$rel = (string) $time;
		if ($redirect)
		{
			if ($redirect === true) $rel .= ",refresh";
			else $rel .= ",".htmlspecialchars($redirect);
		}
		return '<span class="counter" rel="'.$rel.'">'.self::timespan($time, game::TIME_FULL, 5).'</span>';
	}
	
	// konstanter for game::timespan()
	const TIME_FULL = 1; // sekunder
	const TIME_PARTIAL = 2; // sek
	const TIME_SHORT = 4; // s
	const TIME_PAST = 8;
	const TIME_FUTURE = 16;
	const TIME_NOBOLD = 32;
	const TIME_ALL = 64;
	const TIME_ABS = 128;
	
	/**
	 * Kalkuler hvor lang tid noe tar/har tatt
	 * @param integer $secs antall sekunder (eller tidspunkt hvis TIME_ABS er satt)
	 * @param integer $modifiers (standard: TIME_PARTIAL, TIME_FUTURE
	 */
	public static function timespan($secs, $modifiers = 0, $max = 2)
	{
		global $_lang;
		
		// kalkulere tiden?
		if ($modifiers & self::TIME_ABS)
		{
			if ($secs == 0)
			{
				return 'ikke tilgjengelig';
			}
			
			$secs = abs(time() - $secs);
		}
		
		$secs = round($secs);
		
		// begrens til $max egenskaper
		$data = array();
		
		// antall minutter
		if ($secs > 59)
		{
			// antall timer
			if ($secs > 3599)
			{
				// antall dager
				if ($secs > 86399)
				{
					// antall uker
					if ($secs > 604799)
					{
						$ant = floor($secs / 604800);
						$data["weeks"] = $ant;
						$secs -= $ant * 604800;
					}
					
					// dager
					$ant = floor($secs / 86400);
					if ($ant > 0 || $modifiers & self::TIME_ALL) $data["days"] = $ant;
					$secs -= $ant * 86400;
				}
				
				// timer
				$ant = floor($secs / 3600);
				if ($ant > 0 || $modifiers & self::TIME_ALL) $data["hours"] = $ant;
				$secs -= $ant * 3600;
			}
			
			// minutter
			$ant = floor($secs / 60);
			if ($ant > 0 || $modifiers & self::TIME_ALL) $data["minutes"] = $ant;
			$secs -= $ant * 60;
		}
		
		// sekunder
		if ($secs > 0 || ($modifiers & self::TIME_ALL && count($data) > 0)) $data["seconds"] = $secs;
		
		$data = array_slice($data, 0, $max, true);
		$ret = array();
		$bold = !($modifiers & self::TIME_NOBOLD);
		$type = $modifiers & self::TIME_SHORT ? 'short' : ($modifiers & self::TIME_FULL ? 'full' : 'partial');
		$typesplit = $modifiers & self::TIME_SHORT ? '' : ' ';
		foreach ($data as $i => $v)
		{
			$ret[] = ($bold ? "<b>$v</b>" : $v).$typesplit.$_lang[$i][$type][$v == 1 ? 0 : 1];
		}
		
		$timetype = count($ret) > 0 && $modifiers & self::TIME_PAST ? ' siden' : '';
		if (count($ret) == 0) $ret = array("akkurat nå");
		$last = array_pop($ret); 
		$lastsplit = $type == "full" ? ' og ' : ' ';
		return (count($ret) > 0 ? implode(" ", $ret) . $lastsplit : '') . $last . $timetype;
	}
	
	// regne ut koordinatlengder fra et punkt til et annet, og legge til en margin hvis man ønsker..
	public static function coord_distance($x1, $y1, $x2, $y2, $offset = 1)
	{
		$from_y = deg2rad($y1);
		$from_x = deg2rad($x1);
		$to_y = deg2rad($y2);
		$to_x = deg2rad($x2);

		$p = $from_x - $to_x;
		if ($p > 180) $p = 360 - $p;

		$d = rad2deg(acos(sin($from_y)*sin($to_y) + cos($from_y)*cos($to_y)*cos($p))) * 111.12;

		// legg til offset og returner
		return $d * $offset;
	}
	
	// sjekk for gyldig e-postadresse
	public static function validemail($address)
	{
		return preg_match("/^[a-zA-Z_\\-][\\w\\.\\-_]*[a-zA-Z0-9_\\-]@[a-zA-Z0-9][\\w\\.-]*[a-zA-Z0-9]\\.[a-zA-Z][a-zA-Z\\.]*[a-zA-Z]$/Di", $address);
	}


	// bygg opp adresse
	public static function address($path, $get = array(), $exclude = array(), $add = array())
	{
		// fjern evt. querystring fra path
		if (($pos = strpos($path, "?")) !== false)
		{
			$path = substr($path, 0, $pos);
		}
		
		foreach ($exclude as $name) unset($get[$name]);
		foreach ($add as $name => $value) $get[$name] = $value;
		
		$querystring = array();
		
		foreach ($get as $name => $value)
		{
			game::build_query_string($name, $value, $querystring);
		}
		
		$querystring = count($querystring) > 0 ? "?".implode("&", $querystring) : '';
		return $path . $querystring;
	}
	
	public static function build_query_string($name, $value, &$result)
	{
		$name = urlencode($name);

		if ($value === true || $value === "")
		{
			$result[] = $name;
		}
		elseif (is_array($value))
		{
			game::build_query_string_array($name, $value, $result);
		}
		else
		{
			$result[] = $name . '=' . urlencode($value);
		}

		return $result;
	}

	public static function build_query_string_array($prefix, $values, &$result)
	{
		foreach ($values as $name => $value)
		{
			$name = $prefix.'['.urlencode($name).']';
			if ($value === true || $value === "")
			{
				$result[] = $name;
			}
			elseif (is_array($value))
			{
				game::build_query_string_array($name, $value, $result);
			}
			else
			{
				$result[] = $name . '=' . urlencode($value);
			}
		}
	}
	
	/**
	 * Kalkuler rankforskjell
	 * @param player $up1 spiller 1
	 * @param player $up2 spiller 2
	 */
	public function calc_rank_diff(player $up1, player $up2)
	{
		return $up2->rank['number'] - $up1->rank['number'];
	}
	
	/**
	 * Kalkuler spesiell rankforskjell (rank avhengig av posisjon)
	 * @param player $up1 spiller 1
	 * @param player $up2 spiller 2
	 */
	public function calc_specrank_diff(player $up1, player $up2)
	{
		// beregn spiller 1 sin spesialrank
		$r1 = 0;
		if ($up1->rank['pos_id'] != 0)
		{
			// beregn hvilket nummer spilleren er på spesialrank (0 = ingen, 1 = nest nederste osv)
			$r1 = count(game::$ranks['pos']) - game::$ranks['pos'][$up1->rank['pos_id']]['number'] + 1;
		}
		
		// beregn spiller 2 sin spesialrank
		$r2 = 0;
		if ($up2->rank['pos_id'] != 0)
		{
			// beregn hvilket nummer spilleren er på spesialrank (0 = ingen, 1 = nest nederste osv)
			$r2 = count(game::$ranks['pos']) - game::$ranks['pos'][$up2->rank['pos_id']]['number'] + 1;
		}
		
		return $r2 - $r1;
	}
	
	/**
	 * Hent ut antall aktive auksjoner
	 */
	public static function auksjoner_active_count()
	{
		$auksjoner = cache::fetch("auksjoner_active");
		if (!$auksjoner) return 0;
		
		$active = 0;
		$time = time();
		foreach ($auksjoner as $row)
		{
			if ($row[0] <= $time && $row[1] >= $time) $active++;
		}
		
		return $active;
	}
	
	/**
	 * Finn antall spillere i fengsel
	 */
	public static function fengsel_count()
	{
		$result = ess::$b->db->query("SELECT COUNT(up_id) FROM users_players WHERE up_fengsel_time > ".time());
		return mysql_result($result, 0);
	}
	
	/**
	 * Hent beste ranker siste 24 timer
	 */
	public static function get_best_rankers($limit = null)
	{
		$limit = (int) ($limit ?: 1);
		
		// tidsperiode
		$d = ess::$b->date->get();
		$a = $d->format("H") < 21 ? 2 : 1;
		$d->modify("-$a day");
		$d->setTime(21, 0, 0);
		$date_from = $d->format("U");
		
		$d->modify("+1 day");
		$date_to = $d->format("U");
		
		// hent spiller
		$result = ess::$b->db->query("
			SELECT up_id, up_name, up_access_level, sum_uhi_points, up_points, up_last_online, up_profile_image_url, upr_rank_pos
			FROM
				(
					SELECT uhi_up_id, SUM(uhi_points) sum_uhi_points
					FROM users_hits
					WHERE uhi_secs_hour >= $date_from AND uhi_secs_hour < $date_to
					GROUP BY uhi_up_id
					HAVING sum_uhi_points > 0
					ORDER BY sum_uhi_points DESC
					LIMIT $limit
				) ref,
				users_players
				LEFT JOIN users_players_rank ON upr_up_id = up_id
			WHERE uhi_up_id = up_id");
		
		if (mysql_num_rows($result) == 0) return array();
		
		$players = array();
		$up_id = array();
		while ($row = mysql_fetch_assoc($result))
		{
			$players[$row['up_id']] = $row;
			$up_id[] = $row['up_id'];
		}
		
		// hent familier hvor spilleren er medlem
		$ff = ff::get_ff_list($up_id, ff::TYPE_FAMILIE);
		foreach ($ff as $row)
		{
			$players[$row['ffm_up_id']]['ff'][] = $row;
			$players[$row['ffm_up_id']]['ff_links'][] = $row['link'];
		}
		
		return $players;
	}
}
