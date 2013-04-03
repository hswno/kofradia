<?php

class httpreq
{
	public $host = "localhost";
	public $actualhost = false;
	public $port = 80;
	public $timeout = 5;
	public $link = false;
	
	// koble til serveren
	function connect()
	{
		$errno = $errstr = false;
		$this->link = @fsockopen($this->host, $this->port, $errno, $errstr, $this->timeout);
		
		if (!$this->link)
		{
			#trigger_error("Kunne ikke koble til {$this->host}. Feilmelding: $errstr", E_USER_WARNING);
			return false;
		}
		
		return true;
	}
	
	// utføre GET spørring
	function get($path, $cookies = array(), $receive_data = true)
	{
		// koble til
		if (!$this->connect()) return false;
		
		// sett opp headers
		$headers = array();
		$headers[] = "GET $path HTTP/1.0";
		$headers[] = "Host: ".($this->actualhost ? $this->actualhost : $this->host);
		$headers[] = "User-Agent: Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.3) Gecko/20070309 Firefox/2.0.0.3";
		$headers[] = "Accept: application/x-shockwave-flash,text/xml,application/xml,application/xhtml+xml,text/html;q=0.9,text/plain;q=0.8,image/png,*/*;q=0.5";
		
		// -> sett opp cookies
		foreach ($cookies as $name => $cookie)
		{
			$headers[] = "Cookie: $name=$cookie";
		}
		
		$headers[] = "Connection: close";
		
		// send spørring
		@fputs($this->link, implode("\r\n", $headers)."\r\n\r\n");
		
		// hente data?
		if ($receive_data)
		{
			return $this->receive_data();
		}
		
		return $this->link;
	}
	
	// utføre POST spørring
	function post($path, $params = array(), $cookies = array(), $receive_data = true)
	{
		// koble til
		if (!$this->connect()) return false;
		
		// sett opp parametere
		$post = array();
		
		foreach ($params as $name => $item)
		{
			$name = urlencode($name);
			
			// array?
			if (is_array($item))
			{
				foreach ($item as $i)
				{
					$post[] = $name."[]=".urlencode($i);
				}
			}
			
			// string
			else
			{
				$post[] = $name."=".urlencode($item);
			}
		}
		
		// sett sammen
		$post = implode("&", $post);
		
		
		// headers
		$headers = array();
		$headers[] = "POST $path HTTP/1.0";
		$headers[] = "Host: ".($this->actualhost ? $this->actualhost : $this->host);
		
		// -> sett opp cookies
		foreach ($cookies as $name => $cookie)
		{
			$headers[] = "Cookie: $name=$cookie";
		}
		
		$headers[] = "Content-type: application/x-www-form-urlencoded";
		$headers[] = "Content-length: " . mb_strlen($post);
		$headers[] = "Connection: close";
		
		// send spørring
		fputs($this->link, implode("\r\n", $headers)."\r\n\r\n".$post);
		
		// hente data?
		if ($receive_data)
		{
			return $this->receive_data();
		}
		
		return $this->link;
	}
	
	// hente data
	function receive_data()
	{
		// hent data
		$data = "";
		while (!@feof($this->link))
		{
			$data .= @fgets($this->link, 8192);
		}
		
		// del opp headers og innhold
		$pos = mb_strpos($data, "\r\n\r\n");
		
		// hent headers og innhold
		$headers = mb_substr($data, 0, $pos);
		$content = mb_substr($data, $pos+4);
		
		// send svar
		return array("headers" => $headers, "content" => $content);
	}
}