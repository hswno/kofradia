<?php

use \Kofradia\DB;

/**
 * Spesifikk anti-bot test
 */
class antibot
{
	public $u_id;
	public $name;
	public $data;
	public $span;
	
	/**
	 * Hent en spesifikk test
	 * @return antibot
	 */
	public static function get($name, $span = NULL)
	{
		if (!login::$logged_in) throw new HSException("Ingen bruker.");
		return new antibot(login::$user->id, $name, $span);
	}
	
	/**
	 * Hent inn test
	 */
	public function __construct($u_id, $name, $span = NULL)
	{
		$this->u_id = (int) $u_id;
		$this->name = $name;
		$this->span = abs($span);
		
		$result = \Kofradia\DB::get()->query("SELECT id, count, count_last, span, last_try FROM users_antibot WHERE ua_u_id = $this->u_id AND name = ".\Kofradia\DB::quote($name));
		$this->data = $result->fetch();
	}
	
	/**
	 * Øk telleren
	 */
	public function increase_counter()
	{
		$span = $this->span;
		if ($span == 0) throw new HSException("Ugyldig span.");
		
		if (!$this->data)
		{
			// opprett
			\Kofradia\DB::get()->exec("INSERT INTO users_antibot SET ua_u_id = $this->u_id, name = ".\Kofradia\DB::quote($this->name).", span = $span, count = 1");
			$this->data = array(
				"id" => \Kofradia\DB::get()->lastInsertId(),
				"name" => $this->name,
				"count" => 1,
				"count_last" => 0,
				"span" => $span,
				"last_try" => NULL
			);
			return;
		}
		
		$set = $span != $this->data['span'] ? ", span = $span" : "";
		\Kofradia\DB::get()->exec("UPDATE users_antibot SET count = count + 1$set WHERE ua_u_id = $this->u_id AND name = ".\Kofradia\DB::quote($this->name));
	}
	
	/**
	 * Avbryt anti-bot (setter ned telleren)
	 */
	protected function abort()
	{
		\Kofradia\DB::get()->exec("UPDATE users_antibot SET count = count_last WHERE ua_u_id = $this->u_id AND name = ".\Kofradia\DB::quote($this->name));
	}
	
	/**
	 * Sjekk om test er nødvendig
	 */
	public function is_check_required()
	{
		// finnes ikke?
		if (!$this->data) return false;
		
		// span endret?
		if ($this->span && $this->span != $this->data['span'])
		{
			$this->data['span'] = $this->span;
			if ($this->data['span'] == 0) throw new HSException("Ugyldig span");
			\Kofradia\DB::get()->exec("UPDATE users_antibot SET span = {$this->data['span']} WHERE id = {$this->data['id']}");
		}
		
		// trenger test?
		if ($this->data['count'] != $this->data['count_last'] && $this->data['count'] % $this->data['span'] == 0)
		{
			return true;
		}
	}
	
	/**
	 * Send videre til siden for anti-bot test om nødvendig
	 */
	public function check_required($redirect = NULL)
	{
		// trenger ikke test?
		if (!$this->is_check_required()) return;
		
		// oppdater anti-bot status
		$this->update_status("redir");
		
		if (!$redirect) $redirect = $_SERVER['REQUEST_URI'];
		$ret = "&ret=".urlencode($redirect);
		redirect::handle("/antibot/sjekk?name=".urlencode($this->name).$ret, redirect::ROOT);
	}
	
	/**
	 * Oppdater tid for forsøk
	 */
	public function update_time()
	{
		\Kofradia\DB::get()->exec("UPDATE users_antibot SET last_try = ".time()." WHERE id = {$this->data['id']}");
	}
	
	/**
	 * Hent bildene
	 */
	public function get_images()
	{
		$images = array(
			"data" => array(),
			"info" => array(),
			"valid" => 0
		);
		$result = \Kofradia\DB::get()->query("SELECT id, imgnum, valid, time, data FROM users_antibot_validate WHERE antibotid = {$this->data['id']}");
		while ($row = $result->fetch())
		{
			if ($row['valid']) $images['valid']++;
			
			$images['data'][$row['imgnum']] = $row['data'];
			unset($row['data']);
			
			$images['info'][$row['imgnum']] = $row;
		}
		
		return $images;
	}
	
	/**
	 * Generer bilder
	 */
	public function generate_images($first = NULL)
	{
		// slett gamle bilder
		\Kofradia\DB::get()->exec("DELETE FROM users_antibot_validate WHERE antibotid = {$this->data['id']}");
		
		// lås anti-boten
		\Kofradia\DB::get()->beginTransaction();
		\Kofradia\DB::get()->query("SELECT id FROM users_antibot WHERE id = {$this->data['id']} FOR UPDATE")->closeCursor();
		
		// har noen bilder nå?
		$result = \Kofradia\DB::get()->query("SELECT COUNT(*) FROM users_antibot_validate WHERE antibotid = {$this->data['id']}");
		if ($result->fetchColumn(0) > 0)
		{
			\Kofradia\DB::get()->commit();
			return $this->get_images();
		}

		$images = array(
			"data" => array(),
			"info" => array(),
			"valid" => 0
		);
		
		// generer bildene
		$data = antibot_generate::image_create_all(2, 6);
		$time = time();
		
		// opprett bildene og putt i databasen
		foreach ($data as $key => $image)
		{
			$imgnum = $key + 1;
			
			// legg til i databasen
			$valid = $image['valid'] ? '1' : '0';
			\Kofradia\DB::get()->exec("INSERT INTO users_antibot_validate SET antibotid = {$this->data['id']}, imgnum = $imgnum, valid = $valid, time = $time, data = ".\Kofradia\DB::quote($image['data']));
			
			// legg til i $antibot
			$images['info'][$imgnum] = array("time" => $time, "valid" => $valid);
			$images['data'][$imgnum] = $image['data'];
			if ($valid) $images['valid']++;
		}
		
		// avslutt lås
		\Kofradia\DB::get()->commit();
		
		return $images;
	}
	
	/**
	 * Slett bilder
	 */
	public function delete_images()
	{
		// slett anti-bot bildene
		\Kofradia\DB::get()->exec("DELETE FROM users_antibot_validate WHERE antibotid = {$this->data['id']}");
	}
	
	/**
	 * Anti-bot test ble utført vellykket
	 */
	public function valid()
	{
		// var dette anti-bot for kuler?
		if ($this->name == "kuler")
		{
			 $this->kuler();
		}
		
		// sett count_last til count
		\Kofradia\DB::get()->exec("UPDATE users_antibot SET count_last = count WHERE id = {$this->data['id']}");
		
		$this->delete_images();
		$this->update_status("success");
	}
	
	/**
	 * Antall kuler som skal kjøpes
	 */
	protected $kuler_num;
	
	/**
	 * Når kulene må være kjøpt
	 */
	public $kuler_time_left;
	
	/**
	 * Pre-check for kuler
	 */
	public function kuler_precheck()
	{
		$time = time();
		
		// sjekk om vi fremdeles kan kjøpe kulene
		$result = \Kofradia\DB::get()->query("
			SELECT COUNT(*)
			FROM bullets
			WHERE bullet_freeze_up_id = ".login::$user->player->id." AND bullet_freeze_time > $time");
		
		// ingen kuler?
		$this->kuler_num = $result->fetchColumn(0);
		if ($this->kuler_num == 0)
		{
			putlog("LOG", "KJØPE KULER: ".login::$user->player->data['up_name']." var for treg med å utføre anti-bot for å kjøpe kuler");
			ess::$b->page->add_message("Du var for treg og kulene du ønsket å kjøpe var ikke lenger tilgjengelig.", "error");
			
			$this->abort();
			
			return false;
		}
		
		// finn ut hvor lang tid vi har på oss
		$result = \Kofradia\DB::get()->query("
			SELECT bullet_freeze_time
			FROM bullets
			WHERE bullet_freeze_up_id = ".login::$user->player->id." AND bullet_freeze_time > $time
			ORDER BY bullet_freeze_time
			LIMIT 1");
		$row = $result->fetch();
		if ($row)
		{
			$this->kuler_time_left = $row['bullet_freeze_time'] - $time;
		}
		
		return true;
	}
	
	/**
	 * Behandle anti-bot for kuler
	 */
	protected function kuler()
	{
		// utføre precheck?
		if (!$this->kuler_num)
		{
			if (!$this->kuler_precheck()) return;
		}
		
		$time = time();
		\Kofradia\DB::get()->beginTransaction();
		
		$price = $this->kuler_num * login::$user->player->weapon->data['bullet_price'];
		
		// trekk fra pengene og sjekk samtidig om vi faktisk hadde nok penger
		$affected = \Kofradia\DB::get()->exec("UPDATE users_players SET up_cash = up_cash - $price WHERE up_id = ".login::$user->player->id." AND up_cash >= $price");
		if ($affected == 0)
		{
			ess::$b->page->add_message("Du har ikke nok penger på hånda. For å kjøpe $this->kuler_num ".fword("kule", "kuler", $this->kuler_num)." må du ha ".game::format_cash($price)." på hånda. Kulene ble gjort tilgjengelig for alle igjen.", "error");
			
			\Kofradia\DB::get()->exec("UPDATE bullets SET up_freeze_up_id = NULL, up_freeze_time = 0 WHERE up_freeze_up_id = ".login::$user->player->id);
			\Kofradia\DB::get()->commit();
			
			return;
		}
		
		// forsøk å skaff alle kulene
		$affected = \Kofradia\DB::get()->exec("
			DELETE FROM bullets
			WHERE bullet_freeze_up_id = ".login::$user->player->id." AND bullet_freeze_time > $time
			ORDER BY bullet_time
			LIMIT $this->kuler_num");
		
		// feil antall kuler anskaffet?
		if ($affected != $this->kuler_num)
		{
			// reverser transaksjon
			\Kofradia\DB::get()->rollback();
			
			// informer
			putlog("DF", "KJØPE KULER: ".login::$user->player->data['up_name']." var for treg med å utføre anti-bot for å kjøpe kuler");
			ess::$b->page->add_message("Du var for treg og kulene du ønsket å kjøpe var ikke lenger tilgjengelig.", "error");
			
			return;
		}
		
		// gi kulene til spilleren
		\Kofradia\DB::get()->exec("UPDATE users_players SET up_weapon_bullets = up_weapon_bullets + $this->kuler_num WHERE up_id = ".login::$user->player->id);
		\Kofradia\DB::get()->commit();
		
		// logg
		putlog("DF", "KJØPE KULER: ".login::$user->player->data['up_name']." kjøpte $this->kuler_num kuler for totalt ".game::format_cash($price));
		
		// informer
		ess::$b->page->add_message("Du kjøpte $this->kuler_num ".fword("kule", "kuler", $this->kuler_num)." for ".game::format_cash($price).".");
	}
	
	/**
	 * Oppdater anti-bot status - for å loggføre de som bruker lang tid mellom anti-bot handlingene
	 */
	public function update_status($step, $param = NULL)
	{
		global $__server;
		$n = time();
		
		// steps:
		//   redir
		//   new_img
		//   new_img_wait
		//   test_init
		//   test_repeat
		//   failed
		//   success
		
		// har vi noe status fra før?
		if (isset($_SESSION[$__server['session_prefix'].'antibot_status'][$this->name]))
		{
			$p = array_reverse($_SESSION[$__server['session_prefix'].'antibot_status'][$this->name]);
			
			if (count($p) > 6)
			{
				putlog("ABUSE", "%c10%bANTIBOT-LOG%b: %u".login::$user->player->data['up_name']."%u har utført mange forskjellige handlinger på rad uten å ha fullført anti-boten (%u{$this->name}%u) - handlingslogg:");
				putlog("ABUSE", "%c10%bANTIBOT-LOG%b: $step");
				$time_last = $n;
				foreach ($p as $row)
				{
					$time = $time_last - $row['time'];
					$time_last = $row['time'];
					 
					putlog("ABUSE", "%c10%bANTIBOT-LOG%b: $time sekunder --> {$row['step']}");
				}
				
				$_SESSION[$__server['session_prefix'].'antibot_status'][$this->name] = array_slice($_SESSION[$__server['session_prefix'].'antibot_status'][$this->name], -2);
			}
			
			$last = $p[0];
			$last_time = $n - $last['time'];
			
			// utført anti-bot, men forrige handling var ikke init/repeat?
			if ($step == "success" && $last['step'] != "test_init" && $last['step'] != "test_repeat")
			{
				putlog("ABUSE", "%c10%bANTIBOT-LOG%b: %u".login::$user->player->data['up_name']."%u utførte anti-bot vellykket, men forrige handling var ikke å vise anti-bot testen ($last_time sekunder siden forrige handling: {$last['step']}) (%u{$this->name}%u)");
			}
			
			// brukt lang tid på å utføre anti-boten?
			elseif ($last_time > 60 && $step == "success")
			{
				putlog("ABUSE", "%c10%bANTIBOT-LOG%b: %u".login::$user->player->data['up_name']."%u utførte anti-bot vellykket, men brukte lang tid ($last_time sekunder siden forrige handling: {$last['step']}) (%u{$this->name}%u)");
			}
		}
		
		// fjerne?
		if ($step == "success")
		{
			unset($_SESSION[$__server['session_prefix'].'antibot_status'][$this->name]);
		}
		
		// legg til
		else
		{
			$_SESSION[$__server['session_prefix'].'antibot_status'][$this->name][] = array("step" => $step, "time" => time());
		}
		
		switch ($step)
		{
			case "redir":
				// trenger sjekk
				putlog("ANTIBOT", "%c10%bVIDERESENDING%b: %u".login::$user->player->data['up_name']."%u blir nå videresendt til anti-bot testen %u{$this->name}%u");
			break;
			
			case "new_img_wait":
				putlog("ANTIBOT", "%c8%bNYE BILDER%b: %u".login::$user->player->data['up_name']."%u ba om nye bilder for %u{$this->name}%u men må vente %u$param%u sekunder");
			break;
			
			case "new_img":
				putlog("ANTIBOT", "%c9%bNYE BILDER%b: %u".login::$user->player->data['up_name']."%u ba om nye bilder for %u{$this->name}%u");
			break;
			
			case "test_repeat":
				putlog("ANTIBOT", "%c13%bSJEKK%b: %u".login::$user->player->data['up_name']."%u viste anti-boten for %u{$this->name}%u på nytt");
			break;
			
			case "test_init":
				putlog("ANTIBOT", "%bSJEKK OPPRETT BILDER%b: %u".login::$user->player->data['up_name']."%u opprettet anti-bot bilder for %u{$this->name}%u");
			break;
			
			case "failed":
				putlog("ANTIBOT_ERROR", "%c4%bSJEKK MISLYKKET%b: %u".login::$user->player->data['up_name']."%u mislykket anti-boten ({$this->name}) med %u$param%u ".fword("riktig alternativ", "riktige alternativer", $param));
			break;
			
			case "success":
				putlog("ANTIBOT", "%bSJEKK VELLYKKET%b: %u".login::$user->player->data['up_name']."%u utførte anti-boten for %u{$this->name}%u");
			break;
		}
	}
}


antibot_generate::init();
class antibot_generate
{
	/** Gyldige bilder */
	public static $dir_valid;
	
	/** Ugyldige bilder */
	public static $dir_invalid;
	
	/** Vannmerket (PNG) */
	public static $watermark_src;
	
	/** Rotere bildene */
	const ROTATE = true;
	
	/** Benytte vannmerke */
	const WATERMARK = true;
	
	/** Init */
	public static function init()
	{
		self::$dir_valid = ANTIBOT_FOLDER . "/positive";
		self::$dir_invalid = ANTIBOT_FOLDER . "/negative";
		self::$watermark_src = ANTIBOT_FOLDER . "/fargefilter.png";
	}
	
	/**
	 * Generer samling med bilder (raw)
	 */
	public static function image_create_all($num_valid, $num_total)
	{
		// finn tilfeldige bilder
		$arr = range(1, 6);
		$tilfeldige = array_rand($arr, $num_valid);
		
		// finn liste over filer
		$files_valid = self::dir_list(self::$dir_valid);
		$files_invalid = self::dir_list(self::$dir_invalid);
		
		$images = array();
		for ($i = 0; $i < $num_total; $i++)
		{
			$valid = in_array($i, $tilfeldige);
			$images[] = array(
				"valid" => $valid,
				"data" => self::image_create($valid,
						$valid ? $files_valid : $files_invalid,
						$valid ? self::$dir_valid : self::$dir_invalid));
		}
		
		return $images;
	}
	
	/**
	 * Hent liste over alle bildene i en mappe
	 */
	protected function dir_list($dir)
	{
		$bilder = array();
		if ($dh = @opendir($dir))
		{
			while (($file = readdir($dh)) !== false)
			{
				// er det et gyldig bilde?
				if (self::image_type($file))
				{
					$bilder[] = $file;
				}
			}
			closedir($dh);
		}
		return $bilder;
	}
	
	/** Sjekk for gyldig bildetype */
	protected function image_type($name)
	{
		$ext = mb_substr($name, mb_strrpos($name, ".")+1);
		if ($ext == "jpg" || $ext == "jpeg") return "jpeg";
		elseif ($ext == "png") return "png";
		elseif ($ext == "gif") return "gif";
		return false;
	}
	
	/** Generer raw for et bilde */
	protected function image_raw($img)
	{
		$pre = ob_get_contents();
		@ob_clean();
		
		imagejpeg($img, null, 100);
		$data = ob_get_contents();
		@ob_clean();
		
		echo $pre;
		imagedestroy($img);
		
		return $data;
	}
	
	/** Generer et bilde */
	protected function image_create($valid, $images, $dir)
	{
		// ingen bilder?
		if (count($images) == 0)
		{
			// lag et hvitt bilde med tekst på
			$text = $valid ? 'Gyldig bilde' : 'Ikke gyldig';
			
			// opprett bilde
			$img = imagecreatetruecolor(90, 65);
			if (!$img) die("error");
			
			// farger
			$color_bg = imagecolorallocate($img, 255, 255, 255);
			$color_text = imagecolorallocate($img, 0, 0, 0);
			
			// legg til bakgrunn og tekst
			imagefill($img, 0, 0, $color_bg);
			imagestring($img, $valid ? 3 : 2, $valid ? 3 : 10, 28, $text, $color_text);
			
			return self::image_raw($img);
		}
		
		// velg et tilfeldig bilde
		$file = $images[array_rand($images)];
		$type = self::image_type($file);
		
		// åpne bildet
		$source = call_user_func("imagecreatefrom".$type, "$dir/$file");
		if (!$source) die("image create error");
		
		// finn størrelse
		$source_width = imagesx($source);
		$source_height = imagesy($source);
		
		// roter
		if (self::ROTATE) {
			$roter = rand(-10, 10);
			$bg = imagecolorallocate($source, 255, 255, 255);
			$source = imagerotate($source, $roter, $bg);
			
			// resample
			$rotated = imagecreatetruecolor($source_width, $source_height);
			$x = floor((imagesx($source) - $source_width) / 2) + 5;
			$y = floor((imagesy($source) - $source_height) / 2) + 5;
			imagecopyresampled($rotated, $source, 0, 0, $x, $y, $source_width, $source_height, $source_width - 10, $source_height - 10);
			imagedestroy($source);
		} else {
			$rotated = $source;
		}
		
		// hvor mye skal bildet beveges horisontalt or vertikalt?
		$beveg_x = rand(-10, 0);
		$beveg_y = rand(-10, 0);
		
		// opprett nytt bilde
		$forstorr_x = rand(0, 10);
		$forstorr_y = rand(0, 10);
		$width = $source_width - 10;// + $forstorr;
		$height = $source_height - 10;// + $forstorr;
		$image = imagecreatetruecolor($width, $height);
		$red = imagecolorallocate($image, 255, 0, 0);
		imagefill($image, 0, 0, $red);
		
		// kopier source til dest.
		imagecopyresampled($image, $rotated, $beveg_x, $beveg_y, 0, 0, $source_width + $forstorr_x, $source_height + $forstorr_y, $source_width, $source_height);
		
		// slett source fra minnet
		imagedestroy($rotated);
		
		
		// legger på watermark
		if (self::WATERMARK)
		{
			$watermark = imagecreatefrompng(self::$watermark_src);
			
			$wm_width = imagesx($watermark);
			$wm_height = imagesy($watermark);
			
			if (self::ROTATE)
			{
				// roter vannmerke
				$roter = rand(0, 359);
				$bg = imagecolorallocate($watermark, 255, 255, 255);
				$watermark = imagerotate($watermark, $roter, $bg);
				
				// resample
				$rotated = imagecreatetruecolor($wm_width, $wm_height);
				imagealphablending($rotated, false);
				imagesavealpha($rotated, true);
				$x = floor((imagesx($watermark) - $wm_width) / 2);
				$y = floor((imagesy($watermark) - $wm_height) / 2);
				imagecopyresampled($rotated, $watermark, 0, 0, $x, $y, $wm_width, $wm_height, $wm_width, $wm_height);
				imagedestroy($watermark);
				$watermark = $rotated;
			}
			
			// plasser i midten
			$x = ($width - $wm_width) / 2;
			$y = ($height - $wm_height) / 2;
			
			// flytt tilfeldig (-/+ 30)
			$x += rand(-30, 30);
			$y += rand(-30, 30);
			
			// legg til watermark
			imagecopy($image, $watermark, $x, $y, 0, 0, $wm_width, $wm_height);
		}
		
		return self::image_raw($image);
	}
}
