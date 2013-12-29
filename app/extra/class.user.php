<?php

use \Kofradia\Users\Contact;

/**
 * Brukersystemet
 */
class user
{
	/** Samling av brukerobjekter */
	protected static $users = array();
	
	/** ID til brukeren */
	public $id;
	
	/** Data om brukeren */
	public $data;
	
	/** Levende/aktivert? */
	public $active;
	
	/**
	 * Params til brukeren
	 * @var params_update
	 */
	public $params;
	
	/**
	 * Aktiv spiller til brukeren
	 * @var player
	 */
	public $player;
	
	/**
	 * Informasjon om lås
	 * @var array
	 */
	public $lock;
	
	/**
	 * Er brukeren låst?
	 */
	public $lock_state;
	
	/**
	 * Hent brukerobjekt
	 * @param integer $u_id
	 * @param boolean $is_login_user settes kun av login klassen
	 */
	public static function get($u_id, $is_login_user = false)
	{
		// allerede lastet inn?
		if (isset(self::$users[$u_id]))
		{
			$user = self::$users[$u_id];
			if ($is_login_user) login::$user = $user;
			return $user;
		}
		
		$user = new user($u_id, $is_login_user);
		if (!$user->data) return false;
		
		// lagre objektet for evt. senere bruk
		self::$users[$user->id] = $user;
		
		return $user;
	}
	
	/**
	 * Opprett objekt av en bruker
	 * @param integer $u_id
	 * @param boolean $is_login_user settes kun av login klassen
	 */
	public function __construct($u_id, $is_login_user = false)
	{
		global $_base, $_game;
		$u_id = (int) $u_id;
		$this->id = $u_id;
		
		// hent brukerdata
		$result = \Kofradia\DB::get()->query("
			SELECT users.*
			FROM users
			WHERE users.u_id = $u_id");
		
		// lagre data
		$this->data = $result->fetch();
		unset($result);
		
		// fant ikke brukeren?
		if (!$this->data)
		{
			return;
		}
		
		// levende/aktivert?
		$this->active = $this->data['u_access_level'] != 0;
		
		// koble mot login?
		if ($is_login_user)
		{
			login::$user = $this;
		}
		
		// fjern variablene som skal lastes når de blir benyttet
		unset($this->params);
		unset($this->player);
		unset($this->lock);
		unset($this->lock_state);
		
		return;
	}
	
	/**
	 * Fiks objektet hvis det har vært serialized
	 */
	public function __wakeup()
	{
		// slett objektene på nytt hvis de ikke er initialisert med __get
		if (!isset($this->params)) unset($this->params);
		if (!isset($this->player)) unset($this->player);
		if (!isset($this->lock)) unset($this->lock);
		if (!isset($this->lock_state)) unset($this->lock_state);
	}
	
	/**
	 * Last inn objekter først når de skal benyttes
	 */
	public function __get($name)
	{
		switch ($name)
		{
			// params
			case "params":
				$this->params = new params_update($this->data['u_params'], "users", "u_params", "u_id = $this->id");
				return $this->params;
			break;
			
			// spilleren
			case "player":
				// er dette brukeren som er logget inn?
				if (isset(login::$user->player) && login::$user->player->id == $this->data['u_active_up_id']) $this->player = login::$user->player;
				else new player($this->data['u_active_up_id'], $this);
				return $this->player;
			break;
			
			// lås
			case "lock":
			case "lock_state":
				$this->check_lock();
				return $this->lock;
			break;
		}
	}
	
	/**
	 * Aktiver brukeren
	 */
	public function activate()
	{
		global $_game, $__server;
		
		// er aktivert?
		if ($this->data['u_access_level'] != 0) return false;
		$this->data['u_access_level'] = 1;
		
		// aktiver brukeren
		$a = \Kofradia\DB::get()->exec("UPDATE users SET u_access_level = 1 WHERE u_id = $this->id AND u_access_level = 0");
		if ($a == 0) return false;
		
		putlog("CREWCHAN", "%bAktivering%b: Brukeren {$this->data['u_email']} ({$this->player->data['up_name']}) er nå aktivert igjen {$__server['path']}/min_side?u_id=$this->id");
		return true;
	}
	
	/**
	 * Deaktiver brukeren
	 */
	public function deactivate($reason, $note, player $by_up = null)
	{
		global $_game, $__server;
		if (!$by_up) $by_up = $this->player;
		
		// er ikke aktivert?
		if ($this->data['u_access_level'] == 0) return false;
		
		// deaktivere spilleren?
		if ($this->player->active)
		{
			$this->player->deactivate($reason, $note, $by_up);
		}
		
		$this->data['u_access_level'] = 0;
		$this->data['u_deactivated_time'] = time();
		$this->data['u_deactivated_up_id'] = $by_up->id;
		$this->data['u_deactivated_reason'] = empty($reason) ? NULL : $reason;
		$this->data['u_deactivated_note'] = empty($note) ? NULL : $note;
		
		// deaktiver brukeren
		$a = \Kofradia\DB::get()->exec("UPDATE users SET u_access_level = 0, u_deactivated_time = {$this->data['u_deactivated_time']}, u_deactivated_up_id = $by_up->id, u_deactivated_reason = ".\Kofradia\DB::quote($reason).", u_deactivated_note = ".\Kofradia\DB::quote($note)." WHERE u_id = $this->id AND u_access_level != 0");
		if ($a == 0) return false;
		
		// logg ut alle øktene
		\Kofradia\DB::get()->exec("UPDATE sessions SET ses_active = 0, ses_logout_time = ".time()." WHERE ses_u_id = $this->id AND ses_active = 1");
		
		if ($by_up->id == $this->player->id) $info = 'deaktiverte seg selv';
		else
		{
			$info = 'ble deaktivert';
			if (login::$logged_in) $info .= ' av '.login::$user->player->data['up_name'];
		}
		putlog("CREWCHAN", "%bDeaktivering%b: Brukeren {$this->data['u_email']} ({$this->player->data['up_name']}) $info {$__server['path']}/min_side?u_id=$this->id");
		return true;
	}
	
	/**
	 * Er brukeren låst pga. manglende data?
	 */
	protected function check_lock()
	{
		$this->lock = array();
		
		// er passordet fjernet?
		if (!$this->data['u_pass'])
		{
			$this->lock[] = "pass";
		}
		
		// er ikke fødselsdato ført opp?
		if (empty($this->data['u_birth']) || $this->data['u_birth'] == "0000-00-00")
		{
			$this->lock[] = "birth";
		}
		
		// har vi ingen levende spiller?
		if (!$this->player->active)
		{
			$this->lock[] = "player";
		}
		
		$this->lock_state = count($this->lock) > 0;
	}
	
	/**
	 * Endre tilgangsnivå
	 * @param integer $level nytt tilgangsnivå
	 * @param bool $no_update_up ikke oppdatere det visuelle tilgangsnivået til spilleren?
	 */
	public function change_level($level, $no_update_up = NULL)
	{
		global $_game;
		$level = (int) $level;
		
		\Kofradia\DB::get()->beginTransaction();
		
		// forsøk å endre tilgangsnivået fra nåværende
		$a = \Kofradia\DB::get()->exec("UPDATE users SET u_access_level = $level WHERE u_id = $this->id AND u_access_level = {$this->data['u_access_level']}");
		if (!$a > 0)
		{
			\Kofradia\DB::get()->commit();
			return false;
		}
		$this->data['u_access_level'] = $level;
		
		// endre spilleren også?
		if ($this->player->active && !$no_update_up)
		{
			\Kofradia\DB::get()->exec("UPDATE users_players SET up_access_level = $level WHERE up_id = {$this->player->id}");
			
			// endre rankliste?
			/*if ($level < $_game['access_noplay'] && $this->player->data['up_access_level'] >= $_game['access_noplay'])
			{
				// øk tallplasseringen til de under spilleren
				\Kofradia\DB::get()->exec("
					UPDATE users_players, (SELECT up_id ref_up_id FROM users_players WHERE up_points = {$this->player->data['up_points']} AND up_id != {$this->player->id} AND up_access_level < {$_game['access_noplay']} LIMIT 1) ref
					SET up_rank_pos = up_rank_pos + 1 WHERE ref_up_id IS NULL AND up_points < {$this->player->data['up_points']}");
			}
			elseif ($level >= $_game['access_noplay'] && $this->player->data['up_access_level'] < $_game['access_noplay'])
			{
				// senk tallplasseringen til de under spilleren
				\Kofradia\DB::get()->exec("
					UPDATE users_players, (SELECT up_id ref_up_id FROM users_players WHERE up_points = {$this->player->data['up_points']} AND up_id != {$this->player->id} AND up_access_level < {$_game['access_noplay']} LIMIT 1) ref
					SET up_rank_pos = up_rank_pos - 1 WHERE ref_up_id IS NULL AND up_points < {$this->player->data['up_points']}");
			}*/
			\Kofradia\DB::get()->exec("UPDATE users_players_rank SET upr_up_access_level = $level WHERE upr_up_id = {$this->player->id}");
			ranklist::update();
			
			$this->player->data['up_access_level'] = $level;
		}
		
		\Kofradia\DB::get()->commit();
		
		return true;
	}

	/**
	 * Hent kontaktlisten
	 *
	 * @return array(\Kofradia\Users\Contact, ..)
	 */
	public function getContacts()
	{
		return Contact::getContacts($this);
	}

	/**
	 * Oppdater tidsstempel for når kontaktlisten sist ble endret
	 */
	public function updateContactsTime()
	{
		\Kofradia\DB::get()->exec("
			UPDATE users SET u_contacts_update_time = ".time()."
			WHERE u_id = ".$this->id);
	}
}