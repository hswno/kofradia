<?php namespace Kofradia\Network;

class Helpers {
	/**
	 * Check if a IP matches a CIDR
	 *
	 * @param string IP to check, e.g. 10.4.0.1
	 * @param string CIDR to match, e.g. 10.4.0.0/24
	 * @return bool
	 */
	public static function cidr_match($ip, $range)
	{
		list ($subnet, $bits) = explode('/', $range);
		$ip = ip2long($ip);
		$subnet = ip2long($subnet);
		$mask = -1 << (32 - $bits);
		$subnet &= $mask; # nb: in case the supplied subnet wasn't correctly aligned
		return ($ip & $mask) == $subnet;
	}
}