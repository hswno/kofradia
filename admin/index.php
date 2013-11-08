<?php

require "../base.php";
global $_base;

echo '
<div class="bg1_c medium">
	<h1 class="bg1">Administrasjon<span class="left"></span><span class="right"></span></h1>
	<div class="bg1">
		<p><b>Merk:</b> Mange av undersidene her er ikke oppdatert til de kravene de burde ha. Derfor vil det på mange sider komme opp feilmeldinger m.v.</p>
		<div style="float: left; width: 50%">
			<div style="padding-right: 5px">
				<h2 class="bg1">Brukere<span class="left2"></span><span class="right2"></span></h2>
				<div class="bg1">
					<p><a href="brukere/finn">Finn spiller</a> <span class="c_mod">(Moderator)</span> - Finn spiller bassert på ID, spiller, IP-adresse eller e-postadresse.</p>
					<p><a href="brukere/siste_reg">Siste registrerte brukere</a> <span class="c_mod">(Moderator)</span> - Vis oversikt over brukere sortert etter sist registrert.</p>
					<p><a href="brukere/ip">IP-adresser med flere brukere</a> <span class="c_mod">(Moderator)</span> - Vis IP-adresser som har flere brukere aktive.</p>
					<p><a href="age">Beregn alder</a> <span class="c_mod">(Moderator)</span> - Beregn alder utifra fødselsdato.</p>
					<p><a href="ip_ban">IP-ban oppføringer</a> <span class="c_mod">(Moderator)</span> - Vis aktive og gamle IP-ban oppføringer.</p>
					<p><a href="brukere/ip_ban">Sett IP-ban</a> <span class="c_mod">(Moderator)</span></p>
					<p><a href="email_blacklist">Utesteng e-postadresse/domene</a> <span class="c_mod">(Moderator)</span></p>
				</div>
				<h2 class="bg1">Statistikk<span class="left2"></span><span class="right2"></span></h2>
				<div class="bg1">
					<p><a href="stats/daily_users">Brukere pålogget per dag</a> <span class="c_mod">(Moderator)</span> - Vis hvor mange brukere og antall sidevisninger det er dag til dag.</p>
				</div>
			</div>
		</div>
		<div style="float: right; width: 50%">
			<div style="padding-left: 5px">
				<h2 class="bg1">Diverse<span class="left2"></span><span class="right2"></span></h2>
				<div class="bg1">
					<p><a href="scheduler_status">Oppgaveplanlegger</a> <span class="c_mod">(Moderator)</span> - Vis aktive oppgaver og når de utføres.</p>
					<p><a href="kriminalitet/">Kriminalitet</a> <span class="c_mod">(Moderator)</span> - Legg til og endre kriminalitetoppføringene.</p>
					<p><a href="registrer_donasjon">Registrer donasjon</a> <span class="c_admin">(Administrator)</span> - Legg til en donasjon i listen over donasjoner.</p>
				</div>
				<h2 class="bg1">Server/system<span class="left2"></span><span class="right2"></span></h2>
				<div class="bg1">
					<p><a href="prosesser">Kjørende prosesser</a> <span class="c_admin">(Administrator)</span> - Vis kjørende prosesser på serveren.</p>
					<p><a href="oppetid">Oppetid/belastning</a> (Åpen)</p>
				</div>
				<h2 class="bg1">Andre ting<span class="left2"></span><span class="right2"></span></h2>
				<div class="bg1">
					<p><a href="fun/poker">Poker (originale)</a> <span class="c_mod">(Nostat)</span> - Spill poker med deg selv.</p>
					<p><a href="penger">Juster pengenivå</a> <span class="c_mod">(Moderator)</span> - Juster ditt eget pengenivå slik at du kan spille poker osv.</p>
				</div>
			</div>
		</div>
	</div>
</div>';

$_base->page->load();