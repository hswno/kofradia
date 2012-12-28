<?php

require "../base/ajax.php";

// krev bruker
ajax::require_user();

// mangler verdier?
if (!isset($_POST['type']) || !isset($_POST['note']) || !isset($_POST['ref']))
{
	ajax::text("ERROR:MISSING", ajax::TYPE_INVALID);
}

// blokkert fra å sende inn rapporteringer?
$blokkering = blokkeringer::check(blokkeringer::TYPE_RAPPORTERINGER);
if ($blokkering)
{
	ajax::html("Du er blokkert fra å sende inn rapporteringer. Blokkeringen varer til ".$_base->date->get($blokkering['ub_time_expire'])->format(date::FORMAT_SEC).".<br /><b>Begrunnelse:</b> ".game::format_data($blokkering['ub_reason'], "bb-opt", "Ingen begrunnelse gitt."), ajax::TYPE_INVALID);
}

// begrunnelse er tomt?
$note = trim($_POST['note']);
if (empty($note))
{
	ajax::html("<p>Begrunnelsen kan ikke være tom!</p>", ajax::TYPE_INVALID);
}

// referanseid
$ref = intval($_POST['ref']);

// forsøk å legg til
switch ($_POST['type'])
{
	// privat melding
	case "pm":
		$result = rapportering::report_pm($ref, $note);
		
		// fant ikke meldingen
		if ($result === false)
		{
			ajax::html("<p>Fant ikke meldingen.</p>", ajax::TYPE_INVALID);
		}
		
		// dupe
		elseif ($result[0] === "dupe")
		{
			ajax::html("<p>Du har allerede rapportert denne meldingen.</p>", ajax::TYPE_INVALID);
		}
	break;
	
	// forumtråd
	case "ft":
		// kontroller lås
		ajax::validate_lock(true);
		
		$result = rapportering::report_forum_topic($ref, $note);
		
		// ukjent tråd
		if ($result === false)
		{
			ajax::html("<p>Fant ikke forumtråden.</p>", ajax::TYPE_INVALID);
		}
		
		// tråd slettet
		elseif ($result === "deleted")
		{
			ajax::html("<p>Forumtråden er slettet.</p>", ajax::TYPE_INVALID);
		}
		
		// dupe
		elseif ($result[0] === "dupe")
		{
			if ($result['r_source_up_id'] == login::$user->player->id)
				ajax::html("<p>Du har allerede rapportert denne tråden.</p>", ajax::TYPE_INVALID);
			ajax::html("<p>Denne tråden er allerede rapportert av en annen bruker.</p>", ajax::TYPE_INVALID);
		}
	break;
	
	// forumsvar
	case "fr":
		// kontroller lås
		ajax::validate_lock(true);
		
		$result = rapportering::report_forum_reply($ref, $note);
		
		// ukjent tråd/svar
		if ($result === false)
		{
			ajax::html("<p>Fant ikke svaret i forumet.</p>", ajax::TYPE_INVALID);
		}
		
		// slettet svar
		elseif ($result === "deleted")
		{
			ajax::html("<p>Svaret er slettet.</p>", ajax::TYPE_INVALID);
		}
		
		// slettet emne
		elseif ($result === "topic_deleted")
		{
			ajax::html("<p>Forumtråden svaret tilhører er slettet.</p>", ajax::TYPE_INVALID);
		}
		
		// dupe
		elseif ($result[0] === "dupe")
		{
			if ($result['r_source_up_id'] == login::$user->player->id)
				ajax::html("<p>Du har allerede rapportert dette svaret.</p>", ajax::TYPE_INVALID);
			ajax::html("<p>Dette svaret er allerede rapportert av en annen bruker.</p>", ajax::TYPE_INVALID);
		}
	break;
	
	// signatur
	case "signature":
		// kontroller lås
		ajax::validate_lock(true);
		
		$result = rapportering::report_signature($ref, $note);
		
		// brukeren finnes ikke
		if ($result === "player_not_found")
		{
			ajax::html("<p>Spilleren du ønsket å rapportere ble ikke funnet.</p>", ajax::TYPE_INVALID);
		}
		
		// dupe
		elseif ($result[0] === "dupe")
		{
			ajax::html("<p>Du har allerede rapportert signaturen til denne brukeren.</p>", ajax::TYPE_INVALID);
		}
	break;
	
	// profiltekst
	case "profile":
		// kontroller lås
		ajax::validate_lock(true);
		
		$result = rapportering::report_profile($ref, $note);
		
		// brukeren finnes kke
		if ($result === "player_not_found")
		{
			ajax::html("<p>Spilleren du ønsket å rapportere ble ikke funnet.</p>", ajax::TYPE_INVALID);
		}
		
		// dupe
		elseif ($result[0] === "dupe")
		{
			ajax::html("<p>Du har allerede rapportert profilen til denne brukeren.</p>", ajax::TYPE_INVALID);
		}
	break;
	
	// fant ikke ønsket rapporteringsvalg
	default:
		ajax::html("<p>Ukjent rapportering.</p>", ajax::TYPE_INVALID);
		sysreport::log("Rapportering ble ikke funnet: {$_POST['type']}\n\nReferanse: {$_POST['ref']}\n\nBegrunnelse for rapportering: {$_POST['note']}");
}

ajax::html('<p>Rapporteringen ble sendt inn og vil bli behandlet så fort som mulig.</p><p>Du vil normalt <b>ikke få svar</b> når saken er behandlet. Takk for din rapportering.</p><div class="p" style="border: 1px dotted #525252; padding: 5px; margin: 1em 1.5em">'.parse_html(game::bb_to_html($note)).'</div>');