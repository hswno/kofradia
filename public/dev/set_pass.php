<?php

define("FORCE_HTTPS", true);

require "../base.php";
ess::$b->page->add_title("Sett passord");

// bytte passord?
if (isset($_POST['u_id']))
{
	$u_id = (int) $_POST['u_id'];
	$pass = trim(postval("pass"));
	
	// finn brukeren
	$user = user::get($u_id);
	if (!$user)
	{
		ess::$b->page->add_message("Fant ikke brukeren.", "error");
	}
	
	elseif ($pass == "")
	{
		ess::$b->page->add_message("Passordet kan ikke være tomt.", "error");
	}
	
	else
	{
		// lagre passord for utvidede tilganger
		$hash = password::hash($pass);
		$user->params->update("extended_access_passkey", $hash, true);
		
		// lagre nytt passord
		$hash = ess::$b->db->quote(password::hash($pass, null, "user"));
		ess::$b->db->query("UPDATE users SET u_pass = $hash, u_bank_auth = $hash WHERE u_id = $user->id");
		
		ess::$b->page->add_message("Du lagret nytt passord for brukeren #$user->id (".htmlspecialchars($user->data['u_email']).", ".$user->player->profile_link().").");
		redirect::handle();
	}
}

ess::$b->page->add_js_domready('$("u_id").focus();');

echo '
<h1>Sett passord på en bruker</h1>
<p><a href="./">Tilbake</a></p>
<form action="" method="post">
	<dl class="dl_15">
		<dt>Bruker ID</dt>
		<dd><input type="text" class="styled w40" name="u_id" id="u_id" value="'.postval("u_id").'" /></dd>
		<dt>Nytt passord</dt>
		<dd><input type="password" class="styled w100" name="pass" /></dd>
	</dl>
	<p>'.show_sbutton("Lagre passord").'</p>
</form>';

ess::$b->page->load();