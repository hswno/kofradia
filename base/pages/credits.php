<?php

class page_credits
{
	/**
	 * Construct
	 */
	public function __construct()
	{
		ess::$b->page->add_title("Takk til");
		
		$this->show();
		
		ess::$b->page->load();
	}
	
	/**
	 * Vis siden
	 */
	protected function show()
	{
		// css
		ess::$b->page->add_css('
.credits_wrap {
	padding: 0 9px;
}
.credits_top {
	margin: 20px 0 5px;
}
.credits_left {
	width: 49%;
	float: left;
}
.credits_right {
	width: 49%;
	float: right;
}
.credits_g_left { float: left; width: 39% }
.credits_g_right { float: right; width: 39% }');
		
		echo '
<h1>Takk til</h1>
<p>Vi ønsker å takke alle personer som har hjulpet Kofradia på en eller flere måter. Uten dem hadde ikke Kofradia vært det det er i dag, og vi setter virkelig pris på hjelpen de har bidratt med. Nedenfor er noen av de vi ønsker å takke spesielt:</p>

<div class="credits_wrap">
	<div class="section credits_top">
		<h2><user id="1" /> - Henrik Steen</h2>
		<p>Henrik er skaperen og eier av Kofradia. Han er den eneste som utvikler spillet og har overordnet ansvar og kontroll på spillet.</p>
	</div>
	
	<!-- venstre siden -->
	<div class="credits_left">
		<div class="section">
			<h2><user="InZo" /> - Anita</h2>
			<p><user="InZo" /> var med som Administrator i en lengre periode. Hun er en person man alltid kan spørre om ting, og er tilgjenglig så godt som hele tiden. Hun er en person man absolutt kan stole på, og vi skal ikke se bort ifra at hun kanskje returnerer til Crewet ved en senere anledning!</p>
		</div>
		
		<div class="section">
			<h2>Tidligere medlemmer av Crewet</h2>
			<p>Brukere som tidligere har vært medlem av Crewet, og som vi ønsker å takke spesielt.</p>
			<div class="section credits_g_left">
				<h3>Administratorer</h3>
				<ul>
					<li><user="InZo" /></li>
					<li><user="Quantization" /></li>
					<li><user="Roxar" /></li>
					<li><user="Sondre" /></li>
					<li><user="TheGodfather" /></li>
					<li><user="Xstasy" /></li>
				</ul>
				<h3>Seniormoderatorer</h3>
				<ul>
					<li><user="DjDude" /></li>
					<li><user="h0rn" /></li>
					<li><user="Marthe" /></li>
				</ul>
				<h3>Moderatorer</h3>
				<ul>
					<li><user="ElKapino" /></li>
					<li><user="Floff" /></li>
					<li><user="Goldfinger" /></li>
					<li><user="Greenboy" /></li>
					<li><user="Homecoming" /></li>
					<li><user="Speedy" /></li>
				</ul>
			</div>
			<div class="section credits_g_right">
				<h3>Forummoderatorer</h3>
				<ul>
					<li><user="aNdersK" /></li>
					<li><user="ChrisF" /></li>
					<li><user="Franc Lucas" /></li>
					<li><user="Jonas" /></li>
					<li><user="Nicko" /></li>
					<li><user="Shero" /></li>
					<li><user="Sunniva" /></li>
				</ul>
				<h3>Support Operatører</h3>
				<ul>
					<li><user="LoveToy" /></li>
					<li><user="Hardraade" /></li>
					<li><user="Mathias" /></li>
					<li><user="Modda" /></li>
					<li><user="Trakt0r" /></li>
				</ul>
				<h3>Ressurs</h3>
				<ul>
					<li><user="Floyd" /></li>
				</ul>
				<h3>Idémyldrere</h3>
				<ul>
					<li><user="BlackkoZ" /></li>
					<li><user="DeShuan Holton" /></li>
					<li><user="Ludvig" /></li>
				</ul>
			</div>
			<div class="clear"></div>
			<p>Nåværende Crew kan sees <a href="crewet">her</a>.</p>
		</div>
	</div>
	
	<!-- høyre siden -->
	<div class="credits_right">
		<div class="section">
			<h2>Donasjoner</h2>
			<p class="h_right"><a href="donasjon">Doner &raquo;</a></p>
			<p>Donasjoner er det som hjelper å holde balanse i økonomien til spillet. Vi har en del utgifter, men ingen inntekter, og derfor setter vi stor pris på de som ønsker å donere penger til oss.</p>
			<div class="section">
				<h3>Siste donasjoner</h3>';
		
		// hent donasjonene
		$result = ess::$b->db->query("SELECT d_up_id, d_time FROM donations ORDER BY d_time DESC LIMIT 15");
		if (mysql_num_rows($result) == 0)
		{
			echo '
				<p>Ingen donasjoner.</p>';
		}
		
		else
		{
			echo '
				<dl class="dd_right">';
			
			while ($row = mysql_fetch_assoc($result))
			{
				$user = $row['d_up_id'] ? '<user id="'.$row['d_up_id'].'" />' : 'Anonym';
				echo '
					<dt>'.$user.'</dt>
					<dd>'.ess::$b->date->get($row['d_time'])->format(date::FORMAT_NOTIME).'</dd>';
			}
			
			echo '
				</dl>';
		}
		
		echo '
			</div>
			<p><a href="donasjon?vis">Vis full oversikt &raquo;</a></p>
		</div>
		<div class="section">
			<h2>Annet</h2>
			<p>Mange av ikonene våre er hentet fra ikon-pakken til <a href="http://famfamfam.com/lab/icons/silk/">famfamfam.com</a>:</p>
			<p class="c"><a href="http://famfamfam.com/lab/icons/silk/"><img src="'.STATIC_LINK.'/icon/famfamfam.png" alt="Ikoner fra famfamfam.com" /></a></p>
		</div>
	</div>
</div>';
	}
}