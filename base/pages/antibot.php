<?php

/*
 * Anti-bot test
 */
class page_antibot
{
	protected $update_delay = 30;
	protected $retry_delay = 10;
	protected $redirect_url;
	
	/**
	 * @var antibot
	 */
	protected $antibot;
	
	protected $images;
	protected $images_time;
	protected $images_data;
	protected $images_valid;
	
	/**
	 * @var form
	 */
	protected $form;
	
	/** Har vi ventetid før vi kan utføre? */
	protected $wait;
	
	/**
	 * Construct
	 */
	public function __construct()
	{
		access::no_guest();
		ess::$b->page->add_title("Anti-bot sjekk");
		
		// hvilken side vi skal sendes til
		$this->redirect_url = getval("ret");
		if (!$this->redirect_url) $this->redirect_url = ess::$s['relative_path']."/";
		
		// sjekk etter anti-bot
		$name = getval("name");
		$this->antibot = antibot::get($name);
		if (!$this->antibot->data)
		{
			ess::$b->page->add_message("Anti-bot oppføringen er ikke opprettet.", "error");
			$this->redirect();
		}
		
		// kuler?
		if ($name == "kuler")
		{
			// gjennomfør pre-check
			if (!$this->antibot->kuler_precheck())
			{
				$this->redirect();
			}
			
			// sett lave ventetider
			$this->update_delay = 2;
			$this->retry_delay = 2;
		}
		
		redirect::store("sjekk?name=".urlencode($this->antibot->name).'&ret='.urlencode($this->redirect_url));
		
		// sjekk om anti-boten ikke krever sjekk
		if (!$this->antibot->is_check_required())
		{
			$this->redirect();
		}
		
		// sjekk for ventetid
		$this->check_wait();
		
		// hent bildene
		$this->load_images();
		
		$this->form = new form("anti-bot");
		
		// utføre sjekk?
		if (isset($_POST['valider']) && isset($this->images) && !$this->wait)
		{
			$this->check();
		}
		
		// nye bilder?
		if (isset($_POST['new']))
		{
			$this->new_imgs();
		}
		
		// vis anti-boten
		$this->show();
	}
	
	/**
	 * Sjekk for ventetid
	 */
	protected function check_wait()
	{
		// kan vi utføre med en gang?
		$this->wait = max(0, $this->antibot->data['last_try'] + $this->retry_delay - time());
		if ($this->wait > $this->retry_delay) $this->wait = 0;
	}
	
	/**
	 * Hent bildene
	 */
	protected function load_images()
	{
		$images = $this->antibot->get_images();
		if (count($images['info']) == 0)
		{
			// vi har ikke bilder for anti-boten enda, generer bilder
			$images = $this->antibot->generate_images();
			$this->antibot->update_status("test_init");
		}
		else
		{
			$this->antibot->update_status("test_repeat");
		}
		
		$this->images_time = 0;
		$this->images = $images['info'];
		$this->images_data = $images['data'];
		$this->images_valid = $images['valid'];
		
		if (count($this->images) != 6)
		{
			$this->images = NULL;
			$this->images_data = NULL;
		}
		
		else
		{
			$this->images_time = $this->images[1]['time'];
		}
	}
	
	/**
	 * Utfør sjekk
	 */
	protected function check()
	{
		$this->form->validate(postval("hash"));
		
		// ingen merket?
		if (!isset($_POST['bilde']))
		{
			ess::$b->page->add_message("Du må markere noen bilder.", "error");
		}
		
		// ugyldig?
		elseif (!is_array($_POST['bilde']))
		{
			putlog("ABUSE", "%bUGYLDIG INNTASTING I ANTI-BOT:%b _POST bilde var ikke array: {$_POST['bilde']}");
			ess::$b->page->add_message("Du må markere noen bilder.", "error");
		}
		
		// antall bilder markert
		elseif (count($_POST['bilde']) != $this->images_valid)
		{
			ess::$b->page->add_message("Du må markere ".fwords("%d bilde", "%d bilder", $this->images_valid).". Du markerte ".fwords("%d bilde", "%d bilder", count($_POST['bilde'])).".");
		}
		
		else
		{
			// sjekk om det er korrekt inntastet
			$keys = array_keys($_POST['bilde']);
			$valid = 0;
			foreach ($keys as $key)
			{
				if (isset($this->images[$key]) && $this->images[$key]['valid']) $valid++;
			}
			
			// feil bilder?
			if ($valid != $this->images_valid)
			{
				ess::$b->page->add_message("Du merket av feil bilder.", "error");
				
				// lagre logg
				$this->antibot->update_status("failed", $valid);
				
				// oppdater tid
				$this->antibot->update_time();
			}
			
			// korrekte bilder
			else
			{
				$this->antibot->valid();
				$this->redirect();
			}
		}
		
		// send til testen
		redirect::handle();
	}
	
	/**
	 * Be om nye bilder
	 */
	protected function new_imgs()
	{
		$this->form->validate(postval("hash"));
		
		// kan vi be om nye bilder nå?
		$delay = $this->images_time + $this->update_delay - time();
		if ($delay > 0 && $delay <= $this->update_delay)
		{
			ess::$b->page->add_message("Du må vente ".game::counter($delay, true)." før du kan oppdatere bildene.", "error");
			$this->antibot->update_status("new_img_wait", $delay);
			redirect::handle();
		}
		
		// slett bildene
		$this->antibot->delete_images();
		
		ess::$b->page->add_message("Du viser nye bilder.");
		$this->antibot->update_status("new_img");
		
		redirect::handle();
	}
	
	/**
	 * Vis anti-boten
	 */
	protected function show()
	{
		login::$data['antibot'][$this->antibot->data['id']] = $this->images_data;
		$hash = $this->form->create();
		
		ess::$b->page->add_css_file("sjekk.css?u");
		
		$c = str_replace(".", "", round(microtime(true), 2));
		
		echo '
<div class="bg1_c small">
	<h1 class="bg1">Anti-bot<span class="left"></span><span class="right"></span></h1>
	<div class="bg1">
		<boxes />
		<p>Dette er et virkemiddel mot bruk av programmer som spiller for deg uten at du aktivt utfører noen handlinger. Dette er juks og ikke tillatt.</p>'.($this->antibot->kuler_time_left ? '
		<p>Anti-bot må fullføres <b>før</b> kulene blir kjøpt. Du har nå reservert kulene, men for å fullføre kjøpet må anti-boten gjennomføres <b style="color: #DD0000">innen '.game::counter($this->antibot->kuler_time_left, true).'</b> sekunder.</p>' : '').'
		<p>Merk de bildene som inneholder en <u>bil</u> og trykk på &laquo;Fullfør&raquo; knappen nederst.</p>'.($this->wait ? '
		<p class="error_box">Du må vente '.game::counter($this->wait).' før du kan utføre anti-bot sjekk på nytt.</p>' : '').'
		<form action="" method="post" id="antibot_form">
			<input type="hidden" name="hash" value="'.$hash.'" />
			<div id="antibot">
				<div class="antibot_row">
					<div class="antibot_col1 box_handle box_handle_noimg">
						<img src="sjekk?a='.$this->antibot->data['id'].'1&amp;'.$c.'" alt="Bilde 1" />
						<input type="checkbox" name="bilde[1]" />
					</div>
					<div class="antibot_col2 box_handle box_handle_noimg">
						<img src="sjekk?a='.$this->antibot->data['id'].'2&amp;'.$c.'" alt="Bilde 2" />
						<input type="checkbox" name="bilde[2]" />
					</div>
					<div class="antibot_col3 box_handle box_handle_noimg">
						<img src="sjekk?a='.$this->antibot->data['id'].'3&amp;'.$c.'" alt="Bilde 3" />
						<input type="checkbox" name="bilde[3]" />
					</div>
				</div>
				<div class="antibot_row">
					<div class="antibot_col1 box_handle box_handle_noimg">
						<img src="sjekk?a='.$this->antibot->data['id'].'4&amp;'.$c.'" alt="Bilde 4" />
						<input type="checkbox" name="bilde[4]" />
					</div>
					<div class="antibot_col2 box_handle box_handle_noimg">
						<img src="sjekk?a='.$this->antibot->data['id'].'5&amp;'.$c.'" alt="Bilde 5" />
						<input type="checkbox" name="bilde[5]" />
					</div>
					<div class="antibot_col3 box_handle box_handle_noimg">
						<img src="sjekk?a='.$this->antibot->data['id'].'6&amp;'.$c.'" alt="Bilde 6" />
						<input type="checkbox" name="bilde[6]" />
					</div>
				</div>
			</div>
			<p class="c">
				'.show_sbutton("Fullfør", 'name="valider"').'
				'.show_sbutton("Nye bilder", 'name="new"').'
			</p>
		</form>
	</div>
</div>';
		
		ess::$b->page->load();
	}
	
	/**
	 * Send tilbake til den opprinnelig esiden
	 */
	protected function redirect()
	{
		redirect::handle($this->redirect_url, redirect::SERVER);
	}
}