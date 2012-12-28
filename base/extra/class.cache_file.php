<?php

class cache_file implements cache_engine
{
	protected $cache_p;
	public function __construct()
	{
		$this->cache_p = CACHE_FILES_DIR . "/" . CACHE_FILES_PREFIX;
	}
	
	public function fetch($key)
	{
		$file = $this->get_path($key);
		if (!file_exists($file)) return false;
		
		$data = file_get_contents($file);
		if (!$data) return false;
		
		$data = unserialize($data);
		if ($data[0] != 0 && $data[0] < time())
		{
			@unlink($file);
			return false;
		}
		
		return $data[1];
	}
	
	public function store($key, $data, $ttl)
	{
		$file = $this->get_path($key);
		$data = serialize(array(
			$ttl > 0 ? time() + $ttl : 0,
			$data
		));
		$res = file_put_contents($file, $data);
		
		if ($res)
		{
			@chmod($file, 0777);
		}
		
		return $res;
	}
	
	public function delete($key)
	{
		return @unlink($this->get_path($key));
	}
	
	protected function get_path($key)
	{
		return $this->cache_p . str_replace("..", "__", $key);
	}
}