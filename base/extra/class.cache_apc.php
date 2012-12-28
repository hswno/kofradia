<?php

class cache_apc implements cache_engine
{
	public function fetch($key)
	{
		return apc_fetch($key);
	}
	
	public function store($key, $data, $ttl)
	{
		return @apc_store($key, $data, $ttl);
	}
	
	public function delete($key)
	{
		return apc_delete($key);
	}
}
