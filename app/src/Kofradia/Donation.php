<?php namespace Kofradia;

use \Kofradia\DB;

class Donation {
	/**
	 * Get donations
	 * Sorted by newest first
	 *
	 * @param \pagei $pagei
	 * @return array(\Kofradia\Donasjon, ..)
	 */
	public static function getDonations(\pagei $pagei = null)
	{
		$q = "
			SELECT d_id, d_up_id, d_time, d_amount
			FROM donations
			ORDER BY d_time DESC";
		$result = $pagei ? $pagei->query($q) : DB::get()->query($q);

		$list = array();
		while ($row = $result->fetch())
		{
			$list[] = static::load($row);
		}

		return $list;
	}

	/**
	 * Create donation
	 *
	 * @param float Amount donated
	 * @param \DateTime Time donated
	 * @param int|null Player ID
	 * @return \Kofradia\Donation
	 */
	public static function create($amount, \DateTime $time, $up_id = null)
	{
		DB::get()->exec("
			INSERT INTO donations
			SET d_up_id = ".DB::quote($up_id).", d_time = ".DB::quote($time->getTimestamp()).", d_amount = ".DB::quote($amount));
		\cache::delete("donation_list");
		return static::get(DB::get()->lastInsertId());
	}

	/**
	 * Get specific donation
	 *
	 * @param int Donation ID
	 * @return \Kofradia\Donation
	 */
	public static function get($d_id)
	{
		$d_id = (int) $d_id;
		$result = DB::get()->query("
			SELECT d_id, d_up_id, d_time, d_amount
			FROM donations
			WHERE d_id = $d_id");
		if ($row = $result->fetch())
		{
			return static::load($row);
		}
	}

	/**
	 * Verify data from PayPal
	 *
	 * @return bool|null  null if failure, bool if actually verified
	 */
	public static function verifyPayPalData($data)
	{
		// generate validate-url
		$req = 'cmd=_notify-validate';
		foreach ($data as $key => $value)
		{
			$req .= '&' . urlencode($key) . '='. urlencode($value);
		}

		$header = "POST /cgi-bin/webscr HTTP/1.1\r\n";
		$header .= "Host: www.sandbox.paypal.com\r\n";
		$header .= "Connection: close\r\n";
		$header .= "Content-Type: application/x-www-form-urlencoded\r\n";
		$header .= "Content-Length: ".strlen($req)."\r\n\r\n";

		#$url = "www.sandbox.paypal.com";
		$url = "www.paypal.com";
		$fp = fsockopen('ssl://'.$url, 443, $errno, $errstr, 30);
		fputs($fp, $header.$req);

		$res = null;
		while (!feof($fp))
		{
			$ret = trim(fgets($fp, 1024));
			if ($ret == "VERIFIED")
			{
				$res = true;
				break;
			}
			elseif ($ret == "INVALID")
			{
				$res = false;
				break;
			}
		}

		fclose($fp);
		return $ret;
	}

	/**
	 * Data about the donation
	 *
	 * @var array
	 */
	protected $data;

	/**
	 * Create new object from data
	 *
	 * @param array $data
	 * @return \Kofradia\Donasjon
	 */
	protected static function load(array $data)
	{
		$d = new static();
		$d->data = $data;
		return $d;
	}

	/**
	 * Get time
	 *
	 * @return int
	 */
	public function getTime()
	{
		return $this->data['d_time'];
	}

	/**
	 * Get player ID
	 *
	 * @return int
	 */
	public function getPlayerID()
	{
		return $this->data['d_up_id'];
	}
}