<?php namespace Kofradia;

use \Kofradia\DB;

/**
 * This class can be used to generate a unique hash
 * that must be sent with a form.
 *
 * The hash can only be used once, and thus the form
 * can be submitted only once.
 *
 * Each hash can be associated with a domain,
 * so that different forms can be opened at once
 * without interfering with eachother.
 */
class Form {
	/**
	 * Database fields to fetch
	 *
	 * @var string
	 */
	protected static $databaseFields = "forms_id, forms_area, forms_hash, forms_created_time, forms_attempts, forms_log, forms_last_time";

	/**
	 * Clean database for old entries
	 *
	 * Should be run by scheduler
	 */
	public static function cleanDatabase()
	{
		$expire = time() - 86400*30;
		DB::get()->prepare("
			DELETE FROM forms
			WHERE forms_created_time < ?")
			->execute(array($expire));
	}

	/**
	 * Get object by hash
	 *
	 * @param string hash
	 * @param \user
	 * @return \Kofradia\Form|null
	 */
	public static function getByHash($hash, \user $user)
	{
		$f = new static();
		$f->setHash($hash);
		$f->setUser($user);
		return $f->getFromDB($hash);
	}

	/**
	 * Get object by domain
	 *
	 * If no unused object exists in the database a
	 * new blank object will be returned
	 *
	 * @param string domain
	 * @param \user
	 * @return \Kofradia\Form
	 */
	public static function getByDomain($domain, \user $user)
	{
		$result = DB::get()->prepare("
			SELECT ".static::$databaseFields."
			FROM forms
			WHERE forms_area = ? AND forms_u_id = ? AND forms_attempts = 0
			LIMIT 1");
		$result->execute(array(
			$domain,
			$user->id));

		$f = new static();
		$f->setUser($user);
		$f->setDomain($domain);
		if ($row = $result->fetch())
		{
			$f->setData($row);
		}

		return $f;
	}

	/**
	 * The domain this will operate on
	 *
	 * @var string
	 */
	protected $domain;

	/**
	 * Text that is sent when an invalid request is submitted
	 *
	 * @var string
	 */
	public $invalidText = "Ugyldig inntasting. Gå tilbake og prøv på nytt.";

	/**
	 * The user the form belongs
	 *
	 * @var \user
	 */
	protected $user;

	/**
	 * The unique hash
	 *
	 * @var string
	 */
	protected $hash;

	/**
	 * The current object from database
	 *
	 * @var array
	 */
	protected $data;

	/**
	 * Set user
	 *
	 * @param \user
	 */
	public function setUser(\user $user)
	{
		$this->user = $user;
	}

	/**
	 * Set data
	 *
	 * @param array Data from database
	 */
	public function setData($data)
	{
		$this->data = $data;
		$this->hash = $data['forms_hash'];
		
		$this->setDomain($data['forms_area']);
	}

	/**
	 * Set domain
	 *
	 * @param string Domain
	 */
	public function setDomain($domain)
	{
		// we can't change the domain
		if ($this->domain && $this->domain != $domain)
		{
			throw new \HSException("Domain already set.");
		}

		$this->domain = $domain;
	}

	/**
	 * Check for domain match
	 *
	 * @param string Domain
	 * @return bool True if same domain
	 */
	public function checkDomain($domain)
	{
		return $domain == $this->domain;
	}

	/**
	 * Load data from database by matching hash and user
	 *
	 * @param string Hash
	 * @return \Kofradia\Form|null Returns the current object if success
	 */
	public function getFromDB()
	{
		if (!$this->hash || !$this->user)
		{
			throw new \HSException("Hash or user not set.");
		}

		$result = DB::get()->prepare("
			SELECT ".static::$databaseFields."
			FROM forms
			WHERE forms_hash = ? AND forms_u_id = ?
			LIMIT 1");
		$result->execute(array(
			$this->hash,
			$this->user->id));

		if ($row = $result->fetch())
		{
			$this->setData($row);
			return $this;
		}
	}

	/**
	 * Get hash, create new if needed
	 *
	 * @return string
	 */
	public function getHash()
	{
		if (!$this->hash)
		{
			$this->createHash();
			$this->getFromDB($this->hash);
		}

		return $this->hash;
	}

	/**
	 * Create new hash and insert into database
	 */
	protected function createHash()
	{
		// create new
		$this->setHash(uniqid());
		DB::get()->prepare("
			INSERT INTO forms
			SET forms_area = ?, forms_hash = ?, forms_u_id = ?, forms_created_time = ?")
			->execute(array(
				$this->domain,
				$this->hash,
				$this->user->id,
				time()));
	}

	/**
	 * Set hash
	 *
	 * @param string Hash
	 */
	public function setHash($hash)
	{
		if ($this->hash)
		{
			throw new \HSException("Already have a hash.");
		}

		$this->hash = $hash;
	}

	/**
	 * Mark as used in database
	 *
	 * @param string Extra text to add to log message
	 * @return bool True if success
	 */
	public function setUsed($log_msg = '')
	{
		$res = DB::get()->prepare("
			UPDATE forms
			SET forms_attempts = forms_attempts + 1
			WHERE forms_id = ? AND forms_attempts = 0")
			->execute(array(
				$this->data['forms_id']));
		if ($res)
		{
			$this->addLog($log_msg);
		}

		return $res > 0;
	}

	/**
	 * Add log
	 *
	 * @param string Extra text to add to log message
	 * @param bool Increase number of attempts?
	 */
	protected function addLog($log_msg = '', $increase_attempts = false)
	{
		$log = sprintf("Time: %s; URI: %s; User-agent: %s%s",
			\ess::$b->date->get()->format("d.m.Y H:i:s"),
			$_SERVER['REQUEST_URI'],
			$_SERVER['HTTP_USER_AGENT'],
			(!empty($log_msg) ? '; '.$log_msg : ''));

		$extra = '';
		if ($increase_attempts)
		{
			$this->data['forms_attempts']++;
			$extra = ', forms_attempts = forms_attempts + 1';
		}

		$this->data['forms_last_time'] = time();
		$res = DB::get()->prepare("
			UPDATE forms
			SET forms_last_time = ?$extra,
				forms_log = IF(ISNULL(forms_log), ?, CONCAT(forms_log, '\n', ?))
			WHERE forms_id = ?")
			->execute(array(
				$this->data['forms_last_time'],
				$log,
				$log,
				$this->data['forms_id']));
	}

	/**
	 * Check if hash is used
	 *
	 * @return bool
	 */
	public function isUsed()
	{
		return $this->data && $this->data['forms_attempts'] > 0;
	}

	/**
	 * Check if hash matches
	 *
	 * @param string hash
	 * @return bool
	 */
	public function checkHash($hash)
	{
		return $this->hash && $this->hash == $hash;
	}

	/**
	 * Add a failed attempt
	 *
	 * Is called by a validate function to log the action
	 *
	 * @param string Extra text ot add to log message
	 */
	public function addFailedAttempt($log_msg = '')
	{
		$this->addLog($log_msg, true);

		$msg = (!empty($log_msg) ? '; Info: '.$log_msg : '');
		$msg = "%c13%bFORMS-ABUSE:%b%c %u".$this->user->player->data['up_name']."%u utførte samme formdata på nytt!"
		      ." (Gjentakelse: %c4%u".$this->data['forms_attempts']."%u%c; Area: %u".$this->domain."%u;"
		      ." Hash: %u".$this->hash."%u; IP:%c5 ".$_SERVER['REMOTE_ADDR']."%c".$msg.")";
		$target = $this->data['forms_attempts'] > 2 ? "ABUSE2" : "ABUSE";
		putlog($target, $msg);
	}

	/**
	 * Validate hash
	 *
	 * A hash can only be validated once as it will be removed
	 * when validating
	 *
	 * @param string|null Hash to validate or null to get from request
	 * @param string Extra text to add to log message
	 * @return bool True if success
	 */
	public function validateHash($hash = null, $log_msg = '')
	{
		if (!$hash)
		{
			$hash = postval($this->getVarName());
		}
		
		/*if (mb_strlen($hash) > 13)
		{
			putlog("ABUSE", "%b%c13BOT-ABUSE:%c%b %u".login::$user->player->data['up_name']."%u sendte hash %u$hash%u til %u{$_SERVER['REQUEST_URI']}%u (har ikke javascript?)");
			$hash = mb_substr($hash, 0, 13);
		}*/

		// is this it?
		if (!$this->isUsed() && $this->checkHash($hash) && $this->setUsed())
		{
			return true;
		}

		// it is either used, belongs to another form object or does not exists
		// find the form object by the hash (even if it is the same)
		$obj = static::getByHash($hash, $this->user);
		if ($obj && $obj->checkDomain($this->domain))
		{
			$obj->addFailedAttempt($log_msg);
		}

		else
		{
			// no object found
			$msg = (!empty($log_msg) ? '; Info: '.$log_msg : '');
			putlog("ABUSE2", "%c4%bFORMS-ABUSE:%b%c %u".$this->user->player->data['up_name']."%u utførte formdata med ukjent hash!"
			                  ." (Area: %u".$this->domain."%u; Hash: '".$hash.$msg."')");
		}

		return false;
	}

	/**
	 * Validate hash and alert user if error
	 *
	 * @param string|null Hash to validate or null to get from request
	 * @param string Extra text to add to log message
	 * @return bool True if success (and no error is sent)
	 */
	public function validateHashOrAlert($hash = null, $log_msg = '')
	{
		$res = $this->validateHash($hash, $log_msg);
		if (!$res)
		{
			\ess::$b->page->add_message($this->invalidText, "error");
		}

		return $res;
	}

	/**
	 * Create HTML-input
	 *
	 * @param string|null Name of input-object (or null to use default)
	 * @return string
	 */
	public function getHTMLInput($name = null)
	{
		if (!$name)
		{
			$name = $this->getVarName();
		}

		return '<input type="hidden" name="'.htmlspecialchars($name).'" value="'.htmlspecialchars($this->getHash()).'" />';
	}

	/**
	 * Get name of request variable to use
	 */
	public function getVarName()
	{
		return 'form-'.$this->domain;
	}
}
