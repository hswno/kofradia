<?php

define("FORCE_HTTPS", true);
define("ALLOW_GUEST", true);
require "base.php";

redirect::store("", redirect::ROOT);

/**
 * Logging av resultat fra autologin
 */
function autologin_putlog($message = NULL)
{
	global $al, $hash;
	$message = $message ? " ($message)" : "";
	if (isset($al) && $al)
	{
		$user = login::$logged_in ? " av ".login::$user->player->data['up_name']." (".login::$user->data['u_email'].")" : "";
		putlog("NOTICE", "AUTOLOGIN: Gyldig visning fra {$_SERVER['REMOTE_ADDR']}$user (al_id: {$al['al_id']})$message".($al['al_redirect'] ? " (redir: {$al['al_redirect']})" : ""));
	}
	else
	{
		putlog("NOTICE", "AUTOLOGIN: Ugyldig forespørsel fra {$_SERVER['REMOTE_ADDR']}$message");
	}
}

// mangler vi informasjon?
if (!isset($_GET['h']))
{
	ess::$b->page->add_message("Ugyldig forespørsel.");
	autologin_putlog("Manglet hash");
	redirect::handle();
}
$hash = $_GET['h'];

// hent informasjon om hash
$result = \Kofradia\DB::get()->query("
	SELECT
		al_id, al_u_id, al_hash, al_time_created, al_time_expire, al_time_used, al_sid, al_redirect, al_type,
		u_access_level
	FROM autologin, users WHERE al_hash = ".\Kofradia\DB::quote($hash)." AND u_id = al_u_id");
$al = $result->fetch();

// fant ikke?
if (!$al)
{
	autologin_putlog("Hash ble ikke funnet i databasen: $hash");
	redirect::handle();
}

// skal vi videresendes et sted?
$redir = false;
if ($al['al_redirect'])
{
	$redir = true;
	redirect::store($al['al_redirect'], redirect::ROOT);
}

// allerede benyttet eller gått ut på tid?
$expired = $al['al_time_expire'] < time();
if ($al['al_time_used'] || $expired)
{
	// marker som benyttet
	if (!$al['al_time_used'])
	{
		\Kofradia\DB::get()->exec("UPDATE autologin SET al_time_used = ".time()." WHERE al_id = {$al['al_id']}");
	}
	
	// logget inn som korrekt bruker?
	if (login::$logged_in && login::$user->id == $al['al_u_id'])
	{
		// send til korrekt side uten beskjed
		autologin_putlog(($expired ? "Gått ut på tid" . ($al['al_time_used'] ? " og allerede benyttet" : "") : "Allerede benyttet")."; Allerede logget inn");
		redirect::handle();
	}
	
	// logget inn men som en annen bruker?
	if (login::$logged_in)
	{
		autologin_putlog(($expired ? "Gått ut på tid" . ($al['al_time_used'] ? " og allerede benyttet" : "") : "Allerede benyttet")."; Logget inn som annen bruker");
		ess::$b->page->add_message("Lenken du forsøkte å åpne ".($expired ? "har gått ut på tid" : "har allerede blitt benyttet").". Du er ikke logget inn med samme bruker som lenken var rettet til.", "error");
		redirect::handle();
	}
	
	// ikke logget inn
	autologin_putlog(($expired ? "Gått ut på tid" . ($al['al_time_used'] ? " og allerede benyttet" : "") : "Allerede benyttet"));
	ess::$b->page->add_message("Lenken du forsøkte å åpne ".($expired ? "har gått ut på tid" : "har allerede blitt benyttet").".".($al['al_redirect'] ? " Du må logge inn manuelt for å bli sendt til korrekt side." : ""), "error");
	redirect::handle();
}

// er vi allerede logget inn?
if (login::$logged_in)
{
	// logget inn med feil bruker?
	if (login::$user->id != $al['al_u_id'])
	{
		// er den korrekte brukeren deaktivert?
		if ($al['u_access_level'] == 0)
		{
			// marker som benyttet
			\Kofradia\DB::get()->exec("UPDATE autologin SET al_time_used = ".time()." WHERE al_id = {$al['al_id']}");
			
			autologin_putlog("Logget inn som annen bruker; Bruker deaktivert");
			ess::$b->page->add_message("Lenken du forsøkte å åpne var ment for en annen bruker som er deaktivert.", "error");
			redirect::handle();
		}
		
		// logg ut brukeren
		login::logout();
		
		// logg inn med korrekt bruker
		if (login::do_login_handle($al['al_u_id']))
		{
			// marker som benyttet
			\Kofradia\DB::get()->exec("UPDATE autologin SET al_time_used = ".time().", al_sid = ".login::$info['ses_id']." WHERE al_id = {$al['al_id']}");
			
			autologin_putlog("Logget ut og logget inn med korrekt bruker");
			ess::$b->page->add_message("Du har blitt automatisk logget ut av den forrige brukeren og logget inn med brukeren lenken du åpnet var ment for.<br />Du blir automatisk logget ut etter 15 minutter uten aktivitet.");
			redirect::handle();
		}
		
		// marker som benyttet
		\Kofradia\DB::get()->exec("UPDATE autologin SET al_time_used = ".time()." WHERE al_id = {$al['al_id']}");
		
		autologin_putlog("Logget ut; Innlogging mislykket");
		ess::$b->page->add_message("Automatisk innlogging ble mislykket.".($al['al_redirect']));
		redirect::handle();
	}
	
	// marker som benyttet
	\Kofradia\DB::get()->exec("UPDATE autologin SET al_time_used = ".time()." WHERE al_id = {$al['al_id']}");
	
	autologin_putlog("Allerede logget inn");
	redirect::handle();
}

// logg inn med korrekt bruker
if (login::do_login_handle($al['al_u_id']))
{
	// marker som benyttet
	\Kofradia\DB::get()->exec("UPDATE autologin SET al_time_used = ".time().", al_sid = ".login::$info['ses_id']." WHERE al_id = {$al['al_id']}");
	
	// nullstille passordet?
	if ($al['al_type'] == autologin::TYPE_RESET_PASS)
	{
		// oppdater brukeren
		\Kofradia\DB::get()->exec("UPDATE users SET u_pass = NULL, u_pass_change = NULL WHERE u_id = ".login::$user->id);
		$reseted = login::$user->data['u_pass'] != null;
		
		// logg ut øktene
		$logged_out = \Kofradia\DB::get()->exec("UPDATE sessions SET ses_active = 0, ses_logout_time = ".time()." WHERE ses_u_id = ".login::$user->id." AND ses_active = 1 AND ses_id != ".login::$info['ses_id']);
		
		$msg = $reseted ? 'Ditt passord har nå blitt nullstilt, og du kan nå opprette et nytt passord.' : 'Ditt passord var allerede nullstilt.';
		if ($logged_out > 0) $msg .= ' '.fwords("%d økt", "%d økter", $logged_out).' ble logget ut automatisk.';
		
		autologin_putlog("Logget inn; passord nullstilt");
		ess::$b->page->add_message("Du har blitt automatisk logget inn.<br />Du blir automatisk logget ut etter 15 minutter uten aktivitet.<br /><br />$msg");
		
		redirect::handle("lock?f=pass", redirect::ROOT);
	}
	
	else
	{
		autologin_putlog("Logget inn");
		ess::$b->page->add_message("Du har blitt automatisk logget inn.<br />Du blir automatisk logget ut etter 15 minutter uten aktivitet.");
	}
	
	redirect::handle();
}

// marker som benyttet
\Kofradia\DB::get()->exec("UPDATE autologin SET al_time_used = ".time()." WHERE al_id = {$al['al_id']}");

autologin_putlog("Innlogging mislykket");
ess::$b->page->add_message("Automatisk innlogging ble mislykket.");
redirect::handle();