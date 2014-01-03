<?php namespace Kofradia\Users;

use \Kofradia\DB;

class Autologin {
	/**
	 * Type for resetting password
	 */
	const TYPE_RESET_PASS = 1;
	
	/**
	 * Generate autologin-row for a user
	 *
	 * @param int $u_id
	 * @param int $expire timestamp
	 * @param optional string $redirect
	 * @param optional int $type (1=resets password)
	 * @return string hash
	 */
	public static function generate($u_id, $expire, $redirect = null, $type = null)
	{
		$u_id = (int) $u_id;
		$expire = (int) $expire;
		$type = $type === null ? null : (int) $type;

		// generer hash
		$hash = mb_substr(sha1(sha1($u_id . $expire . uniqid()) . ($redirect ? $redirect : "doh")), 0, 16);
		
		// opprett
		$redirect = $redirect ? ", al_redirect = ".\Kofradia\DB::quote($redirect) : "";
		\Kofradia\DB::get()->exec("
			INSERT INTO autologin
			SET al_u_id = $u_id, al_hash = ".\Kofradia\DB::quote($hash).", al_time_created = ".time().",
				al_time_expire = ".$expire."$redirect, al_type = ".\Kofradia\DB::quote($type));
		
		return $hash;
	}

	/**
	 * Log error-result
	 *
	 * @param string Melding
	 */
	public static function logError($message = null)
	{
		$message = $message ? " ($message)" : "";
		putlog("NOTICE", sprintf("AUTOLOGIN: Ugyldig forespørsel fra %s%s",
			$_SERVER['REMOTE_ADDR'],
			$message));
	}

	/**
	 * Genereate URL from hash
	 *
	 * @param string Hash
	 * @return string URL
	 */
	public static function generateUrl($hash)
	{
		return \ess::$s['spath'].'/autologin/'.$hash;
	}

	/**
	 * Get by hash
	 *
	 * @param string hash
	 * @return \Kofradia\Users\Autologin
	 */
	public static function getByHash($hash)
	{
		$q = DB::get()->prepare("
			SELECT al_id, al_u_id, al_hash, al_time_created, al_time_expire, al_time_used, al_sid, al_redirect, al_type
			FROM autologin
			WHERE al_hash = ?");
		$q->execute(array($hash));

		if ($row = $q->fetch())
		{
			$al = new static();
			$al->data = $row;
			return $al;
		}
	}

	/**
	 * Data from database
	 *
	 * @var array
	 */
	public $data;

	/**
	 * The user it belongs
	 *
	 * @var \user
	 */
	protected $user;

	/**
	 * Redirect URL override
	 *
	 * @var string
	 */
	protected $url_override;

	/**
	 * Message provided by processing, e.g. specific user information
	 *
	 * @var array
	 */
	protected $messages = array();

	/**
	 * Log result
	 *
	 * @param string Melding
	 */
	public function log($message = null)
	{
		putlog("NOTICE", sprintf("AUTOLOGIN: Gyldig visning fra %s%s (al_id: %s)%s%s",
			$_SERVER['REMOTE_ADDR'],
			(\login::$logged_in ? " av ".\login::$user->player->data['up_name']." (".\login::$user->data['u_email'].")" : ""),
			$this->data['al_id'],
			($message ? sprintf(" (%s)", $message) : ""),
			($this->data['al_redirect'] ? sprintf(" (redir: %s)", $this->data['al_redirect'].")") : "")));
	}

	/**
	 * Get user
	 *
	 * @return \user
	 */
	public function getUser()
	{
		if ($this->user)
		{
			return $this->user;
		}

		if ($this->data['al_u_id'])
		{
			return ($this->user = \user::get($this->data['al_u_id']));
		}

		throw new \HSException("Autologin mangler bruker!");
	}

	/**
	 * Check if expired
	 *
	 * @return bool
	 */
	public function isExpired()
	{
		return $this->data['al_time_expire'] < time();
	}

	/**
	 * Check if used
	 *
	 * @return bool
	 */
	public function isUsed()
	{
		return $this->data['al_time_used'];
	}

	/**
	 * Check if it can be used
	 *
	 * @return bool
	 */
	public function isUsable()
	{
		return !$this->isUsed() && !$this->isExpired();
	}

	/**
	 * Set used
	 *
	 * @param int Session ID assigned
	 */
	public function setUsed($sid = null)
	{
		$extra = '';
		$data = array();
		$data[] = time();
		if ($sid)
		{
			$extra .= ', al_sid = ?';
			$data[] = $sid;
		}
		$data[] = $this->data['al_id'];

		$q = DB::get()->prepare("UPDATE autologin SET al_time_used = ?$extra WHERE al_id = ?");
		$q->execute($data);
	}

	/**
	 * Redirect to specified URL
	 */
	public function redirect()
	{
		$url = $this->url_override ?: $this->data['al_redirect'];
		return \redirect::handle($url, \redirect::ROOT);
	}

	/**
	 * Process
	 *
	 * @return bool Logged in as correct user?
	 */
	public function process()
	{
		// already used og expired?
		if (!$this->isUsable())
		{
			$success = $this->processNoUsable();
		}

		// already logged in?
		elseif (\login::$logged_in)
		{
			$success = $this->processLoggedIn();
		}

		// not logged in and can proces as normal
		else
		{
			$success = $this->processNormal();
		}

		// handle type if success
		if ($success)
		{
			$this->handleType();
		}

		return $success;
	}

	/**
	 * Process the various type-events
	 */
	protected function handleType()
	{
		// reset password?
		if ($this->data['al_type'] == static::TYPE_RESET_PASS)
		{
			$this->handleResetPassword();
		}
	}

	/**
	 * Reset password for the user
	 */
	protected function handleResetPassword()
	{
		$user = $this->getUser();

		// update user
		DB::get()->prepare("UPDATE users SET u_pass = NULL, u_pass_change = NULL WHERE u_id = ?")->execute(array($user->id));
		$reseted = $user->data['u_pass'] != null;
		$user->data['u_pass'] = null;
		$user->data['u_pass_change'] = null;
		
		// log out any sessions
		$q = DB::get()->prepare("
			UPDATE sessions
			SET ses_active = 0, ses_logout_time = ?
			WHERE ses_u_id = ? AND ses_active = 1 AND ses_id != ?");
		$logged_out = $q->execute(array(
			time(),
			\login::$user->id,
			\login::$info['ses_id']));
		
		$msg = $reseted ? 'Ditt passord har nå blitt nullstilt, og du kan nå opprette et nytt passord.' : 'Ditt passord var allerede nullstilt.';
		if ($logged_out > 0) $msg .= ' '.fwords("%d økt", "%d økter", $logged_out).' ble logget ut automatisk.';
		$this->messages[] = $msg;
		
		$this->log("Logget inn; passord nullstilt");
		$this->url_override = "/lock?f=pass";
	}

	/**
	 * Process expired and already used links
	 *
	 * @return bool Logged in as correct user?
	 */
	protected function processNoUsable()
	{
		// marker som benyttet
		if (!$this->isUsed())
		{
			$this->setUsed();
		}

		// logget inn som korrekt bruker?
		if (\login::$logged_in && \login::$user->id == $this->getUser()->id)
		{
			// send til korrekt side uten beskjed
			$this->log(($expired ? "Gått ut på tid" . ($this->data['al_time_used'] ? " og allerede benyttet" : "") : "Allerede benyttet")."; Allerede logget inn");
			return true;
		}

		// logget inn men som en annen bruker?
		if (\login::$logged_in)
		{
			$this->log(($expired ? "Gått ut på tid" . ($this->data['al_time_used'] ? " og allerede benyttet" : "") : "Allerede benyttet")."; Logget inn som annen bruker");
			$this->messages[] = "Lenken du forsøkte å åpne ".($expired ? "har gått ut på tid" : "har allerede blitt benyttet").". Du er ikke logget inn med samme bruker som lenken var rettet til.";
			return false;
		}

		// ikke logget inn
		$this->log(($expired ? "Gått ut på tid" . ($this->data['al_time_used'] ? " og allerede benyttet" : "") : "Allerede benyttet"));
		$this->messages[] = "Lenken du forsøkte å åpne ".($expired ? "har gått ut på tid" : "har allerede blitt benyttet").".".($this['al_redirect'] ? " Du må logge inn manuelt for å bli sendt til korrekt side." : "");

		return false;
	}

	/**
	 * Process already logged in
	 *
	 * @return bool Logged in as correct user?
	 */
	protected function processLoggedIn()
	{
		// correct user?
		if (\login::$user->id == $this->getUser()->id)
		{
			$this->setUsed();
			$this->log("Allerede logget inn");
			return true;
		}

		// correct user is deactivated?
		if ($this->getUser()->data['u_access_level'] == 0)
		{
			$this->setUsed();
			$this->log("Logget inn som annen bruker; Bruker deaktivert");
			$this->messages[] = "Lenken du forsøkte å åpne var ment for en annen bruker som er deaktivert.";
			return false;
		}
		
		// we can switch user
		\login::logout();
		if (\login::do_login_handle($this->getUser()->id))
		{
			$this->setUsed(\login::$info['ses_id']);

			$this->log("Logget ut og logget inn med korrekt bruker");
			$this->messages[] = sprintf('Du har blitt automatisk logget ut av den forrige brukeren og logget inn med <user id="%s" />.<br />Du blir automatisk logget ut etter 15 minutter uten aktivitet.', $this->getUser()->player->id);
		
			return true;
		}
		
		// failed
		$this->setUsed();
		
		$this->log("Logget ut; Innlogging mislykket");
		$this->messages[] = "Automatisk innlogging ble mislykket.";

		return false;
	}

	/**
	 * Process as normal
	 *
	 * @return bool Logged in as correct user?
	 */
	protected function processNormal()
	{
		$user = $this->getUser();
		if (!\login::do_login_handle($user->id))
		{
			$this->setUsed();
			$this->log("Innlogging mislykket");
			return false;
		}

		$this->setUsed(\login::$info['ses_id']);
		$this->log("Logget inn");
		$this->messages[] = sprintf('Du har blitt automatisk logget inn som <user id="%s" />. Du blir automatisk logget ut etter 15 minutter uten aktivitet.', $this->getUser()->player->id);
		
		return true;
	}

	/**
	 * Get message generated by processing
	 *
	 * @return array of strings (the messages)
	 */
	public function getMessages()
	{
		return $this->messages;
	}
}