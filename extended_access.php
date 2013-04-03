<?php

define("FORCE_HTTPS", true);
require "base.php";
global $_base;

$_base->page->add_title("Utvidede tilganger");
access::no_guest();

// ikke rettigheter for dette?
if (!isset(login::$extended_access))
{
	redirect::handle("");
}

function extended_access_verify_password($password)
{
	// test mot ny løsning
	if (password::verify_hash($password, login::$extended_access['passkey']))
	{
		return true;
	}
	
	// for kompatibilitet mot gammel løsning
	if (password::verify_hash(md5(sha1($password . login::$user->id, $stored_hash) . login::$user->id), login::$extended_access['passkey']))
	{
		return true;
	}
	
	// feil passord
	return false;
}

// behold orign ved reload
if (isset($_GET['orign']))
{
	redirect::store("extended_access?orign=".urlencode($_GET['orign']));
}

// ikke authed?
if (!login::extended_access_is_authed())
{
	// opprette passord?
	if (isset($_GET['create']))
	{
		// har vi passord?
		if (isset(login::$extended_access['passkey']))
		{
			$_base->page->add_message("Passord er allerede lagt inn i din konto.", "error");
			redirect::handle();
		}
		
		// sjekk
		if (isset($_POST['password']) && isset($_POST['password_repeat']))
		{
			// oppfyller passordet kravet?
			$error = password::validate($_POST['password'], password::LEVEL_STRONG);
			if ($error != 0)
			{
				$_base->page->add_message("Passordet oppnådde ikke følgende krav: ".ucfirst(implode(", ", password::format_errors($error))), "error");
				redirect::handle("extended_access?create");
			}
			
			// samme som brukerpassordet?
			if (password::verify_hash($_POST['password'], login::$user->data['u_pass'], "user"))
			{
				$_base->page->add_message("Passordet kan ikke være det samme som passordet til brukeren.", "error");
				redirect::handle("extended_access?create");
			}
			
			// samme som bankpassordet?
			if (password::verify_hash($_POST['password'], login::$user->data['u_bank_auth'], "bank_auth"))
			{
				$_base->page->add_message("Passordet kan ikke være det samme som passordet til banken.", "error");
				redirect::handle("extended_access?create");
			}
			
			// er passordene like?
			if ($_POST['password'] != $_POST['password_repeat'])
			{
				$_base->page->add_message("Passordene må være like.", "error");
				redirect::handle("extended_access?create");
			}
			
			// lagre passord
			$hash = password::hash($_POST['password']);
			login::$user->params->update("extended_access_passkey", $hash, true);
			
			$_base->page->add_message("Du har nå opprettet et passord og kan logge inn for crewauth.");
			redirect::handle();
		}
		
		echo '
<h1>Opprette passord for crewauth</h1>
<form action="extended_access?create'.(isset($_GET['orign']) ? '&amp;orign='.urlencode($_GET['orign']) : '').'" method="post">
	<dl class="dd_right w300">
		<dt>Ønsket passord</dt>
		<dd><input type="password" class="styled w100" name="password" /></dd>
		<dt>Gjenta passord</dt>
		<dd><input type="password" class="styled w100" name="password_repeat" /></dd>
		<dd><input type="submit" value="Opprett passord" class="button" /></dd>
	</dl>
	<p>Ønsket passord må oppfylle kravet til password::LEVEL_STRONG.</p>
</form>';
		
		$_base->page->load();
	}
	
	// glemt passord?
	if (isset($_GET['forgot']))
	{
		echo '
<h1>Glemt passord for crewauth</h1>
<p class="h_right"><a href="extended_access'.(isset($_GET['orign']) ? '?orign='.urlencode($_GET['orign']) : '').'">Tilbake</a></p>
<p>Hvis du har glemt passordet ditt må du ta kontakt med <user id="1" /> for å få det nullstilt.</p>';
		
		$_base->page->load();
	}
	
	// har ikke passord?
	if (!isset(login::$extended_access['passkey']))
	{
		redirect::handle("extended_access?create".(isset($_GET['orign']) ? '&orign='.urlencode($_GET['orign']) : ''));
	}
	
	// logge inn?
	if (isset($_GET['login']) && isset($_POST['password']))
	{
		// kontroller nåværende passord
		if (!extended_access_verify_password($_POST['password']))
		{
			$_base->page->add_message("Passordet stemte ikke.", "error");
			putlog("CREWCHAN", "CREWAUTH: ".login::$user->player->data['up_name']." mislykket innlogging for utvidede tilganger");
			redirect::handle();
		}
		
		// logg inn
		login::extended_access_login();
		
		putlog("NOTICE", "CREWAUTH: ".login::$user->player->data['up_name']." logget inn med utvidede tilganger");
		
		// spesiell side?
		if (isset($_GET['orign']))
		{
			$_base->page->add_message("Du er nå logget inn for utvidede tilganger (crewauth).");
			redirect::handle($_GET['orign'], redirect::SERVER);
		}
		
		redirect::handle();
	}
	
	echo '
<h1>Utvideded tilganger (crewauth)</h1>
<p>Du er ikke logget inn.</p>';
	
	// logg inn skjema
	echo '
<form action="extended_access?login'.(isset($_GET['orign']) ? '&amp;orign='.urlencode($_GET['orign']) : '').'" method="post">';
	
	if (isset($_POST['orign']))
	{
		echo '
	<input type="hidden" name="orign" value="'.htmlspecialchars($_POST['orign']).'" />';
	}
	
	echo '
	<dl class="dd_right w300">
		<dt>Passord</dt>
		<dd><input type="password" name="password" class="styled w100" id="extended_auth_pw" /></dd>
		<dd><input type="submit" value="Logg inn" class="button" /></dd>
	</dl>
	<p><a href="extended_access?forgot'.(isset($_GET['orign']) ? '&amp;orign='.urlencode($_GET['orign']) : '').'">Glemt passord &raquo;</a></p>
</form>';
	
	$_base->page->add_js_domready('$("extended_auth_pw").focus();');
	$_base->page->load();
}

// authed


// logg ut?
if (isset($_GET['logout']))
{
	login::extended_access_logout();
	
	putlog("NOTICE", "CREWAUTH: ".login::$user->player->data['up_name']." logget UT av utvidede tilganger");
	
	// spesiell side?
	if (isset($_GET['orign']))
	{
		$_base->page->add_message("Du er nå logget ut og har ikke lengre utvidede tilganger (crewauth).");
		redirect::handle($_GET['orign'], redirect::SERVER);
	}
	
	redirect::handle();
}

// endre passord?
if (isset($_GET['change']))
{
	// sjekk
	if (isset($_POST['password_current']) && isset($_POST['password']) && isset($_POST['password_repeat']))
	{
		redirect::store("extended_access?change");
		
		// kontroller nåværende passord
		if (!extended_access_verify_password($_POST['password_current']))
		{
			$_base->page->add_message("Nåværende passord stemte ikke.", "error");
			redirect::handle();
		}
		
		// oppfyller passordet kravet?
		$error = password::validate($_POST['password'], password::LEVEL_STRONG);
		if ($error != 0)
		{
			$_base->page->add_message("Passordet oppnådde ikke følgende krav: ".ucfirst(implode(", ", password::format_errors($error))), "error");
			redirect::handle();
		}
		
		// samme som brukerpassordet?
		if (password::verify_hash($_POST['password'], login::$user->data['u_pass'], "user"))
		{
			$_base->page->add_message("Passordet kan ikke være det samme som passordet til brukeren.", "error");
			redirect::handle();
		}
		
		// samme som bankpassordet?
		if (password::verify_hash($_POST['password'], login::$user->data['u_bank_auth'], "bank_auth"))
		{
			$_base->page->add_message("Passordet kan ikke være det samme som passordet til banken.", "error");
			redirect::handle();
		}
		
		// er passordene like?
		if ($_POST['password'] != $_POST['password_repeat'])
		{
			$_base->page->add_message("Passordene må være like.", "error");
			redirect::handle();
		}
		
		// lagre passord
		$hash = password::hash($_POST['password']);
		login::$user->params->update("extended_access_passkey", $hash, true);
		
		putlog("NOTICE", "CREWAUTH: ".login::$user->player->data['up_name']." endret sitt passord for utvidede tilganger");
		
		$_base->page->add_message("Du har nå oppdatert ditt passord for crewauth.");
		redirect::store("extended_access");
	}
	
	echo '
<h1>Endre passord for crewauth</h1>
<p class="h_right"><a href="extended_access">Tilbake</a></p>
<form action="extended_access?change" method="post">
	<dl class="dd_right w300">
		<dt>Nåværende passord</dt>
		<dd><input type="password" class="styled w100" name="password_current" /></dd>
		<dt>Ønsket passord</dt>
		<dd><input type="password" class="styled w100" name="password" /></dd>
		<dt>Gjenta passord</dt>
		<dd><input type="password" class="styled w100" name="password_repeat" /></dd>
		<dd><input type="submit" value="Endre passord" class="button" /></dd>
	</dl>
	<p>Ønsket passord må oppfylle kravet til password::LEVEL_STRONG.</p>
</form>';
	
	$_base->page->load();
}

// har vi noen orign å gå til?
if (isset($_GET['orign']))
{
	redirect::handle($_GET['orign'], redirect::SERVER);
}

echo '
<h1>Utvidede tilganger (crewauth)</h1>
<p>Du er logget inn med utvidede tilganger. Du blir automatisk logget ut etter 30 minutter med inaktivitet.</p>
<p><a href="extended_access?change">Endre passord &raquo;</a></p>
<p><a href="extended_access?logout">Logg ut &raquo;</a></p>';

$_base->page->load();