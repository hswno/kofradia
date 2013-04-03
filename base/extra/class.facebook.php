<?php

class facebook
{
	/**
	 * Hent antall likes
	 */
	public static function get_likes_num()
	{
		$d = cache::fetch("facebook_likes");
		if ($d !== false) return $d;
		
		$ttl = 900; // 15 min cache
		
		// hent data
		$json = @file_get_contents("https://graph.facebook.com/kofradia");
		if (!$json)
		{
			cache::store("facebook_likes", "ukjent", $ttl);
			return "ukjent";
		}
		
		$data = json_decode($json);
		cache::store("facebook_likes", $data['likes'], $ttl);
		
		return $data['likes'];
	}
}