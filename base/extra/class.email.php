<?php

/**
 * Sending av e-post
 * 
 * Støtter html og tekst, samt vanlige vedlegg og vedlegg for html
 */
class email
{
	public $html = false;
	public $text = false;
	public $html_attachments = array();
	public $attachments = array();
	public $headers = array();
	public $params = '';
	public $data = false;
	
	/**
	 * Constructor
	 *
	 * @param string $sender avsender
	 */
	public function __construct($sender = "Kofradia <system@kofradia.no>")
	{
		$this->headers["From"] = $sender;
		$this->headers["MIME-Version"] = "1.0";
		$this->headers["X-Mailer"] = "HenriSt Mailer (PHP ".phpversion().")";
		
		// avsender
		$this->params = "";
		$matches = false;
		preg_match("/([a-zA-Z_\\-][\\w\\.\\-_]*[a-zA-Z0-9_\\-]@[a-zA-Z0-9][\\w\\.-]*[a-zA-Z0-9]\\.[a-zA-Z][a-zA-Z\\.]*[a-zA-Z])/i", $this->headers['From'], $matches);
		if (isset($matches[1]))
		{
			$this->params = "-f {$matches[1]}";
		}
	}
	
	/**
	 * Sett HTML
	 *
	 * @param string html $content
	 * @return object $this
	 */
	public function html($content)
	{
		$this->html = $content;
		return $this;
	}
	
	/**
	 * Sett tekst
	 * 
	 * @param string $content
	 * @return object $this
	 */
	public function text($content)
	{
		$this->text = $content;
		return $this;
	}
	
	/**
	 * Lag boundary ID
	 *
	 * @return string
	 */
	private function genid()
	{
		return "----HENRIST-".uniqid();
	}
	
	/**
	 * Kod om tekst (base64 eller quoted-printable)
	 * 
	 * @param string $data
	 * @param string $encoding
	 * @return array (headings, content)
	 */
	private function encode($data, $encoding = "base64")
	{
		switch ($encoding)
		{
			case "base64":
				$data = trim(chunk_split(base64_encode($data)));
			break;
			
			case "quoted-printable":
				$length = strlen($data);
				$result = '';
				$linelength = 0;
				
				for ($i = 0; $i < $length; $i++)
				{
					$c = ord($data[$i]);
					
					// linjeskift?
					if ($c == 10 || $c == 13)
					{
						$result .= $data[$i];
						$linelength = 0;
						continue;
					}
					
					// ny linje?
					if ($linelength == 75)
					{
						$result .= "=\r\n";
						$linelength = 0;
					}
					
					// tegn som må kodes om?
					// kan forbedres litt for å stemme fullstendig med RFC 2045, men denne gjør jobben
					if (($c == 61 || $c < 33 || $c > 126) && ($c != 32 || $linelength > 73) && ($c != 9 || $linelength > 73))
					{
						// må vi over på ny linje?
						if ($linelength+3 > 76)
						{
							$result .= "=\r\n";
							$linelength = 0;
						}
						elseif ($linelength+3 == 76)
						{
							$next = $data[$i+1];
							if ($next != "\r" && $next != "\n")
							{
								$result .= "=\r\n";
								$linelength = 0;
							}
						}
						
						$result .= "=".str_pad(strtoupper(dechex($c)), 2, '0', STR_PAD_LEFT);
						$linelength += 3;
					}
					
					else
					{
						$result .= $data[$i];
						$linelength++;
					}
				}
				
				$data = $result;
			break;
			
			default:
				throw new HSException("Encoding type $encoding not supported.");
		}
		
		return array('Content-Transfer-Encoding: '.$encoding, $data);
	}
	
	/**
	 * Legg til vanlig vedlegg
	 *
	 * @param string $headers
	 * @param string $data
	 * @param string $encoding
	 * @return object $this
	 */
	public function attach($headers, $data, $encoding = "base64")
	{
		$data = $this->encode($data, $encoding);
		$this->attachments[] = array((!empty($headers) ? $headers."\r\n" : '') . $data[0], $data[1]);
		return $this;
	}
	
	/**
	 * Legg til vedlegg for HTML
	 * 
	 * @param string $headers
	 * @param string $data
	 * @param string $encoding
	 * @return object $this
	 */
	public function html_attach($headers, $data, $encoding = "base64")
	{
		$data = $this->encode($data, $encoding);
		$this->html_attachments[] = array((!empty($headers) ? $headers."\r\n" : '') . $data[0], $data[1]);
		return $this;
	}
	
	/**
	 * Formater e-posten
	 *
	 * @return object $this
	 */
	public function format()
	{
		// for å sjekke om det blir opprettet noen grupper (multiparts)
		$id = false;
		
		// ingenting å sende?
		if ($this->text == false && $this->html == false && count($this->html_attachments) == 0 && count($this->attachments) == 0)
		{
			throw new HSException("No content to send by email."); 
		}
		
		// sett opp html
		$html = false;
		if ($this->html)
		{
			$html = $this->encode($this->html, "quoted-printable");
			$html[0] = "Content-Type: text/html; charset=ISO-8859-1\r\n".$html[0];
			
			// vedlegg?
			if (count($this->html_attachments) > 0)
			{
				$id = $this->genid();
				
				$html[1] = "--".$id."\r\n".$html[0]."\r\n\r\n".$html[1]."\r\n--".$id;
				$html[0] = 'Content-Type: multipart/related; boundary="'.$id.'"';
				
				// vedleggene
				foreach ($this->html_attachments as $item)
				{
					$html[1] .= "\r\n".$item[0]."\r\n\r\n".$item[1]."\r\n--".$id;
				}
				
				$html[1] .= "--";
			}
		}
		
		// sett opp tekst
		$text = false;
		if ($this->text)
		{
			$text = $this->encode($this->text, "quoted-printable");
			$text[0] = "Content-Type: text/plain; charset=ISO-8859-1\r\n".$text[0];
		}
		
		// slå sammen med html
		$data = !$text && $html ? $html : ($text && !$html ? $text : false);
		if ($text && $html)
		{
			$id = $this->genid();
			
			$data[1] = "--".$id."\r\n".$text[0]."\r\n\r\n".$text[1]."\r\n--".$id;
			$data[1] .= "\r\n".$html[0]."\r\n\r\n".$html[1]."\r\n--".$id."--";
			$data[0] = 'Content-Type: multipart/alternative; boundary="'.$id.'"';
		}
		
		// legg til vedlegg
		if (count($this->attachments) > 0)
		{
			$id = $this->genid();
			
			$data[1] = "--".$id."\r\n".$data[0]."\r\n\r\n".$data[1]."\r\n--".$id;
			$data[0] = 'Content-Type: multipart/mixed; boundary="'.$id.'"';
			
			// vedleggene
			foreach ($this->attachments as $item)
			{
				$data[1] .= "\r\n".$item[0]."\r\n\r\n".$item[1]."\r\n--".$id;
			}
			
			$data[1] .= "--";
		}
		
		// sett opp resten av e-posten
		if ($id)
		{
			$data[1] = "This is a multi-part message in MIME format.\r\n".$data[1];
		}
		
		// sett opp headers
		$headers = array();
		foreach ($this->headers as $name => $value)
		{
			$headers[] = "$name: $value";
		}
		$headers = implode("\r\n", $headers)."\r\n".$data[0];
		
		$this->data = array($headers, $data[1]);
		return $this;
	}
	
	/**
	 * Send e-posten
	 *
	 * @param string $receiver
	 * @param string $subject
	 * @return boolean success
	 */
	public function send($receiver, $subject = "<no subject>")
	{
		// ikke formatert e-posten?
		if (!$this->data)
		{
			$this->format();
		}
		
		$headers = $this->data[0];
		
		// sørg for gyldig mottakeradresse (kun e-post, ikke navn)
		$matches = false;
		preg_match("/([a-zA-Z_\\-][\\w\\.\\-_]*[a-zA-Z0-9_\\-]@[a-zA-Z0-9][\\w\\.-]*[a-zA-Z0-9]\\.[a-zA-Z][a-zA-Z\\.]*[a-zA-Z])/i", $receiver, $matches);
		if (isset($matches[1]))
		{
			// kjør To: header
			$headers = "To: ".preg_replace("/[\\r\\n]/", "", $receiver).($headers !== "" ? "\r\n" : "").$headers;
			$receiver = $matches[1];
		}
		
		// send e-posten
		return @mail($receiver, $subject, $this->data[1], $headers, $this->params);
	}
}