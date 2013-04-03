<?php

require "../base.php";
ess::$b->page->add_title("Logg inn som en annen bruker");

// logge inn?
if (isset($_POST['u_id']))
{
	$u_id = (int) $_POST['u_id'];
	
	// finn brukeren
	$user = user::get($u_id);
	if (!$user)
	{
		ess::$b->page->add_message("Fant ikke brukeren.", "error");
	}
	
	else
	{
		// logg inn
		if (login::do_login_handle($user->id, null, LOGIN_TYPE_ALWAYS))
		{
			// logg inn utvidede tilganger
			login::extended_access_login();
			
			ess::$b->page->add_message("Du er nå logget inn som #$user->id (".htmlspecialchars($user->data['u_email']).", ".$user->player->profile_link().").");
			redirect::handle("", redirect::ROOT);
		}
		
		ess::$b->page->add_message("Kunne ikke logge deg inn.");
	}
}

// med spillernavn?
if (isset($_POST['up_name']))
{
	$player = player::get($_POST['up_name'], null, true);
	if (!$player)
	{
		ess::$b->page->add_message("Fant ikke spilleren.", "error");
	}
	
	else
	{
		// logg inn
		if (login::do_login_handle($player->data['up_u_id'], null, LOGIN_TYPE_ALWAYS))
		{
			// logg inn utvidede tilganger
			login::extended_access_login();
			
			ess::$b->page->add_message("Du er nå logget inn som ".$player->profile_link()." (".htmlspecialchars($player->user->data['u_email']).").");
			redirect::handle("", redirect::ROOT);
		}
		
		ess::$b->page->add_message("Kunne ikke logge deg inn.");
	}
}

ess::$b->page->add_js_domready('$("u_id").focus();');

echo '
<h1>Logg inn som en annen bruker</h1>
<p><a href="./">Tilbake</a></p>
<h2>Ved hjelp av bruker ID</h2>
<form action="" method="post">
	<dl class="dl_15">
		<dt>Bruker ID</dt>
		<dd><input type="text" class="styled w40" name="u_id" id="u_id" value="'.htmlspecialchars(postval("u_id")).'" /></dd>
	</dl>
	<p>'.show_sbutton("Logg inn").'</p>
</form>
<h2>Ved hjelp av spillernavn</h2>
<form action="" method="post">
	<dl class="dl_15">
		<dt>Spillernavn</dt>
		<dd><input type="text" class="styled w100" name="up_name" value="'.htmlspecialchars(postval("up_name")).'" /></dd>
	</dl>
	<p>'.show_sbutton("Logg inn").'</p>
</form>';

ess::$b->page->load();