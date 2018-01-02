<?php

/*
 * Dette scriptet kan brukes til å slette artikler fra databasen som
 * tilhører folk som ikke lengre er medlem av firmaet artikkelen er
 * opprettet i og som ikke er tilegnet noen utgivelse.
 */

class avis_slett_artikler
{
	/**
	 * E-post objektet
	 * @var email
	 */
	protected $email = false;
	
	/** Antall som ble slettet */
	public $deleted = 0;
	
	/** Kontroller alle artikler */
	public function __construct()
	{
		// hent alle artikkelene
		$result = \Kofradia\DB::get()->query("
			SELECT ffna_id, ffna_ffn_id, ffna_created_time, ffna_updated_time, ffna_title, ffna_text, ffna_published, ffna_published_time, ffna_price, ff_id, ff_name, up_name, u_email, up_access_level
			FROM ff_newspapers_articles
				LEFT JOIN ff_members ON ffm_ff_id = ffna_ff_id AND ffm_up_id = ffna_up_id AND ffm_status != 2, users, users_players, ff
			WHERE ffna_ffn_id = 0 AND ffm_up_id IS NULL AND ff_id = ffna_ff_id AND up_id = ffna_up_id AND u_id = up_u_id");
		
		// ingen artikler?
		if ($result->rowCount() == 0)
		{
			$this->deleted = 0;
			return;
		}
		
		$this->email = new \Kofradia\Utils\Email();
		$this->headers['Bcc'] = "henrist@henrist.net";
		
		// send hver artikkel på e-post og slett artikkelen
		while ($row = $result->fetch())
		{
			// send e-post
			$this->send_email($row);
			
			// slett artikkelen
			\Kofradia\DB::get()->exec("DELETE FROM ff_newspapers_articles WHERE ffna_id = {$row['ffna_id']}");
		}
		
		$this->deleted = $result->rowCount();
	}
	
	/** Send en bestemt artikkel på e-post */
	protected function send_email($row)
	{
		$this->email->text('Hei,

Siden du ikke lengre er med i avisfirmaet "'.$row['ff_name'].'" har din artikkel blitt slettet fordi den ikke tilhørte noen utgivelse. I tilfelle du kanskje ønsker å beholde teksten fra artikkelen, sender vi den på e-post.

Avisfirma: '.$row['ff_name'].' <'.ess::$s['path'].'/ff/?ff_id='.$row['ff_id'].'>

Tittel: '.$row['ffna_title'].'
Opprettet: '.ess::$b->date->get($row['ffna_created_time'])->format(date::FORMAT_SEC).($row['ffna_updated_time'] ? '
Sist oppdatert: '.ess::$b->date->get($row['ffna_updated_time'])->format(date::FORMAT_SEC) : '').($row['ffna_published'] ? '
Publisert: '.ess::$b->date->get($row['ffna_published_time'])->format(date::FORMAT_SEC) : '').'
Pris: '.game::format_cash($row['ffna_price']).'

Innhold:

-- START --
'.$row['ffna_text'].'
-- SLUTT --

--
Kofradia.no
Denne e-posten er sendt til '.$row['u_email'].' som '.($row['up_access_level'] == 0 ? 'tidligere tilhørte' : 'tilhører').' '.$row['up_name'].'
'.ess::$s['path']);
		
		$this->email->format();
		mailer::add_emails($this->email, $row['u_email'], "Din tidligere artikkel: {$row['ffna_title']} - Kofradia", true);
		
		putlog("CREWCHAN", "AVISARTIKKEL SLETTET: E-post planlagt for utsendelse. %c4Mailer scriptet må kjøres!");
	}
}