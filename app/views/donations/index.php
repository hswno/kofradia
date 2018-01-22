<?php

// data:
// <none>

\ess::$b->page->add_js_domready('
	var b = $("donation_public");
	if (b)
	{
		b.addEvent("click", function()
		{
			var elm = $("donation_custom");
			var oldval = elm.get("value");
			elm.set("value", oldval.substring(0, oldval.length-1)+(b.get("checked") ? "1" : "0"));
		});
	}
');

?>
<div class="bg1_c small">
	<h1 class="bg1">Donasjon<span class="left"></span><span class="right"></span></h1>
	<p class="h_right"><a href="donasjon?vis">Vis donasjoner &raquo;</a></p>
	<div class="bg1">
		<p class="c" style="margin: 20px 0"><img src="&staticlink;/other/stott_kofradia_donasjon.png" alt="Støtt Kofradia - doner!" /></p>
		<p>Kofradia driver per i dag på frivillig basis og har ingen inntekt. Samtidig har vi en del utgifter for server, domene og liknende.</p>
		<p>For at vi skal kunne fortsette å tilby denne tjenesten og utvide med nye funksjoner, håper vi at folk ønsker å donere en liten pengesum til oss.</p>
		<p>Selv små donasjoner hjelper. Merk likevel at donasjoner under <b>20 NOK</b> ikke vil bli ført opp i listen over brukere som har donert.</p>
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
							<input type="hidden" name="cmd" value="_donations" />
							<input type="hidden" name="business" value="henrist@henrist.net" />
							<input type="hidden" name="item_name" value="Donasjon til Kofradia" />
							<input type="hidden" name="no_shipping" value="1" />
							<input type="hidden" name="return" value="&path;/donasjon" />
							<input type="hidden" name="cancel_return" value="&path;/donasjon" />
							<input type="hidden" name="no_note" value="1" />
							<input type="hidden" name="currency_code" value="NOK" />
							<input type="hidden" name="lc" value="no_NO" />
							<input type="hidden" name="custom" id="donation_custom" value="<?php echo (login::$logged_in ? login::$info['ses_id'].':'.login::$user->player->id : 'gjest:'.$_SERVER['REMOTE_ADDR']); ?>;public=1" />
							<input type="hidden" name="notify_url" value="&path;/donasjon/notify" />
							<dl class="dd_right dl_2x">
								<dt>Beløp</dt>
								<dd>NOK <input type="text" align="right" name="amount" size="3" value="50" class="styled w40" /></dd><?php echo (login::$logged_in ? '
								<dt><label for="donation_public">Vis mitt nick ved donasjonen</label></dt>
								<dd><input type="checkbox" id="donation_public" checked="checked" /></dd>' : ''); ?>
							</dl>
							<p class="c"><input type="image" src="https://www.paypal.com/en_US/i/btn/x-click-butcc-donate.gif" border="0" name="submit" alt="Make payments with PayPal - it's fast, free and secure!" /></p>
						</form>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
