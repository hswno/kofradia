<?php

// denne filen sørger for riktig behandling av skjemaene på siden
// og sjekker om en bruker har utført samme skjema en gang tidligere

/*

eksempel:

$form = new form("utpressing");

// form sjekking
$form->validate($_POST['hash']);

<input type="hidden" name="hash" value="'.$form->create().'" />

*/


class form
{
	public $area = NULL;
	public $u_id = 0;
	public $user = NULL;
	public $hash = false;
	public $active = false;
	public $invalid_text = "Ugyldig inntasting. Gå tilbake og prøv på nytt!";
	
	// opprett objektet og opprett ny kode eller hent aktiv
	function form($area)
	{
		global $_base;
		
		$this->area = $area;
		$this->u_id = login::$user->id;
		$this->user = login::$user->player->data['up_name'];
		
		// har vi noen aktive som ikke er fullført?
		$result = $_base->db->query("SELECT * FROM forms WHERE forms_area = ".$_base->db->quote($this->area)." AND forms_u_id = {$this->u_id} AND forms_attempts = 0 LIMIT 1");
		
		if ($row = mysql_fetch_assoc($result))
		{
			$this->active = $row;
			$this->hash = $row['forms_hash'];
		}
	}
	
	// opprett ny ID
	function create()
	{
		// har vi en fra før av?
		if ($this->active)
		{
			#putlog("SPAMLOG", "%c11%bFORMS-OLD%b%c: %u{$this->user}%u was returned with a old hash (%u{$this->area}%u)");
			
			return $this->hash;
		}
		
		global $_base;
		
		// opprett ny
		$this->hash = uniqid("");
		$this->active = true;
		$_base->db->query("INSERT INTO forms SET forms_area = ".$_base->db->quote($this->area).", forms_hash = '{$this->hash}', forms_u_id = {$this->u_id}, forms_created_time = ".time());
		
		#putlog("SPAMLOG", "%c6%bFORMS-NEW%b%c: %u{$this->user}%u was returned with a %unew%u hash (%u{$this->area}%u)");
		
		return $this->hash;
	}
	
	// formen er utført med denne hashen
	function validate($hash, $info = "")
	{
		if (strlen($hash) > 13)
		{
			putlog("ABUSE", "%b%c13BOT-ABUSE:%c%b %u".login::$user->player->data['up_name']."%u sendte hash %u$hash%u til %u{$_SERVER['REQUEST_URI']}%u (har ikke javascript?)");
			$hash = substr($hash, 0, 13);
		}
		
		global $_base;
		$hash_sql = $_base->db->quote($hash);
		
		// er dette den aktive?
		if ($hash == $this->hash && $this->hash != false)
		{
			$_base->db->query("DELETE FROM forms WHERE forms_hash = $hash_sql AND forms_u_id = $this->u_id");
			$this->active = false;
			return 1;
		}
		
		else
		{
			// ikke aktiv
			// finnes den i det hele tatt?
			$result = $_base->db->query("SELECT * FROM forms WHERE forms_area = ".$_base->db->quote($this->area)." AND forms_u_id = $this->u_id AND forms_hash = $hash_sql");
			
			if ($row = mysql_fetch_assoc($result))
			{
				$log = $_base->db->quote("Time: ".$_base->date->get()->format("d.m.Y H:i:s")."; URI: ".$_SERVER['REQUEST_URI']."; User-agent: ".$_SERVER['HTTP_USER_AGENT'].(!empty($info) ? '; '.$info : ''));
				$_base->db->query("UPDATE forms SET forms_attempts = forms_attempts + 1, forms_log = IF(ISNULL(forms_log), $log, CONCAT(forms_log, '\n', $log)), forms_last_time = ".time()." WHERE forms_area = ".$_base->db->quote($this->area)." AND forms_hash = $hash_sql AND forms_u_id = $this->u_id");
				
				if ($row['forms_attempts'] > 0) putlog("ABUSE", "%c13%bFORMS-ABUSE:%b%c %u{$this->user}%u utførte samme formdata på nytt! (Gjentakelse: %c4%u".($row['attempts']+1)."%u%c; Area: %u{$this->area}%u; Hash: $hash; IP:%c5 {$_SERVER['REMOTE_ADDR']}%c".(!empty($info) ? '; Info: '.$info : '').")");
				
				// over 5 ganger? -> avbryt
				if ($row['forms_attempts'] >= 1)
				{
					$_base->page->add_message($this->invalid_text, "error");
					putlog("ABUSE", "%c4%bFORMS-ABUSE:%b%c Skjemaet til %u{$this->user}%u ble avbrutt.");
					$_base->page->load();
				}
				
				return $row['forms_attempts'] + 1;
			}
			
			else
			{
				putlog("ABUSE", "%c4%bFORMS-ABUSE:%b%c %u{$this->user}%u utførte formdata med ugyldig hash! (Area: %u{$this->area}%u; Hash: $hash)".(!empty($info) ? '; Info: '.$info : '').")");
				
				$_base->page->add_message($this->invalid_text, "error");
				putlog("ABUSE", "%c4%bFORMS-ABUSE:%b%c Skjemaet til %u{$this->user}%u ble avbrutt pga. manglende hash.");
				$_base->page->load();
				
				return false;
			}
		}
	}
}