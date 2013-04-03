<?php

class autologin
{
	/** Type for nullstilling av passord */
	const TYPE_RESET_PASS = 1;
	
	/**
	 * Generer autologin oppføring for en bruker
	 * @param int $u_id
	 * @param int $expire
	 * @param optional string $redirect
	 * @param optional int $type (1=nullstiller passordet)
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
		$redirect = $redirect ? ", al_redirect = ".ess::$b->db->quote($redirect) : "";
		ess::$b->db->query("INSERT INTO autologin SET al_u_id = $u_id, al_hash = ".ess::$b->db->quote($hash).", al_time_created = ".time().", al_time_expire = ".$expire."$redirect, al_type = ".ess::$b->db->quote($type));
		
		return $hash;
	}
}