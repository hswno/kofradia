<?php

class page_donasjon
{
	/**
	 * Construct
	 */
	public function __construct()
	{
		// vise donasjonene?
		if (isset($_GET['vis']))
		{
			$this->show_donasjoner();
		}
		
		// vis vanlig side
		else
		{
			$this->show();
		}
		
		ess::$b->page->load();
	}
	
	/**
	 * Vis donasjonene
	 */
	protected function show_donasjoner()
	{
		ess::$b->page->add_title("Donasjoner");
		
		// hent donasjonene på denne siden
		$pagei = new pagei(pagei::ACTIVE_GET, "side", pagei::PER_PAGE, 30);
		// d_time > ".(time()-2678400)."
		$result = $pagei->query("SELECT d_up_id, d_amount, d_time FROM donations ORDER BY d_time DESC");
		
		echo '
<div class="bg1_c xsmall">
	<h1 class="bg1">Donasjoner<span class="left"></span><span class="right"></span></h1>
	<p class="h_left"><a href="donasjon">&laquo; Tilbake</a></p>'.($pagei->total > 0 ? '
	<p class="h_right">Side '.$pagei->active.' av '.$pagei->pages.'</p>' : '').'
	<div class="bg1">
		<p>Denne siden viser en komplett oversikt over alle som har donert og gitt sin støtte til Kofradia.</p>';
		
		if (mysql_num_rows($result) == 0)
		{
			echo '
		<p>Ingen donasjoner er registrert.</p>';
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
		</dl>
		<p class="c">Vil du også bidra? Trykk <a href="donasjon">her</a>!</p>'.($pagei->pages > 1 ? '
		<p class="c">'.$pagei->pagenumbers().'</p>' : '');
		}
		
		echo '
	</div>
</div>';
	}
	
	/**
	 * Vis siden
	 */
	protected function show()
	{
		ess::$b->page->add_title("Donasjon");
		
		echo '
<div class="bg1_c small">
	<h1 class="bg1">Donasjon<span class="left"></span><span class="right"></span></h1>
	<p class="h_right"><a href="donasjon?vis">Vis donasjoner &raquo;</a></p>
	<div class="bg1">
		<p class="c" style="margin: 20px 0"><img src="'.STATIC_LINK.'/other/stott_kofradia_donasjon.png" alt="Støtt Kofradia - doner!" /></p>
		<p>Kofradia driver per i dag på frivillig basis og har ingen inntekt. Samtidig har vi en del utgifter for server, domene og liknende.</p>
		<p>For at vi skal kunne fortsette å tilby denne tjenesten og utvide med nye funksjoner, håper vi at folk ønsker å donere en liten pengesum til oss.</p>
		<p>Selv små donasjoner hjelper. Merk alikevel at donasjoner under <b>kr. 20</b> ikke vil bli ført opp i listen over brukere som har donert.</p>
		<p><a href="donasjon?vis">Vis oversikt over donasjoner &raquo;</a></p>
		<p><u>Donasjonsløsninger:</u></p>
		<ol>
			<li><b>Bank/nettbank:</b> Dette er løsningen som er foretrukket. Det blir som regel ikke trukket fra noe overføringsgebyr, og det er lett å overføre penger på en sikker måte.</li>
			<li style="margin-top: 10px"><b>Visa/PayPal:</b> For de som ikke har egen nettbank, men som kanskje har Visakort/MasterCard, er dette en alternativ løsning. PayPal tar noen få prosent i overføringsgebyr, så bankoverføring foretrekkes.</li>
		</ol>
		<div class="col2_w">
			<div class="col_w left">
				<div class="col">
					<div class="section">
						<h2>Bank/nettbank</h2>
						<p>Beløp overføres til kontonummer: <b>1503.02.25691</b></p>
						<p>Før opp e-postadressen din som melding hvis du ønsker ditt brukernavn oppført ved donasjonen.</p>
					</div>
				</div>
			</div>
			<div class="col_w right">
				<div class="col">
					<div class="section">
						<h2>PayPal</h2>
						<form action="https://www.paypal.com/cgi-bin/webscr" method="post">
							<input type="hidden" name="cmd" value="_xclick" />
							<input type="hidden" name="business" value="henrist@henrist.net" />
							<input type="hidden" name="item_name" value="Donasjon til Kofradia" />
							<input type="hidden" name="no_shipping" value="1" />
							<input type="hidden" name="return" value="'.ess::$s['path'].'/donasjon" />
							<input type="hidden" name="cancel_return" value="'.ess::$s['path'].'/donasjon" />
							<!--<input type="hidden" name="no_note" value="1" />-->
							<input type="hidden" name="currency_code" value="NOK" />
							<input type="hidden" name="tax" value="0" />
							<input type="hidden" name="lc" value="NO" />
							<input type="hidden" name="bn" value="PP-DonationsBF" />
							<input type="hidden" name="custom" value="'.(login::$logged_in ? login::$info['ses_id'].':'.htmlspecialchars(login::$user->data['u_email']) : 'gjest-'.$_SERVER['REMOTE_ADDR']).'" />'.(login::$logged_in ? '
							<input type="hidden" name="on0" value="show_donator" />' : '').'
							<dl class="dd_right dl_2x">
								<dt>Beløp</dt>
								<dd>NOK <input type="text" align="right" name="amount" size="3" value="50" class="styled w40" /></dd>'.(login::$logged_in ? '
								<dt><label for="os0">Vis mitt nick ved donasjonen</label></dt>
								<dd><input type="checkbox" name="os0" id="os0" value="1" checked="checked" /></dd>' : '').'
							</dl>
							<p class="c"><input type="image" src="https://www.paypal.com/en_US/i/btn/x-click-butcc-donate.gif" border="0" name="submit" alt="Make payments with PayPal - it\'s fast, free and secure!" /></p>
						</form>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>';
	}
}