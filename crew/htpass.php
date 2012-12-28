<?php

require "../base.php";
global $_base;

access::no_guest();
access::need("crewet");

$_base->page->add_title("Endre HT-passord");

// les htpasswd
$passfile = "/home/kofradia/htpass.htpasswd";
$htdigest = new htdigest(file_get_contents($passfile));

if (isset($_POST['pass']))
{
	if (strlen($_POST['pass']) < 5)
	{
		$_base->page->add_message("Minst 5 tegn!", "error");
	}
	
	else
	{
		// melding
		if ($htdigest->is_user(login::$user->player->data['up_name'], "HT-pass"))
		{
			putlog("CREWCHAN", "HT-PASSORD: %u".login::$user->player->data['up_name']."%u endret HT-passordet sitt.");
		}
		else
		{
			putlog("CREWCHAN", "HT-PASSORD: %u".login::$user->player->data['up_name']."%u opprettet HT-pass bruker.");
		}
		
		// endre passord/legg til bruker
		$htdigest->set_password(login::$user->player->data['up_name'], "HT-pass", $_POST['pass']);
		
		// lagre ny data
		if (!file_put_contents($passfile, $htdigest->generate_data()))
		{
			throw new HSException("Kunne ikke lagre passordfilen.");
		}
		
		$_base->page->add_message("Ditt passordet er nå endret. Logg inn med ditt brukernavn og passordet du endret til.");
		redirect::handle();
	}
}

echo '
<h1>Endre HT-passord</h1>
<div class="section center w200">
	<h2>Endre HT-passord</h2>'.(!$htdigest->is_user(login::$user->player->data['up_name'], "HT-pass") ? '
	<p class="error_box">Du har ingen aktiv HT-pass bruker.</p>' : '').'
	<p>HT-passordet er det som brukes for å komme inn på enkelte undersider på webserveren som krever passord for hele mappen (for dokumenter osv).</p>
	<form action="" method="post">
		<dl class="dd_right dl_2x">
			<dt>Nytt passord</dt>
			<dd><input type="password" name="pass" class="styled w100" /></dd>
		</dl>
		<p class="c">'.show_sbutton("Lagre").'</p>
	</form>
</div>';

// vis liste over alle brukerene
if (access::has("mod"))
{
	// finn brukere
	$users = $htdigest->get_users("HT-pass");
	
	// fjerne en bruker?
	if (isset($_POST['delete']))
	{
		// finnes brukeren?
		if (!$htdigest->is_user($_POST['delete'], "HT-pass"))
		{
			$_base->page->add_message("Denne brukeren finnes ikke.", "error");
			redirect::handle();
		}
		
		// fjern brukeren
		$htdigest->remove_user($_POST['delete'], "HT-pass");
		
		// lagre ny data
		file_put_contents($passfile, $htdigest->generate_data());
		
		putlog("CREWCHAN", "HT-PASSORD: ".login::$user->player->data['up_name']." fjernet %u{$_POST['delete']}%u fra HT-pass listen.");
		$_base->page->add_message("Brukeren <b>".htmlspecialchars($_POST['delete'])."</b> ble fjernet.");
		redirect::handle();
	}
	
	// vis oversikt
	echo '
<h1>Oversikt over brukere</h1>
<div class="section center w200">
	<h2>Brukere</h2>';
	
	if (count($users) == 0)
	{
		echo '
	<p>Ingen brukere finnes.</p>';
	}
	
	else
	{
		echo '
	<dl class="dd_right">';
		
		foreach ($users as $user)
		{
			echo '
		<dt>'.htmlspecialchars($user).'</dt>
		<dd><form action="" method="post"><input type="hidden" name="delete" value="'.htmlspecialchars($user).'" />'.show_sbutton("Fjern").'</form></dd>';
		}
		
		echo '
	</dl>';
	}
	
	echo '
</div>';
}

$_base->page->load();
