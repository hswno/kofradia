<?php

// vise logo for avisutgivelse?
if (isset($_GET['load_logo']))
{
	function error($msg)
	{
		header("HTTP/1.1 406 Not Acceptable");
		die($msg);
	}
	
	require "../../app/essentials.php";
	
	// hente for en bestemt utgivelse?
	if (isset($_GET['ff_id']))
	{
		// hent utgivelsen
		$ff_id = intval($_GET['ff_id']);
		$ffn_id = intval($_GET['load_logo']);
		$result = ess::$b->db->query("SELECT ffn_logo FROM ff_newspapers WHERE ffn_id = $ffn_id AND ffn_ff_id = $ff_id");
		$ffn = mysql_fetch_assoc($result);
		
		// fant ikke?
		if (!$ffn)
		{
			error("Fant ikke utgivelsen.");
		}
		
		$data = mysql_result($result, 0);
	}
	
	// har ikke logo?
	if (!isset($_GET['ff_id']) || empty($data))
	{
		// bruk standard logo
		// hent data fra unknown_img.png
		$data = @file_get_contents("unknown_img.png");
	}
	
	// vis logoen
	header("Content-Type: image/png");
	header("X-Script-Time: ".round(microtime(true)-SCRIPT_START, 4));
	
	echo $data;
	die;
}

require "../base.php";

new page_ff_avis();
class page_ff_avis
{
	/**
	 * FF
	 * @var ff
	 */
	protected $ff;
	
	/**
	 * Construct
	 */
	public function __construct()
	{
		$this->ff = ff::get_ff();
		$this->ff->needtype("avis");
		
		redirect::store("avis?ff_id={$this->ff->id}");
		echo '<boxes />';
		
		$this->page_handle();
		$this->ff->load_page();
	}
	
	/**
	 * Behandle forespørsel
	 */
	protected function page_handle()
	{
		ess::$b->page->add_css_file(ess::$s['relative_path']."/ff/avis.css");
		
		// administrasjon av artikler?
		if (isset($_GET['a']))
		{
			$this->articles();
		}
		
		// administrasjon av utgivelser
		elseif (isset($_GET['u']))
		{
			$this->publications();
		}
		
		// vise en publisert utgivelse
		elseif (isset($_GET['ffn']))
		{
			$this->specific_publication();
		}
		
		// vis publiserte utgivelser
		else
		{
			$this->published();
		}
	}
	
	/**
	 * Administrasjon av artikler
	 */
	protected function articles()
	{
		$this->ff->needaccess(3);
		
		// ny artikkel?
		if (isset($_GET['new']))
		{
			$this->article_new();
		}
		
		// bestemt artikkel?
		elseif (isset($_GET['ffna']))
		{
			$this->article_handle();
		}
		
		// oversikt over alle artiklene
		else
		{
			$this->articles_list();
		}
	}
	
	/**
	 * Ny artikkel
	 */
	protected function article_new()
	{
		ess::$b->page->add_title("Ny artikkel");
		
		// legge til?
		if (isset($_POST['title']))
		{
			$title = trim(postval("title"));
			$text = trim(postval("text"));
			$text_plain = strip_tags(game::format_data($text));
			
			// sjekk tittel
			if (mb_strlen($title) < 5)
			{
				ess::$b->page->add_message("Tittelen må inneholde minimum 5 tegn.", "error");
			}
			elseif (mb_strlen($title) > 30)
			{
				ess::$b->page->add_message("Tittelen kan ikke inneholde mer enn 30 tegn.", "error");
			}
			
			// sjekk tekst
			elseif (mb_strlen($text_plain) > 10000)
			{
				ess::$b->page->add_message("Innholdet kan ikke inneholde mer enn 10 000 bokstaver/tall.", "error");
			}
			
			// legg til
			else
			{
				ess::$b->db->query("INSERT INTO ff_newspapers_articles SET ffna_ff_id = {$this->ff->id}, ffna_created_time = ".time().", ffna_up_id = ".login::$user->player->id.", ffna_title = ".ess::$b->db->quote($title).", ffna_text = ".ess::$b->db->quote($text));
				$ffna_id = ess::$b->db->insert_id();
				
				ess::$b->page->add_message("Artikkelen ble opprettet.");
				redirect::handle("avis?ff_id={$this->ff->id}&a&ffna=$ffna_id");
			}
		}
		
		echo '
<p class="c">Ny artikkel | <a href="avis?ff_id='.$this->ff->id.'&amp;a">Tilbake</a></p>
<form action="" method="post">
	<div class="section center w400">
		<h2>Ny artikkel</h2>
		<dl class="dd_right dl_2x">
			<dt>Tittel</dt>
			<dd><input type="text" name="title" value="'.htmlspecialchars(postval("title")).'" maxlength="30" class="styled w200" /></dd>
			<dt>Innhold</dt>
			<dd><textarea name="text" rows="30" cols="10" class="w300">'.htmlspecialchars(postval("text")).'</textarea></dd>
		</dl>
		<p class="c">'.show_sbutton("Opprett").'</p>
	</div>
</form>';
	}
	
	/**
	 * Spesifikk artikkel
	 */
	protected function article_handle()
	{
		$ffna = ff_avis_artikkel::get(getval("ffna"), $this->ff);
		
		// finnes ikke eller har vi ikke tilgang?
		if (!$ffna || !$ffna->access_read)
		{
			$this->ff->redirect();
		}
		
		redirect::store("avis?ff_id={$this->ff->id}&a&ffna=$ffna->id");
		
		// rediger artikkel
		if (isset($_GET['edit']))
		{
			$this->article_edit($ffna);
		}
		
		// slett artikkel
		elseif (isset($_GET['delete']) && $ffna->access_write)
		{
			$this->article_delete($ffna);
		}
		
		// publiser artikkel
		elseif (isset($_GET['publish']) && $ffna->access_write)
		{
			$this->article_publish($ffna);
		}
		
		// fjern publisering
		elseif (isset($_GET['unpublish']) && $ffna->access_write)
		{
			$this->article_publish_remove($ffna);
		}
		
		// vis informasjon
		else
		{
			$this->article_show($ffna);
		}
	}
	
	/**
	 * Redigere en artikkel
	 */
	protected function article_edit(ff_avis_artikkel $ffna)
	{
		ess::$b->page->add_title("Rediger artikkel");
		
		// publisert?
		// redaktører kan redigere publiserte artikler - også sine egne - så lenge utgivelsen ikke er publisert
		if ($ffna->data['ffna_published'] != 0 && !$this->ff->mod)
		{
			if (!$this->ff->access(2))
			{
				ess::$b->page->add_message("Artikkelen er publisert og kan ikke redigeres.", "error");
				redirect::handle();
			}
			
			elseif ($ffna->ffn && $ffna->ffn->data['ffn_published'] != 0)
			{
				ess::$b->page->add_message("Utgivelsen denne artikkelen tilhører er publisert. Artikkelen kan derfor ikke redigeres.", "error");
				redirect::handle();
			}
		}
		
		// lagre endringer?
		if (isset($_POST['title']))
		{
			$title = trim(postval("title"));
			$text = trim(postval("text"));
			$text_plain = strip_tags(game::format_data($text));
			
			// sjekk tittel
			if (mb_strlen($title) < 5)
			{
				ess::$b->page->add_message("Tittelen må inneholde minimum 5 tegn.", "error");
			}
			elseif (mb_strlen($title) > 30)
			{
				ess::$b->page->add_message("Tittelen kan ikke inneholde mer enn 30 tegn.", "error");
			}
			
			// sjekk tekst
			elseif (mb_strlen($text_plain) > 10000)
			{
				ess::$b->page->add_message("Innholdet kan ikke inneholde mer enn 10 000 bokstaver/tall.", "error");
			}
			
			// lagre endringer
			else
			{
				// ingen endringer?
				if ($title == $ffna->data['ffna_title'] && $text == $ffna->data['ffna_text'])
				{
					ess::$b->page->add_message("Ingen endringer ble utført.");
				}
				else
				{
					ess::$b->db->query("UPDATE ff_newspapers_articles SET ffna_title = ".ess::$b->db->quote($title).", ffna_text = ".ess::$b->db->quote($text).", ffna_updated_time = ".time()." WHERE ffna_id = $ffna->id");
					ess::$b->page->add_message("Endringene ble lagret.");
					
					// lagre i loggen?
					// hvis artikkelen er publisert er det kun moderator og redaktør som kan redigere
					// hvis det er en redaktør som endrer, lagre i loggen
					if ($ffna->data['ffna_published'] != 0 && !$this->ff->mod)
					{
						// data: ffna_id,up_id,ffna_up_id,ffna_title_org,ffna_title_new,ffna_text_old,ffna_text_new
						$data = array($ffna->data['ffna_id'], login::$user->player->id, $ffna->data['ffna_up_id'], $ffna->data['ffna_title'], $title, $ffna->data['ffna_text'], $text);
						$this->ff->add_log("article_edited", implode(":", array_map("urlencode", $data)));
					}
				}
				
				redirect::handle();
			}
		}
		
		echo '
<p class="c">Rediger artikkel | <a href="avis?ff_id='.$this->ff->id.'&amp;a&amp;ffna='.$ffna->id.'">Tilbake</a></p>
<form action="" method="post">
	<div class="section center w400">
		<h2>Rediger artikkel</h2>
		<dl class="dd_right dl_2x">
			<dt>Tittel</dt>
			<dd><input type="text" name="title" value="'.htmlspecialchars(postval("title", $ffna->data['ffna_title'])).'" maxlength="30" class="styled w200" /></dd>
			<dt>Innhold</dt>
			<dd><textarea name="text" rows="30" cols="10" class="w300">'.htmlspecialchars(postval("text", $ffna->data['ffna_text'])).'</textarea></dd>
		</dl>
		<p class="c">'.show_sbutton("Lagre endringer").'</p>
	</div>
</form>';
	}
	
	/**
	 * Slette en artikkel
	 */
	protected function article_delete(ff_avis_artikkel $ffna)
	{
		ess::$b->page->add_title("Slett artikkel");
		
		// publisert?
		if ($ffna->data['ffna_published'] != 0 && !$this->ff->mod)
		{
			ess::$b->page->add_message("Artikkelen er publisert og kan ikke slettes.", "error");
			redirect::handle();
		}
		
		// tilegnet en utgivelse?
		if ($ffna->data['ffna_ffn_id'] != 0)
		{
			ess::$b->page->add_message("Artikkelen er tilegnet en utgivelse og kan ikke slettes.", "error");
			redirect::handle();
		}
		
		// slette?
		if (isset($_POST['delete']))
		{
			ess::$b->db->query("DELETE FROM ff_newspapers_articles WHERE ffna_id = $ffna->id");
			
			ess::$b->page->add_message("Artikkelen ble slettet.");
			redirect::handle("avis?ff_id={$this->ff->id}&a");
		}
		
		echo '
<p class="c">Slett artikkel | <a href="avis?ff_id='.$this->ff->id.'&amp;a&amp;ffna='.$ffna->id.'">Tilbake</a></p>
<form action="" method="post">
	<div class="section center w300">
		<h2>Slett artikkel</h2>
		<dl class="dd_right">
			<dt>Tittel</dt>
			<dd>'.htmlspecialchars($ffna->data['ffna_title']).'</dd>
			<dt>Opprettet</dt>
			<dd>'.ess::$b->date->get($ffna->data['ffna_created_time'])->format().'</dd>
			<dt>Sist endret</dt>
			<dd>'.($ffna->data['ffna_updated_time'] == 0 ? 'Aldri' : ess::$b->date->get($ffna->data['ffna_updated_time'])->format()).'</dd>
		</dl>'.(($d = game::format_data($ffna->data['ffna_text'])) != "" ? '
		<div class="p">'.$d.'</div>' : '
		<p>Mangler innhold.</p>').'
		<p class="c">'.show_sbutton("Slett artikkelen", 'name="delete"').'</p>
	</div>
</form>';
	}
	
	/**
	 * Publiser artikkel
	 */
	protected function article_publish(ff_avis_artikkel $ffna)
	{
		$text = $ffna->data['ffna_text'];
		$text_plain = strip_tags(game::format_data($text));
		
		// sjekk tekst
		if (mb_strlen($text_plain) < 20)
		{
			ess::$b->page->add_message("Innholdet må inneholde minimum 20 bokstaver/tall før artikkelen kan publiseres.", "error");
			redirect::handle();
		}
		
		// publisere?
		if (isset($_POST['publish']))
		{
			$price = game::intval(postval("price"));
			
			// sjekk pris
			if ($price < 0)
			{
				ess::$b->page->add_message("Prisen kan ikke være negativ.", "error");
			}
			
			// publiser
			else
			{
				ess::$b->db->query("UPDATE ff_newspapers_articles SET ffna_published = 1, ffna_published_time = ".time().", ffna_price = $price WHERE ffna_id = $ffna->id");
				
				ess::$b->page->add_message("Artikkelen er nå publisert. Redaktøren kan nå legge til artikkelen i en utgivelse.");
				redirect::handle();
			}
		}
		
		echo '
<p class="c">Publiser artikkel | <a href="avis?ff_id='.$this->ff->id.'&amp;a&amp;ffna='.$ffna->id.'">Tilbake</a></p>
<form action="" method="post">
	<div class="section center w300">
		<h2>Publiser artikkel</h2>
		<dl class="dd_right">
			<dt>Tittel</dt>
			<dd>'.htmlspecialchars($ffna->data['ffna_title']).'</dd>
		</dl>'.(($d = game::format_data($ffna->data['ffna_text'])) != '' ? '
		<div class="p">'.$d.'</div>' : '
		<p>Mangler innhold.</p>').'
		<dl class="dd_right dl_2x">
			<dt>Salgspris</dt>
			<dd><input type="text" name="price" value="'.game::format_cash(postval("price", $ffna->data['ffna_price'])).'" class="styled w80 r" /></dd>
		</dl>
		<p>Når du publiserer artikkelen blir artikkelen synlig for redaktøren. Artikkelen kan da legges til i en utgivelse. Når utgivelsen blir publisert, vil du få utbetalt salgsprisen du oppgir nedenfor.</p>
		<p class="c">'.show_sbutton("Publiser artikkelen", 'name="publish"').'</p>
	</div>
</form>';
	}
	
	/**
	 * Fjerne publisering av avisartikkel
	 */
	protected function article_publish_remove(ff_avis_artikkel $ffna)
	{
		if ($ffna->data['ffna_published'] == 0)
		{
			ess::$b->page->add_message("Artikkelen er ikke publisert.", "error");
		}
		elseif ($ffna->data['ffna_ffn_id'] != 0)
		{
			ess::$b->page->add_message("Artikkelen er lagt med i en utgivelse og kan ikke trekkes tilbake.", "error");
		}
		else
		{
			ess::$b->db->query("UPDATE ff_newspapers_articles SET ffna_published = 0 WHERE ffna_id = $ffna->id");
			ess::$b->page->add_message("Artikkelen er ikke lengre publisert.");
		}
		
		redirect::handle();
	}
	
	/**
	 * Vis informasjon om artikkel
	 */
	protected function article_show(ff_avis_artikkel $ffna)
	{
		$more = '';
		
		// tilbake til "legg til artikkel"
		if (isset($_GET['to_ffn']) && !$ffna->ffn && $this->ff->access(2))
		{
			$ffn_id = intval(getval("to_ffn"));
			$more .= ' | <a href="avis?ff_id='.$this->ff->id.'&amp;u&amp;ffn='.$ffn_id.'&amp;add_ffna'.(isset($_GET['add_ffna']) ? '&amp;ffna='.$ffna->id : '').'">Tilbake</a>';
		}
		
		// link: redigere
		$more .= $this->ff->mod || !$ffna->ffn || $ffna->ffn->data['ffn_published'] == 0 ? ' | <a href="avis?ff_id='.$this->ff->id.'&amp;a&amp;ffna='.$ffna->id.'&amp;edit">Rediger</a>' : '';
		
		if ($ffna->access_write)
		{
			// link: slette
			$more .= $ffna->data['ffna_published'] == 0 || $this->ff->mod ? ' | <a href="avis?ff_id='.$this->ff->id.'&amp;a&amp;ffna='.$ffna->id.'&amp;delete">Slett</a>' : '';
			
			// link: publiser/fjern publisering
			$more .= $ffna->data['ffna_published'] == 0 ? ' | <a href="avis?ff_id='.$this->ff->id.'&amp;a&amp;ffna='.$ffna->id.'&amp;publish">Publiser</a>' : ($ffna->data['ffna_ffn_id'] == 0 || $this->ff->mod ? ' | <a href="avis?ff_id='.$this->ff->id.'&amp;a&amp;ffna='.$ffna->id.'&amp;unpublish">Fjern publisering</a>' : '');
		}
		
		// link: utgivelse
		$more .= $ffna->data['ffna_ffn_id'] != 0 ? ' | <a href="avis?ff_id='.$this->ff->id.'&amp;u&amp;ffn='.$ffna->data['ffna_ffn_id'].'">Vis utgivelse</a>' : '';
		
		echo '
<p class="c">'.htmlspecialchars($ffna->data['ffna_title']).$more.'</p>';
		
		// er dette en annen sin artikkel?
		if ($ffna->data['ffna_up_id'] != login::$user->player->id)
		{
			echo '
<p class="c">Denne artikkelen er laget av <user id="'.$ffna->data['ffna_up_id'].'" /></p>';
		}
		
		echo '
<div class="section center w250">
	<h2>Artikkelinformasjon</h2>
	<form action="" method="get">';
		
		foreach ($_GET as $name => $value)
		{
			if ($name == "pos") continue;
			echo '
		<input type="hidden" name="'.htmlspecialchars($name).'" value="'.htmlspecialchars($value).'" />';
		}
		
		echo '
	<dl class="dd_right">
		<dt>Opprettet</dt>
		<dd>'.ess::$b->date->get($ffna->data['ffna_created_time'])->format().'</dd>
		<dt>Sist endret</dt>
		<dd>'.($ffna->data['ffna_updated_time'] == 0 ? 'Aldri' : ess::$b->date->get($ffna->data['ffna_updated_time'])->format()).'</dd>
		<dt>Publisert</dt>
		<dd>'.($ffna->data['ffna_published'] == 0 ? 'Nei' : ess::$b->date->get($ffna->data['ffna_published_time'])->format().'</dd>
		<dt>Salgspris</dt>
		<dd>'.game::format_cash($ffna->data['ffna_price']).'</dd>
		<dt>Utgivelse</dt>
		<dd>'.($ffna->data['ffna_ffn_id'] == 0 ? 'Ingen' : '<a href="avis?ff_id='.$this->ff->id.'&amp;u&amp;ffn='.$ffna->data['ffna_ffn_id'].'">'.htmlspecialchars($ffna->ffn->data['ffn_title']).'</a>'.($ffna->ffn->data['ffn_published'] == 0 ? ' (ikke publisert)' : ' (publisert '.ess::$b->date->get($ffna->ffn->data['ffn_published_time'])->format().')'))).'</dd>
		<dt>'.($ffna->data['ffna_ffn_id'] ? 'Plassering' : 'Forhåndsvisning').'</dt>
		<dd>';
		
		// finn ut hvilken template som skal benyttes og vis informasjon
		if ($ffna->data['ffna_ffn_id'])
		{
			$template = new ff_avis_template($ffna->ffn->data['ffn_template']);
			$template->ffn = $ffna->ffn;
			$template->add_ffna($ffna->data);
			echo htmlspecialchars($template->template['areas'][$ffna->data['ffna_theme_position']]).'<br />'.htmlspecialchars($template->template['name']);
		}
		
		else
		{
			$ok = false;
			$pos_name = false;
			
			// egendefinert plassering?
			if (isset($_GET['pos']))
			{
				$pos = explode(",", $_GET['pos']);
				if (isset($pos[1]) && isset(ff_avis::$templates[$pos[0]]) && isset(ff_avis::$templates[$pos[0]]['areas'][$pos[1]]))
				{
					$ok = true;
					
					$template = new ff_avis_template($pos[0]);
					$pos_name = $pos[1];
					
					$template->add_ffna($ffna->data, $pos[1]);
					
					// lagre valget
					$params = new params_update(-1, "ff_newspapers_articles", "ffna_theme_parameters", "ffna_id = {$ffna->data['ffna_id']}");
					$params->update("template", $template->template_id);
					ess::$b->db->query("UPDATE ff_newspapers_articles SET ffna_theme_position = ".ess::$b->db->quote($pos_name)." WHERE ffna_id = {$ffna->data['ffna_id']}");
					$params->commit();
				}
			}
			
			if (!$ok)
			{
				// har vi plassering? er plasseringen fremdeles gyldig? (i tilfelle den har vært tilegnet en utgivelse men fjernet fra utgivelsen og fått ny template)
				$params = new params($ffna->data['ffna_theme_parameters']);
				$t = $params->get("template");
				if ($t && isset(ff_avis::$templates[$t]) && isset(ff_avis::$templates[$t]['areas'][$ffna->data['ffna_theme_position']]))
				{
					$template = new ff_avis_template($t);
					$pos_name = $ffna->data['ffna_theme_position'];
				}
				else
				{
					// benytt første template som standard
					$f = ff_avis::$templates;
					$k = key($f);
					$template = new ff_avis_template($k);
					$pos_name = key($template->template['areas']);
				}
				
				// legg til artikkelen i templaten
				$template->add_ffna($ffna->data, $pos_name);
			}
			
			ess::$b->page->add_js_domready('
	document.id("template_pos").addEvent("change", function()
	{
		this.form.submit();
	});');
			echo '
			<select name="pos" id="template_pos">';
			
			foreach (ff_avis::$templates as $key1 => $row1)
			{
				echo '
				<optgroup label="'.htmlspecialchars($row1['name']).'">';
				
				foreach ($row1['areas'] as $key2 => $row2)
				{
					echo '
					<option value="'.htmlspecialchars($key1).','.htmlspecialchars($key2).'"'.($template->template_id == $key1 && $pos_name == $key2 ? ' selected="selected"' : '').'>'.htmlspecialchars($row2).'</option>';
				}
				
				echo '
				</optgroup>';
			}
			
			echo '
			</select>
		';
		}
		
		// legg til dummytekst på de plasseringene det ikke er artikler
		$template->add_dummy_text();
		
		echo '</dd>
	</dl>
	</form>
</div>
<h2 class="c">Forhåndsvisning</h2>'.$template->build();
	}
	
	/**
	 * Oversikt over artikler
	 */
	protected function articles_list()
	{
		ess::$b->page->add_title("Avisartikler");
		
		// hent artiklene
		$pagei = new pagei(pagei::ACTIVE_GET, "side", pagei::PER_PAGE, 10);
		$result = $pagei->query("SELECT ffna_id, ffna_ffn_id, ffna_created_time, ffna_updated_time, ffna_title, LENGTH(ffna_text) AS ffna_text_length, ffna_published, ffna_published_time, ffn_published, ffn_published_time, ffn_title FROM ff_newspapers_articles LEFT JOIN ff_newspapers ON ffna_ffn_id = ffn_id WHERE ffna_ff_id = {$this->ff->id} AND ffna_up_id = ".login::$user->player->id." ORDER BY ffna_published, GREATEST(ffna_updated_time, ffna_created_time) DESC");
		
		echo  '
<p class="c">Avisartikler | <a href="avis?ff_id='.$this->ff->id.'&amp;a&amp;new">Opprett artikkel</a></p>
<p class="c">Denne siden viser kun <u>dine</u> artikler.</p>';
		
		// ingen artikler?
		if (mysql_num_rows($result) == 0)
		{
			echo '
<p class="c">Ingen artikler er opprettet.</p>';
		}
		
		else
		{
			// vis artiklene
			echo '
<table class="table center'.($pagei->pages == 1 ? ' tablemb' : '').'">
	<thead>
		<tr>
			<th>Tittel</th>
			<th>Tegn</th>
			<th>Opprettet</th>
			<th>Sist endret</th>
			<th>Publisert</th>
			<th>Utgivelse</th>
		</tr>
	</thead>
	<tbody>';
			
			$i = 0;
			while ($row = mysql_fetch_assoc($result))
			{
				echo '
		<tr'.(++$i % 2 == 0 ? ' class="color"' : '').'>
			<td><a href="avis?ff_id='.$this->ff->id.'&amp;a&amp;ffna='.$row['ffna_id'].'">'.htmlspecialchars($row['ffna_title']).'</a></td>
			<td class="r">'.game::format_number($row['ffna_text_length']).'</td>
			<td>'.ess::$b->date->get($row['ffna_created_time'])->format().'</td>
			<td>'.($row['ffna_updated_time'] == 0 ? 'Aldri' : ess::$b->date->get($row['ffna_updated_time'])->format()).'</td>';
				
				if ($row['ffna_published'] == 0)
				{
					echo '
			<td colspan="2">Ikke publisert</td>';
				}
				else
				{
					echo '
			<td>'.ess::$b->date->get($row['ffna_published_time'])->format().'</td>
			<td>'.($row['ffna_ffn_id'] == 0 ? 'Ingen' : '<a href="avis?ff_id='.$this->ff->id.'&amp;u&amp;ffn='.$row['ffna_ffn_id'].'">'.htmlspecialchars($row['ffn_title']).'</a><br />'.($row['ffn_published'] == 0 ? '(ikke publisert)' : '(publisert '.ess::$b->date->get($row['ffn_published_time'])->format().')')).'</td>';
				}
				
				echo '
		</tr>';
			}
			
			echo '
	</tbody>
</table>';
			
			if ($pagei->pages > 1)
			{
				echo '
<p class="c">Navigasjon: '.$pagei->pagenumbers().'</p>';
			}
		}
	}
	
	/**
	 * Administrasjon av utgivelser
	 */
	protected function publications()
	{
		$this->ff->needaccess(3);
		
		// ny utgivelse
		if (isset($_GET['new']))
		{
			$this->pub_new();
		}
		
		// bestemt utgivelse
		elseif (isset($_GET['ffn']))
		{
			$this->pub_handle();
		}
		
		// vis utgivelsene
		else
		{
			$this->pubs_list();
		}
	}
	
	/**
	 * Opprette ny utgivelse
	 */
	protected function pub_new()
	{
		$this->ff->needaccess(2);
		ess::$b->page->add_title("Ny utgivelse");
		
		// legge til?
		if (isset($_POST['title']))
		{
			$title = trim(postval("title"));
			$desc = trim(postval("desc"));
			$desc_plain = strip_tags(game::format_data($desc));
			$price = game::intval(postval("price"));
			$template = postval("template");
			
			// sjekk tittel
			if (mb_strlen($title) < 5)
			{
				ess::$b->page->add_message("Tittelen må inneholde minimum 5 tegn.", "error");
			}
			elseif (mb_strlen($title) > 35)
			{
				ess::$b->page->add_message("Tittelen kan ikke inneholde mer enn 35 tegn.", "error");
			}
			
			// sjekk beskrivelse
			/*elseif (mb_strlen($desc_plain) < 30)
			{
				ess::$b->page->add_message("Beskrivelsen må inneholde minimum 30 tegn.", "error");
			}*/
			elseif (mb_strlen($desc_plain) > 200)
			{
				ess::$b->page->add_message("Beskrivelsen kan ikke inneholde mer enn 200 tegn.", "error");
			}
			
			// ugyldig pris?
			elseif ($price < 0)
			{
				ess::$b->page->add_message("Salgsprisen kan ikke være negativ.", "error");
			}
			
			// ugyldig template?
			elseif (!$template)
			{
				ess::$b->page->add_message("Du må velge en template.", "error");
			}
			elseif (!isset(ff_avis::$templates[$template]))
			{
				ess::$b->page->add_message("Ugyldig template.", "error");
			}
			
			// legg til
			else
			{
				ess::$b->db->query("INSERT INTO ff_newspapers SET ffn_ff_id = {$this->ff->id}, ffn_template = ".ess::$b->db->quote($template).", ffn_cost = $price, ffn_title = ".ess::$b->db->quote($title).", ffn_description = ".ess::$b->db->quote($desc).", ffn_created_time = ".time().", ffn_created_up_id = ".login::$user->player->id);
				$ffn_id = ess::$b->db->insert_id();
				
				ess::$b->page->add_message("Utgivelsen ble opprettet.");
				redirect::handle("avis?ff_id={$this->ff->id}&u&ffn=$ffn_id");
			}
		}
		
		echo '
<p class="c">Ny utgivelse | <a href="avis?ff_id='.$this->ff->id.'&amp;u">Tilbake</a></p>
<form action="" method="post">
	<div class="section center w300">
		<h2>Ny utgivelse</h2>
		<dl class="dd_right dl_2x">
			<dt>Tittel</dt>
			<dd><input type="text" name="title" value="'.htmlspecialchars(postval("title")).'" maxlength="35" class="styled w200" /></dd>
			<dt>Beskrivelse</dt>
			<dd><textarea name="desc" rows="5" cols="10" class="w200">'.htmlspecialchars(postval("desc")).'</textarea></dd>
			<dt>Salgspris</dt>
			<dd><input type="text" name="price" value="'.game::format_cash(postval("price")).'" class="styled w80 r" /></dd>
			<dt>Oppsett</dt>
			<dd class="templates">';
		
		ess::$b->page->add_css('
		.templates div {
			padding: 0;
			margin-left: 3px;
			margin-bottom: 3px;
			float: right;
			cursor: pointer;
		}');
		
		$merket = isset($_POST['template']) && isset(ff_avis::$templates[$_POST['template']]) ? $_POST['template'] : '';
		foreach (ff_avis::$templates as $name => $info)
		{
			echo '
			<div class="box_handle box_handle_dark box_handle_noimg"><img width="100" src="'.$info['preview'].'" alt="'.htmlspecialchars($info['name']).'" title="'.htmlspecialchars($info['description']).'" /><input type="radio" name="template" value="'.htmlspecialchars($name).'"'.($name == $merket ? ' checked="checked"' : '').' /></div>';
		}
		
		echo '</dd>
		</dl>
		<p class="c">'.show_sbutton("Opprett").'</p>
	</div>
</form>';
	}
	
	/**
	 * Behandle en utgivelse
	 */
	protected function pub_handle()
	{
		// hent informasjon
		$ffn = ff_avis_utgivelse::get(getval("ffn"), $this->ff);
		
		// finnes ikke?
		if (!$ffn)
		{
			$this->ff->redirect();
		}
		
		redirect::store("avis?ff_id={$this->ff->id}&u&ffn=$ffn->id");
		ess::$b->page->add_title($ffn->data['ffn_title']);
		
		// rediger utgivelse
		if (isset($_GET['edit']))
		{
			$this->pub_edit($ffn);
		}
		
		// rediger salgsprisen for utgivelsen etter publisering
		elseif (isset($_GET['edit_price']))
		{
			$this->pub_price($ffn);
		}
		
		// slett utgivelse
		elseif (isset($_GET['delete']))
		{
			$this->pub_delete($ffn);
		}
		
		// last opp ny logo
		elseif (isset($_GET['logo']))
		{
			$this->pub_logo($ffn);
		}
		
		// legg til artikkel
		elseif (isset($_GET['add_ffna']))
		{
			$this->pub_ffna_add($ffn);
		}
		
		// fjern artikkel
		elseif (isset($_GET['remove_ffna']))
		{
			$this->pub_ffna_remove($ffn);
		}
		
		// flytt artikkel
		elseif (isset($_GET['move_ffna']))
		{
			$this->pub_ffna_move($ffn);
		}
		
		// forhåndsvis utgivelse
		elseif (isset($_GET['preview']))
		{
			$this->pub_preview($ffn);
		}
		
		// publiser utgivelse
		elseif (isset($_GET['publish']))
		{
			$this->pub_publish($ffn);
		}
		
		// fjern publisering
		elseif (isset($_GET['unpublish']) && $this->ff->mod)
		{
			$this->pub_publish_remove($ffn);
		}
		
		// vis informasjon
		else
		{
			$this->pub_show($ffn);
		}
	}
	
	/**
	 * Rediger utgivelse
	 */
	protected function pub_edit(ff_avis_utgivelse $ffn)
	{
		$this->ff->needaccess(2);
		ess::$b->page->add_title("Rediger utgivelse");
		
		// publisert?
		if ($ffn->data['ffn_published'] != 0 && !$this->ff->mod)
		{
			ess::$b->page->add_message("Utgivelsen er publisert og kan ikke redigeres.", "error");
			redirect::handle();
		}
		
		// lagre endringer?
		if (isset($_POST['title']))
		{
			$title = trim(postval("title"));
			$desc = trim(postval("desc"));
			$desc_plain = strip_tags(game::format_data($desc));
			$price = game::intval(postval("price"));
			
			// sjekk tittel
			if (mb_strlen($title) < 5)
			{
				ess::$b->page->add_message("Tittelen må inneholde minimum 5 tegn.", "error");
			}
			elseif (mb_strlen($title) > 35)
			{
				ess::$b->page->add_message("Tittelen kan ikke inneholde mer enn 35 tegn.", "error");
			}
			
			// sjekk beskrivelse
			elseif (mb_strlen($desc_plain) > 200)
			{
				ess::$b->page->add_message("Beskrivelsen kan ikke inneholde mer enn 200 tegn.", "error");
			}
			
			// ugyldig pris?
			elseif ($price < 0)
			{
				ess::$b->page->add_message("Salgsprisen kan ikke være negativ.", "error");
			}
			
			// lagre endringer
			else
			{
				// ingen endringer?
				if ($price == $ffn->data['ffn_cost'] && $title == $ffn->data['ffn_title'] && $desc == $ffn->data['ffn_description'])
				{
					ess::$b->page->add_message("Ingen endringer ble utført.");
				}
				else
				{
					ess::$b->db->query("UPDATE ff_newspapers SET ffn_cost = $price, ffn_title = ".ess::$b->db->quote($title).", ffn_description = ".ess::$b->db->quote($desc)." WHERE ffn_id = $ffn->id");
					ess::$b->page->add_message("Endringene ble lagret.");
				}
				
				redirect::handle();
			}
		}
		
		$template = ff_avis::$templates[$ffn->data['ffn_template']];
		
		echo '
<p class="c">Rediger utgivelse | <a href="avis?ff_id='.$this->ff->id.'&amp;u&amp;ffn='.$ffn->id.'">Tilbake</a></p>
<form action="" method="post">
	<div class="section center w300">
		<h2>Rediger utgivelse</h2>
		<dl class="dd_right dl_2x">
			<dt>Tittel</dt>
			<dd><input type="text" name="title" value="'.htmlspecialchars(postval("title", $ffn->data['ffn_title'])).'" maxlength="35" class="styled w200" /></dd>
			<dt>Beskrivelse</dt>
			<dd><textarea name="desc" rows="5" cols="10" class="w200">'.htmlspecialchars(postval("desc", $ffn->data['ffn_description'])).'</textarea></dd>
			<dt>Salgspris</dt>
			<dd><input type="text" name="price" value="'.game::format_cash(postval("price", $ffn->data['ffn_cost'])).'" class="styled w80 r" /></dd>
			<dt>Oppsett</dt>
			<dd>'.htmlspecialchars($template['name']).'</dd>
		</dl>
		<p class="c">'.show_sbutton("Lagre endringer").'</p>
	</div>
</form>';
	}
	
	/**
	 * Rediger pris etter publisering
	 */
	protected function pub_price(ff_avis_utgivelse $ffn)
	{
		$this->ff->needaccess(1);
		ess::$b->page->add_title("Rediger salgspris for utgivelse");
		
		// ikke publisert?
		if ($ffn->data['ffn_published'] == 0)
		{
			redirect::handle("avis?ff_id={$this->ff->id}&u&ffn=$ffn->id&edit");
		}
		
		// lagre endringer?
		if (isset($_POST['price']))
		{
			$price = game::intval(postval("price"));
			
			// ugyldig pris?
			if ($price < 0)
			{
				ess::$b->page->add_message("Salgsprisen kan ikke være negativ.", "error");
			}
			
			// lagre endringer
			else
			{
				// ingen endringer?
				if ($price == $ffn->data['ffn_cost'])
				{
					ess::$b->page->add_message("Ingen endringer ble utført.");
				}
				else
				{
					ess::$b->db->query("UPDATE ff_newspapers SET ffn_cost = $price WHERE ffn_id = $ffn->id");
					ess::$b->page->add_message("Endringene ble lagret.");
				}
				
				redirect::handle();
			}
		}
		
		echo '
<p class="c">Rediger salgspris for utgivelse | <a href="avis?ff_id='.$this->ff->id.'&amp;u&amp;ffn='.$ffn->id.'">Tilbake</a></p>
<form action="" method="post">
	<div class="section center w200">
		<h2>Rediger salgspris</h2>
		<p>Dette er kun mulig for '.$this->ff->type['priority'][1].' fordi utgivelsen er publisert.</p>
		<dl class="dd_right dl_2x">
			<dt>Salgspris</dt>
			<dd><input type="text" name="price" value="'.game::format_cash(postval("price", $ffn->data['ffn_cost'])).'" class="styled w80 r" /></dd>
		</dl>
		<p class="c">'.show_sbutton("Lagre endringer").'</p>
	</div>
</form>';
	}
	
	/**
	 * Slett utgivelse
	 */
	protected function pub_delete(ff_avis_utgivelse $ffn)
	{
		$this->ff->needaccess(2);
		ess::$b->page->add_title("Slett utgivelse");
		
		// publisert?
		if ($ffn->data['ffn_published'] != 0 && !$this->ff->mod)
		{
			ess::$b->page->add_message("Utgivelsen er publisert og kan ikke slettes.", "error");
			redirect::handle();
		}
		
		// finn antall artikler
		$result = ess::$b->db->query("SELECT COUNT(ffna_id) FROM ff_newspapers_articles WHERE ffna_ffn_id = $ffn->id");
		$ffna_count = mysql_result($result, 0);
		
		// kan ikke være noen artikler
		if ($ffna_count > 0)
		{
			ess::$b->page->add_message("Alle artiklene i utgivelsen må fjernes før utgivelsen selv kan slettes.", "error");
			redirect::handle();
		}
		
		// slette?
		if (isset($_POST['delete']))
		{
			ess::$b->db->query("DELETE FROM ff_newspapers WHERE ffn_id = $ffn->id");
			
			ess::$b->page->add_message("Utgivelsen ble slettet.");
			redirect::handle("avis?ff_id={$this->ff->id}&u");
		}
		
		$template = ff_avis::$templates[$ffn->data['ffn_template']];
		
		echo '
<p class="c">Slett utgivelse | <a href="avis?ff_id='.$this->ff->id.'&amp;u&amp;ffn='.$ffn->id.'">Tilbake</a></p>
<form action="" method="post">
	<div class="section center w200">
		<h2>Slett utgivelse</h2>
		<dl class="dd_right">
			<dt>Tittel</dt>
			<dd>'.htmlspecialchars($ffn->data['ffn_title']).'</dd>
			<dt>Salgspris</dt>
			<dd>'.game::format_cash($ffn->data['ffn_cost']).'</dd>
			<dt>Oppsett</dt>
			<dd>'.htmlspecialchars(ff_avis::$templates[$ffn->data['ffn_template']]['name']).'</dd>
		</dl>'.(($d = $this->ff->format_description($ffn->data['ffn_description'])) != "" ? '
		<div class="p">'.$d.'</div>' : '').'
		<p class="c">'.show_sbutton("Slett utgivelsen", 'name="delete"').'</p>
	</div>
</form>';
	}
	
	/**
	 * Rediger logo for utgivelse
	 */
	protected function pub_logo(ff_avis_utgivelse $ffn)
	{
		$this->ff->needaccess(2);
		ess::$b->page->add_title("Ny logo");
		
		// laste opp bilde?
		if (isset($_FILES['logo']))
		{
			// kontroller fil
			if (!is_uploaded_file($_FILES['logo']['tmp_name']))
			{
				ess::$b->page->add_message("Noe gikk galt. Prøv på nytt.", "error");
				redirect::handle("avis?ff_id={$this->ff->id}&u&ffn=$ffn->id&logo");
			}
			
			// hent data
			$data = file_get_contents($_FILES['logo']['tmp_name']);
			if ($data === false)
			{
				ess::$b->page->add_message("Noe gikk galt. Prøv på nytt.", "error");
				redirect::handle("avis?ff_id={$this->ff->id}&u&ffn=$ffn->id&logo");
			}
			
			// åpne med GD
			$img = imagecreatefromstring($data);
			if ($img === false)
			{
				ess::$b->page->add_message("Bildet kunne ikke bli lest. Prøv et annet bilde av type JPEG, PNG, GIF eller WBMP.", "error");
				redirect::handle("avis?ff_id={$this->ff->id}&u&ffn=$ffn->id&logo");
			}
			
			// kontroller bredde/høyde (maks 620x100) og resize
			$resize = false;
			if (imagesx($img) > 620)
			{
				$resize = true;
				$width = 620;
				$height = ($width/imagesx($img)) * imagesy($img);
			}
			if ((!$resize && imagesy($img) > 100) || ($resize && $height > 100))
			{
				$resize = true;
				$height = 100;
				$width = ($height/imagesy($img)) * imagesx($img);
			}
			
			if ($resize)
			{
				$new = imagecreatetruecolor($width, $height);
				imagecopyresampled($new, $img, 0, 0, 0, 0, $width, $height, imagesx($img), imagesy($img));
				imagedestroy($img);
				
				$img = $new;
			}
			
			// hent ut bildedata
			@ob_clean();
			imagepng($img);
			$data = ob_get_contents();
			
			imagedestroy($img);
			
			// lagre bildet til databasen
			ess::$b->db->query("UPDATE ff_newspapers SET ffn_logo = ".ess::$b->db->quote($data)." WHERE ffn_id = $ffn->id");
			
			ess::$b->page->add_message("Logoen ble oppdatert.");
			redirect::handle();
		}
		
		ess::$b->page->add_js('
function vis_bilde(elm)
{
	var e = $("img_preview");
	var s = "file://" + elm.value;
	e.innerHTML = \'<img src="" width="200" style="background-color: #000000" alt="Valgt bilde" />\';
	e.firstChild.src = s;
}');

		echo '
<p class="c">Ny logo | <a href="avis?ff_id='.$this->ff->id.'&amp;u&amp;ffn='.$ffn->id.'">Tilbake</a></p>
<div class="section center w300">
	<h2>Ny logo</h2>
	<p>Dette er logoen som vil bli vist på toppen av utgivelsen. Maks 620px i bredde og 100px i høyde.</p>
	<form action="" method="post" enctype="multipart/form-data">
		<dl class="dd_right dl_2x">
			<dt>Velg bilde</dt>
			<dd><input type="file" name="logo" onchange="vis_bilde(this)" /></dd>
			<dt>Forhåndsvisning</dt>
			<dd><div id="img_preview">Venter</div></dd>
		</dl>
		<p class="c">'.show_sbutton("Last opp logo").'</p>
	</form>
</div>';
	}
	
	/**
	 * Legg til artikkel i utgivelsen
	 */
	protected function pub_ffna_add(ff_avis_utgivelse $ffn)
	{
		$this->ff->needaccess(2);
		ess::$b->page->add_title("Legg til artikkel");
		
		// publisert?
		if ($ffn->data['ffn_published'] != 0 && !$this->ff->mod)
		{
			ess::$b->page->add_message("Utgivelsen er publisert og kan ikke endres.", "error");
			redirect::handle();
		}
		
		// antall artikler - maks 8
		$result = ess::$b->db->query("SELECT COUNT(ffna_id) FROM ff_newspapers_articles WHERE ffna_ffn_id = $ffn->id");
		if (mysql_result($result, 0) > 8)
		{
			ess::$b->page->add_message("Kan ikke legge til flere enn 8 artikler i en utgivelse.", "error");
			redirect::handle();
		}
		
		$template = ff_avis::$templates[$ffn->data['ffn_template']];
		
		// valgt artikkel?
		if (isset($_GET['ffna']))
		{
			// hent informasjon
			$ffna_id = intval(getval("ffna"));
			$result = ess::$b->db->query("SELECT ffna_id, ffna_created_time, ffna_up_id, ffna_updated_time, ffna_title, LENGTH(ffna_text) AS ffna_text_length, ffna_published_time, ffna_price FROM ff_newspapers_articles WHERE ffna_id = $ffna_id AND ffna_ff_id = {$this->ff->id} AND ffna_ffn_id = 0 AND ffna_published != 0");
			$ffna = mysql_fetch_assoc($result);
			
			if (!$ffna)
			{
				ess::$b->page->add_message("Fant ikke artikkelen.", "error");
				redirect::handle("avis?ff_id={$this->ff->id}&u&ffn=$ffn->id&add_ffna");
			}
			
			// hent alle artiklene som er lagt til i utgivelsen
			$result = ess::$b->db->query("SELECT ffna_id, ffna_title, ffna_theme_position, ffna_theme_priority FROM ff_newspapers_articles WHERE ffna_ffn_id = $ffn->id ORDER BY ffna_theme_priority");
			$articles = array();
			while ($row = mysql_fetch_assoc($result))
			{
				$articles[$row['ffna_theme_position']][] = $row;
			}
			
			// valgt plassering?
			if (isset($_POST['theme_position']))
			{
				$position = postval("theme_position");
				if (($pos = mb_strpos($position, ":")) === false)
				{
					ess::$b->page->add_message("Du må velge en plassering.", "error");
				}
				
				else
				{
					// kontroller verdier
					$area = mb_substr($position, 0, $pos);
					$priority = intval(mb_substr($position, $pos+1));
					
					if (!isset($template['areas'][$area]) || $priority <= 0)
					{
						ess::$b->page->add_message("Ugyldig plassering.", "error");
					}
					
					else
					{
						// sjekk priority
						if ((isset($articles[$area]) && $priority > count($articles[$area])+1) || (!isset($articles[$area]) && $priority != 1))
						{
							ess::$b->page->add_message("Ugyldig plassering.", "error");
						}
						
						// legg til artikkelen
						else
						{
							// flytt artikler
							ess::$b->db->query("UPDATE ff_newspapers_articles SET ffna_theme_priority = ffna_theme_priority + 1 WHERE ffna_ffn_id = $ffn->id AND ffna_theme_position = ".ess::$b->db->quote($area)." AND ffna_theme_priority >= $priority");
							
							// legg til artikkelen
							ess::$b->db->query("UPDATE ff_newspapers_articles SET ffna_ffn_id = $ffn->id, ffna_theme_position = ".ess::$b->db->quote($area).", ffna_theme_priority = $priority WHERE ffna_id = $ffna_id");
							
							ess::$b->page->add_message("Artikkelen ble tilegnet utgivelsen.");
							redirect::handle();
						}
					}
				}
			}
			
			// vis informasjon
			echo '
<p class="c">Legg til artikkel | <a href="avis?ff_id='.$this->ff->id.'&amp;u&amp;ffn='.$ffn->id.'&amp;add_ffna">Tilbake</a></p>
<div class="section center w250">
	<h2>Artikkelinformasjon</h2>
	<dl class="dd_right">
		<dt>Tittel</dt>
		<dd><a href="avis?ff_id='.$this->ff->id.'&amp;a&amp;ffna='.$ffna['ffna_id'].'&amp;to_ffn='.$ffn->id.'&amp;add_ffna">'.htmlspecialchars($ffna['ffna_title']).'</a></dd>
		<dt>Journalist</dt>
		<dd><user id="'.$ffna['ffna_up_id'].'" /></dd>
		<dt>Opprettet</dt>
		<dd>'.ess::$b->date->get($ffna['ffna_created_time'])->format().'</dd>
		<dt>Sist endret</dt>
		<dd>'.($ffna['ffna_updated_time'] == 0 ? 'Aldri' : ess::$b->date->get($ffna['ffna_updated_time'])->format()).'</dd>
		<dt>Publisert</dt>
		<dd>'.ess::$b->date->get($ffna['ffna_published_time'])->format().'</dd>
		<dt>Antall tegn</dt>
		<dd>'.game::format_number($ffna['ffna_text_length']).'</dd>
		<dt>Pris</dt>
		<dd>'.game::format_cash($ffna['ffna_price']).'</dd>
	</dl>
</div>
<form action="" method="post">
	<div class="section center w300">
		<h2>Velg plassering</h2>
		<dl class="dd_right">
			<dt>Plassering</dt>
			<dd>
				<select name="theme_position">
					<option value="">Velg plassering</option>';
			
			// vis plasseringsalterantivene
			foreach ($template['areas'] as $key => $area)
			{
				echo '
					<optgroup label="'.htmlspecialchars($area).'">';
				
				if (isset($articles[$key]))
				{
					echo '
						<option value="'.htmlspecialchars($key).':1">'.htmlspecialchars($area).' (øverst)</option>';
					
					foreach ($articles[$key] as $row)
					{
						echo '
						<option value="'.htmlspecialchars($key).':'.($row['ffna_theme_priority']+1).'">Etter &laquo;'.htmlspecialchars($row['ffna_title']).'&raquo;</option>';
					}
				}
				
				else
				{
					echo '
						<option value="'.htmlspecialchars($key).':1">'.htmlspecialchars($area).'</option>';
				}
				
				echo '
					</optgroup>';
			}
			
			echo '
				</select>
			</dd>
		</dl>
		<p class="c">'.show_sbutton("Legg til artikkel").'</p>
	</div>
</form>';
		}
		
		// vis alternativer
		else
		{
			// hent artiklene
			$pagei = new pagei(pagei::ACTIVE_GET, "side", pagei::PER_PAGE, 10);
			$result = $pagei->query("SELECT ffna_id, ffna_created_time, ffna_updated_time, ffna_title, LENGTH(ffna_text) AS ffna_text_length, ffna_published_time, ffna_price FROM ff_newspapers_articles WHERE ffna_ff_id = {$this->ff->id} AND ffna_ffn_id = 0 AND ffna_published != 0 ORDER BY ffna_published_time DESC");
			
			echo  '
<p class="c">Avisartikler | <a href="avis?ff_id='.$this->ff->id.'&amp;u&amp;ffn='.$ffn->id.'">Tilbake</a></p>
<p class="c">Denne siden viser publiserte artikler som ikke er tilegnet noen utgivelse.</p>';
			
			// ingen artikler?
			if (mysql_num_rows($result) == 0)
			{
				echo '
<p class="c">Ingen artikler er tilgjengelig.</p>';
			}
			
			else
			{
				// vis artiklene
				echo '
<table class="table center'.($pagei->pages == 1 ? ' tablemb' : '').'">
	<thead>
		<tr>
			<th>Tittel</th>
			<th>Tegn</th>
			<th>Opprettet</th>
			<th>Sist endret</th>
			<th>Publisert</th>
			<th>Pris</th>
			<th>&nbsp;</th>
		</tr>
	</thead>
	<tbody>';
				
				$i = 0;
				while ($row = mysql_fetch_assoc($result))
				{
					echo '
		<tr'.(++$i % 2 == 0 ? ' class="color"' : '').'>
			<td><a href="avis?ff_id='.$this->ff->id.'&amp;a&amp;ffna='.$row['ffna_id'].'&amp;to_ffn='.$ffn->id.'">'.htmlspecialchars($row['ffna_title']).'</a></td>
			<td class="r">'.game::format_number($row['ffna_text_length']).'</td>
			<td>'.ess::$b->date->get($row['ffna_created_time'])->format().'</td>
			<td>'.($row['ffna_updated_time'] == 0 ? 'Aldri' : ess::$b->date->get($row['ffna_updated_time'])->format()).'</td>
			<td>'.ess::$b->date->get($row['ffna_published_time'])->format().'</td>
			<td class="r">'.game::format_cash($row['ffna_price']).'</td>
			<td><a href="avis?ff_id='.$this->ff->id.'&amp;u&amp;ffn='.$ffn->id.'&amp;add_ffna&amp;ffna='.$row['ffna_id'].'">Legg til</a></td>
		</tr>';
				}
				
				echo '
	</tbody>
</table>';
				
				if ($pagei->pages > 1)
				{
					echo '
<p class="c">Navigasjon: '.$pagei->pagenumbers().'</p>';
				}
			}
		}
	}
	
	/**
	 * Fjern artikkel fra utgivelse
	 */
	protected function pub_ffna_remove(ff_avis_utgivelse $ffn)
	{
		$this->ff->needaccess(2);
		
		// finn artikkelen
		$ffna_id = intval(getval("remove_ffna"));
		$result = ess::$b->db->query("SELECT ffna_id, ffna_ffn_id, ffna_theme_position, ffna_theme_priority FROM ff_newspapers_articles WHERE ffna_id = $ffna_id");
		$ffna = mysql_fetch_assoc($result);
		
		// fant ikke?
		if (!$ffna || $ffna['ffna_ffn_id'] != $ffn->id)
		{
			ess::$b->page->add_message("Fant ikke artikkelen.", "error");
			redirect::handle();
		}
		
		// fjern
		ess::$b->db->query("UPDATE ff_newspapers_articles SET ffna_ffn_id = 0 WHERE ffna_id = $ffna_id AND ffna_ffn_id = $ffn->id");
		
		// flytt andre artikler
		ess::$b->db->query("UPDATE ff_newspapers_articles SET ffna_theme_priority = ffna_theme_priority - 1 WHERE ffna_ffn_id = $ffn->id AND ffna_theme_position = ".ess::$b->db->quote($ffna['ffna_theme_position'])." AND ffna_theme_priority > {$ffna['ffna_theme_priority']}");
		
		ess::$b->page->add_message("Artikkelen ble fjernet.");
		redirect::handle();
	}
	
	/**
	 * Flytt artikkel rundt i utgivelsen
	 */
	protected function pub_ffna_move(ff_avis_utgivelse $ffn)
	{
		$this->ff->needaccess(2);
		ess::$b->page->add_title("Flytt artikkel");
		
		// publisert?
		if ($ffn->data['ffn_published'] != 0 && !$this->ff->mod)
		{
			ess::$b->page->add_message("Utgivelsen er publisert og kan ikke endres.", "error");
			redirect::handle();
		}
		
		// hent informasjon
		$ffna_id = intval(getval("move_ffna"));
		$result = ess::$b->db->query("SELECT ffna_id, ffna_created_time, ffna_up_id, ffna_updated_time, ffna_title, LENGTH(ffna_text) AS ffna_text_length, ffna_theme_position, ffna_theme_priority, ffna_published_time, ffna_price FROM ff_newspapers_articles WHERE ffna_id = $ffna_id AND ffna_ffn_id = $ffn->id");
		$ffna = mysql_fetch_assoc($result);
		
		if (!$ffna)
		{
			ess::$b->page->add_message("Fant ikke artikkelen.", "error");
			redirect::handle();
		}
		
		// hent alle artiklene som er lagt til i utgivelsen
		$result = ess::$b->db->query("SELECT ffna_id, ffna_title, ffna_theme_position, ffna_theme_priority FROM ff_newspapers_articles WHERE ffna_ffn_id = $ffn->id ORDER BY ffna_theme_priority");
		$articles = array();
		while ($row = mysql_fetch_assoc($result))
		{
			$articles[$row['ffna_theme_position']][$row['ffna_id']] = $row;
		}
		
		$template = ff_avis::$templates[$ffn->data['ffn_template']];
		
		// valgt plassering?
		if (isset($_POST['theme_position']))
		{
			$position = postval("theme_position");
			if (($pos = mb_strpos($position, ":")) === false)
			{
				ess::$b->page->add_message("Du må velge en plassering.", "error");
			}
			
			else
			{
				// kontroller verdier
				$area = mb_substr($position, 0, $pos);
				$priority = intval(mb_substr($position, $pos+1));
				$add = $area == $ffna['ffna_theme_position'] ? 0 : 1;
				
				if (!isset($template['areas'][$area]) || $priority <= 0)
				{
					ess::$b->page->add_message("Ugyldig plassering.", "error");
				}
				
				else
				{
					// sjekk priority
					if ((isset($articles[$area]) && $priority > count($articles[$area])+$add) || (!isset($articles[$area]) && $priority != 1))
					{
						ess::$b->page->add_message("Ugyldig plassering.", "error");
					}
					
					// samme?
					elseif ($area == $ffna['ffna_theme_position'] && $priority == $ffna['ffna_theme_priority'])
					{
						ess::$b->page->add_message("Ingen endringer ble utført.");
						redirect::handle();
					}
					
					// flytt artikkelen
					else
					{
						// flytt artikler (for fjerning)
						ess::$b->db->query("UPDATE ff_newspapers_articles SET ffna_theme_priority = ffna_theme_priority - 1 WHERE ffna_ffn_id = $ffn->id AND ffna_theme_position = ".ess::$b->db->quote($ffna['ffna_theme_position'])." AND ffna_theme_priority > {$ffna['ffna_theme_priority']}");
						
						// flytt artikler (for oppretting/flytting)
						ess::$b->db->query("UPDATE ff_newspapers_articles SET ffna_theme_priority = ffna_theme_priority + 1 WHERE ffna_ffn_id = $ffn->id AND ffna_theme_position = ".ess::$b->db->quote($area)." AND ffna_theme_priority >= $priority");
						
						// oppdater artikkelen
						ess::$b->db->query("UPDATE ff_newspapers_articles SET ffna_theme_position = ".ess::$b->db->quote($area).", ffna_theme_priority = $priority WHERE ffna_id = $ffna_id");
						
						ess::$b->page->add_message("Artikkelen ble flyttet.");
						redirect::handle();
					}
				}
			}
		}
		
		// vis informasjon
		echo '
<p class="c">Flytt artikkel | <a href="avis?ff_id='.$this->ff->id.'&amp;u&amp;ffn='.$ffn->id.'">Tilbake</a></p>
<div class="section center w250">
	<h2>Artikkelinformasjon</h2>
	<dl class="dd_right">
		<dt>Tittel</dt>
		<dd><a href="avis?ff_id='.$this->ff->id.'&amp;a&amp;ffna='.$ffna['ffna_id'].'&amp;to_ffn='.$ffn->id.'">'.htmlspecialchars($ffna['ffna_title']).'</a></dd>
		<dt>Journalist</dt>
		<dd><user id="'.$ffna['ffna_up_id'].'" /></dd>
		<dt>Opprettet</dt>
		<dd>'.ess::$b->date->get($ffna['ffna_created_time'])->format().'</dd>
		<dt>Sist endret</dt>
		<dd>'.ess::$b->date->get($ffna['ffna_updated_time'])->format().'</dd>
		<dt>Publisert</dt>
		<dd>'.ess::$b->date->get($ffna['ffna_published_time'])->format().'</dd>
		<dt>Antall tegn</dt>
		<dd>'.game::format_number($ffna['ffna_text_length']).'</dd>
		<dt>Pris</dt>
		<dd>'.game::format_cash($ffna['ffna_price']).'</dd>
	</dl>
</div>
<form action="" method="post">
	<div class="section center w300">
		<h2>Velg plassering</h2>
		<dl class="dd_right">
			<dt>Plassering</dt>
			<dd>
				<select name="theme_position">
					<option value="">Velg plassering</option>';
		
		$pos = $ffna['ffna_theme_position'];
		$pri = $ffna['ffna_theme_priority'];
		
		// vis plasseringsalterantivene
		foreach ($template['areas'] as $key => $area)
		{
			$here = $key == $pos;
			$add = 1;
			
			echo '
					<optgroup label="'.htmlspecialchars($area).'">';
			
			if (isset($articles[$key]))
			{
				echo '
						<option value="'.htmlspecialchars($key).':1"'.($here && $pri == 1 ? ' selected="selected"' : '').'>'.htmlspecialchars($area).' (øverst)'.($here && $pri == 1 ? ' (valgt)' : '').'</option>';
				
				foreach ($articles[$key] as $row)
				{
					if ($ffna['ffna_id'] == $row['ffna_id'])
					{
						$add = 0;
						continue;
					}
					
					echo '
						<option value="'.htmlspecialchars($key).':'.($row['ffna_theme_priority']+$add).'"'.($here && $pri == $row['ffna_theme_priority']+1 ? ' selected="selected"' : '').'>Etter &laquo;'.htmlspecialchars($row['ffna_title']).'&raquo;'.($here && $pri == $row['ffna_theme_priority']+1 ? ' (valgt)' : '').'</option>';
				}
			}
			
			else
			{
				echo '
						<option value="'.htmlspecialchars($key).':1"'.($here && $pri == 1 ? ' selected="selected"' : '').'>'.htmlspecialchars($area).($here && $pri == 1 ? ' (Valgt)' : '').'</option>';
			}
			
			echo '
					</optgroup>';
		}
		
		echo '
				</select>
			</dd>
		</dl>
		<p class="c">'.show_sbutton("Flytt artikkel").'</p>
	</div>
</form>';
	}
	
	/**
	 * Forhåndsvis utgivelse
	 */
	protected function pub_preview(ff_avis_utgivelse $ffn)
	{
		// publisert?
		if ($ffn->data['ffn_published'] != 0 && !$this->ff->mod)
		{
			redirect::handle("avis?ff_id={$this->ff->id}&ffn=$ffn->id");
		}
		
		// hent data
		$data = $ffn->build_avis_html();
		
		echo '
<p class="c">'.htmlspecialchars($ffn->data['ffn_title']).' | <a href="avis?ff_id='.$this->ff->id.'&amp;u&amp;ffn='.$ffn->id.'">Tilbake</a></p>';
		
		echo $data;
	}
	
	/**
	 * Publiser utgivelse
	 */
	protected function pub_publish(ff_avis_utgivelse $ffn)
	{
		// allerede publisert
		if ($ffn->data['ffn_published'] != 0)
		{
			ess::$b->page->add_message("Utgivelsen er allerede publisert.", "error");
			redirect::handle();
		}
		
		// kontroller beskrivelse
		$desc_plain = strip_tags(game::format_data($ffn->data['ffn_description']));
		
		if (mb_strlen($desc_plain) < 30)
		{
			ess::$b->page->add_message("Beskrivelsen for utgivelsen må inneholde minimum 30 bokstaver/tall før den kan publiseres.", "error");
			redirect::handle();
		}
		
		// sjekk når siste publisering ble utført
		$result = ess::$b->db->query("SELECT ffn_published_time FROM ff_newspapers WHERE ffn_ff_id = {$this->ff->id} AND ffn_published != 0 ORDER BY ffn_published_time DESC LIMIT 1");
		$last = mysql_num_rows($result) > 0 ? mysql_result($result, 0) : 0;
		
		// har det gått lang nok tid?
		$delay = ff_avis::FFN_PUBLISH_DELAY+$last - time();
		if ($delay > 0)
		{
			ess::$b->page->add_message("Du må vente ".game::timespan(ff_avis::FFN_PUBLISH_DELAY, game::TIME_FULL)." mellom hver utgivelse som utføres. Gjenstår før neste utgivelse kan publiseres: ".game::timespan($delay, game::TIME_FULL).".", "error");
			redirect::handle();
		}
		
		// hent artiklene med pris
		$result = ess::$b->db->query("SELECT ffna_id, ffna_up_id, ffna_title, ffna_price FROM ff_newspapers_articles WHERE ffna_ffn_id = $ffn->id ORDER BY ffna_title");
		
		// for få artikler?
		if (mysql_num_rows($result) < 4)
		{
			ess::$b->page->add_message("Utgivelsen må inneholde minimum 4 artikler for å bli publisert.", "error");
			redirect::handle();
		}
		
		// kun eier
		if (!$this->ff->access(1))
		{
			ess::$b->page->add_message("Kun ".htmlspecialchars($this->ff->type['priority'][1])." kan publisere utgivelsen.", "error");
			redirect::handle();
		}
		
		// finn total pris for artiklene
		$articles = array();
		$articles_price = 0;
		while ($row = mysql_fetch_assoc($result))
		{
			$articles_price += $row['ffna_price'];
			$articles[] = $row;
		}
		
		$total_price = $articles_price + ff_avis::FFN_PUBLISH_COST;
		
		// godkjenne publisering?
		if (isset($_POST['approve']))
		{
			$price = game::intval(postval("price"));
			$sid = postval("sid");
			
			// kontroller pris
			if ($price != $total_price)
			{
				ess::$b->page->add_message("Det ser ut som prisen har endret seg. Prøv på nytt.", "error");
			}
			
			// kontroller sid
			elseif ($sid != login::$info['ses_id'])
			{
				ess::$b->page->add_message("Ugyldig.", "error");
			}
			
			// kontroller pengenivået i firma banken
			elseif ($total_price > 0 && $this->ff->data['ff_bank'] < $total_price)
			{
				ess::$b->page->add_message("Det er ikke nok penger i firmabanken.", "error");
			}
			
			else
			{
				ess::$b->db->begin();
				
				// trekk fra pengene fra firmabanken
				if ($total_price > 0 && !$this->ff->bank(ff::BANK_BETALING, $total_price, "Publisering av utgivelse: {$ffn->data['ffn_title']} (id: $ffn->id)"))
				{
					ess::$b->db->rollback();
					ess::$b->page->add_message("Det er ikke nok penger i firmabanken.", "error");
				}
				
				else
				{
					// oppdater utgivelsen
					ess::$b->db->query("UPDATE ff_newspapers SET ffn_published = 1, ffn_published_time = ".time().", ffn_published_up_id = ".login::$user->player->id." WHERE ffn_id = $ffn->id");
					
					// utbetal til journalistene
					ess::$b->db->query("UPDATE users_players, ff_members, (SELECT ffna_up_id, SUM(ffna_price) AS ffna_sum, COUNT(ffna_price) AS ffna_count FROM ff_newspapers_articles WHERE ffna_ffn_id = $ffn->id AND ffna_price > 0 GROUP BY ffna_up_id) AS ref SET up_bank = up_bank + ffna_sum, up_bank_received = up_bank_received + ffna_sum, up_bank_profit = up_bank_profit + ffna_sum, up_bank_num_received = up_bank_num_received + ffna_count, up_log_new = up_log_new + ffna_count, ffm_earnings = ffm_earnings + ffna_sum WHERE ffna_up_id = up_id AND ffm_up_id = up_id AND ffm_ff_id = {$this->ff->id}");
					
					// lagre overføringslogg
					ess::$b->db->query("INSERT INTO bank_log (bl_sender_up_id, bl_receiver_up_id, amount, time) SELECT ".login::$user->player->id.", ffna_up_id, ffna_price, ".time()." FROM users_players, ff_newspapers_articles WHERE ffna_ffn_id = $ffn->id AND ffna_up_id = up_id AND ffna_price > 0");
					
					// spillelogg
					ess::$b->db->query("INSERT INTO users_log (time, ul_up_id, type, note, num) SELECT ".time().", ffna_up_id, ".gamelog::$items['bankoverforing'].", CONCAT(ffna_price, ':Utbetaling for avisartikkel.'), ".login::$user->player->id." FROM users_players, ff_newspapers_articles WHERE ffna_ffn_id = $ffn->id AND ffna_up_id = up_id AND ffna_price > 0");
					
					ess::$b->db->commit();
					
					// live-feed
					livefeed::add_row('Avisutgivelsen <a href="'.ess::$s['relative_path'].'/ff/avis?ff_id='.$this->ff->id.'&amp;ffn='.$ffn->id.'">'.htmlspecialchars($ffn->data['ffn_title']).'</a> ble publisert av <a href="'.ess::$s['relative_path'].'/ff/?ff_id='.$this->ff->id.'">'.htmlspecialchars($this->ff->data['ff_name']).'</a>.');
					
					ess::$b->page->add_message("Utgivelsen er nå publisert.");
					redirect::handle();
				}
			}
		}
		
		echo '
<p class="c">Publiser utgivelse | <a href="avis?ff_id='.$this->ff->id.'&amp;u&amp;ffn='.$ffn->id.'">Tilbake</a></p>
<div class="section center w200">
	<h2>Utgivelsesinformasjon</h2>
	<dl class="dd_right">
		<dt>Tittel</dt>
		<dd>'.htmlspecialchars($ffn->data['ffn_title']).'</dd>
		<dt>Opprettet</dt>
		<dd>'.ess::$b->date->get($ffn->data['ffn_created_time'])->format().'</dd>
		<dt>Salgspris</dt>
		<dd>'.game::format_cash($ffn->data['ffn_cost']).'</dd>
	</dl>
</div>
<div class="section center w200">
	<h2>Beskrivelse av utgivelsen</h2>'.(($d = $this->ff->format_description($ffn->data['ffn_description'])) != '' ? '
	<div class="p">'.$d.'</div>' : '
	<p>Ingen beskrivelse.</p>').'
</div>
<form action="" method="post">
	<input type="hidden" name="sid" value="'.login::$info['ses_id'].'" />
	<input type="hidden" name="price" value="'.$total_price.'" />
	<div class="section center w350">
		<h2>Prisoversikt</h2>
		<dl class="dd_right">
			<dt>Publisering av utgivelse</dt>
			<dd>'.game::format_cash(ff_avis::FFN_PUBLISH_COST).'</dd>';
		
		foreach ($articles as $row)
		{
			echo '
			<dt><a href="avis?ff_id='.$this->ff->id.'&amp;a&amp;ffna_id='.$row['ffna_id'].'">'.htmlspecialchars($row['ffna_title']).'</a> (<user id="'.$row['ffna_up_id'].'" />)</dt>
			<dd>'.game::format_cash($row['ffna_price']).'</dd>';
		}
		
		echo '
		</dl>
		<dl class="dd_right">
			<dt>I firmabanken</dt>
			<dd>'.game::format_cash($this->ff->data['ff_bank']).'</dd>
			<dt><u>Samlet kostnader</u></dt>
			<dd><u>'.game::format_cash($total_price).'</u></dd>
		</dl>
		<p class="c">'.show_sbutton("Publiser utgivelsen", 'name="approve"').'</p>
	</div>
</form>
<div class="hr fhr"><hr /></div>';
		
		// vis utgivelsen
		echo $ffn->build_avis_html();
	}
	
	/**
	 * Trekk tilbake utgivelse fra publisering
	 */
	protected function pub_publish_remove(ff_avis_utgivelse $ffn)
	{
		if ($ffn->data['ffn_published'] == 0)
		{
			ess::$b->page->add_message("Utgivelsen er ikke publisert.", "error");
		}
		else
		{
			ess::$b->db->query("UPDATE ff_newspapers SET ffn_published = 0 WHERE ffn_id = $ffn->id");
			ess::$b->page->add_message("Utgivelsen er ikke lengre publisert.");
		}
		
		redirect::handle();
	}
	
	/**
	 * Vis informasjon om utgivelse
	 */
	protected function pub_show(ff_avis_utgivelse $ffn)
	{
		$access2 = ($ffn->data['ffn_published'] == 0 && $this->ff->access(2));
		
		// link: redigere
		$more = $access2 || $this->ff->mod ? ' | <a href="avis?ff_id='.$this->ff->id.'&amp;u&amp;ffn='.$ffn->id.'&amp;edit">Rediger</a>' : '';
		
		// link: rediger salgspris
		$more .= $ffn->data['ffn_published'] != 0 && $this->ff->access(1) ? ' | <a href="avis?ff_id='.$this->ff->id.'&amp;u&amp;ffn='.$ffn->id.'&amp;edit_price">Endre salgspris</a>' : '';
		
		// link: slette
		$more .= $access2 || $this->ff->mod ? ' | <a href="avis?ff_id='.$this->ff->id.'&amp;u&amp;ffn='.$ffn->id.'&amp;delete">Slett</a>' : '';
		
		// link: ny logo
		$more .= $access2 || $this->ff->mod ? ' | <a href="avis?ff_id='.$this->ff->id.'&amp;u&amp;ffn='.$ffn->id.'&amp;logo">Last opp ny logo</a>' : '';
		
		// link: ny artikkel
		$more .= $access2 || $this->ff->mod ? ' | <a href="avis?ff_id='.$this->ff->id.'&amp;u&amp;ffn='.$ffn->id.'&amp;add_ffna">Legg til artikkel</a>' : '';
		
		// link: forhåndsvis
		$more .= $ffn->data['ffn_published'] == 0 ? ' | <a href="avis?ff_id='.$this->ff->id.'&amp;u&amp;ffn='.$ffn->id.'&amp;preview">Forhåndsvis</a>' : ' | <a href="avis?ff_id='.$this->ff->id.'&amp;ffn='.$ffn->id.'">Vis publisert utgivelse</a>';
		
		// link: publiser/fjern publisering
		if ($this->ff->access(1) || $this->ff->mod)
		{
			$more .= $ffn->data['ffn_published'] == 0 ? ' | <a href="avis?ff_id='.$this->ff->id.'&amp;u&amp;ffn='.$ffn->id.'&amp;publish">Publiser</a>' : ($this->ff->mod ? ' | <a href="avis?ff_id='.$this->ff->id.'&amp;u&amp;ffn='.$ffn->id.'&amp;unpublish">Fjern publisering</a>' : '');
		}
		
		echo '
<p class="c">'.htmlspecialchars($ffn->data['ffn_title']).$more.'</p>
<div class="section center w200">
	<h2>Beskrivelse av utgivelsen</h2>'.(($d = $this->ff->format_description($ffn->data['ffn_description'])) != '' ? '
	<div class="p">'.$d.'</div>' : '
	<p>Ingen beskrivelse.</p>').'
</div>';
		
		// vis artiklene i utgivelsen
		echo '
<div class="hr fhr"><hr /></div>
<p class="c">Artikler i utgivelsen</p>';
		
		// hent artiklene
		$result = ess::$b->db->query("SELECT ffna_id, ffna_up_id, ffna_title, LENGTH(ffna_text) AS ffna_text_length, ffna_theme_position, ffna_theme_priority, ffna_price FROM ff_newspapers_articles WHERE ffna_ffn_id = $ffn->id ORDER BY ffna_theme_position, ffna_theme_priority");
		
		// ingen artikler
		if (mysql_num_rows($result) == 0)
		{
			echo '
<p class="c">Ingen artikler er tilegnet utgivelsen | <a href="avis?ff_id='.$this->ff->id.'&amp;u&amp;ffn='.$ffn->id.'&amp;add_ffna">Legg til artikkel</a></p>';
		}
		
		else
		{
			echo '
<table class="table center tablemb">
	<thead>
		<tr>
			<th>Tittel</th>
			<th>Tegn</th>
			<th>Bruker</th>
			<th>Plassering</th>
			<th>Pris</th>'.($ffn->data['ffn_published'] == 0 || $this->ff->mod ? '
			<th>&nbsp;</th>
			<th>&nbsp;</th>' : '').'
		</tr>
	</thead>
	<tbody>';
			
			$access = $this->ff->access(2);
			$template = ff_avis::$templates[$ffn->data['ffn_template']];
			
			$i = 0;
			while ($row = mysql_fetch_assoc($result))
			{
				echo '
		<tr'.(++$i % 2 == 0 ? ' class="color"' : '').'>'.(login::$logged_in && ($access || $row['ffna_up_id'] == login::$user->player->id) ? '
			<td><a href="avis?ff_id='.$this->ff->id.'&amp;a&amp;ffna='.$row['ffna_id'].'">'.htmlspecialchars($row['ffna_title']).'</a></td>' : '
			<td>'.htmlspecialchars($row['ffna_title']).'</td>').'
			<td class="r">'.game::format_number($row['ffna_text_length']).'</td>
			<td><user id="'.$row['ffna_up_id'].'" /></td>
			<td>'.htmlspecialchars($template['areas'][$row['ffna_theme_position']]).' ('.$row['ffna_theme_priority'].')</td>
			<td class="r">'.game::format_cash($row['ffna_price']).'</td>'.($ffn->data['ffn_published'] == 0 || $this->ff->mod ? '
			<td><a href="avis?ff_id='.$this->ff->id.'&amp;u&amp;ffn='.$ffn->id.'&amp;remove_ffna='.$row['ffna_id'].'">Fjern</a></td>
			<td><a href="avis?ff_id='.$this->ff->id.'&amp;u&amp;ffn='.$ffn->id.'&amp;move_ffna='.$row['ffna_id'].'">Flytt</a></td>' : '').'
		</tr>';
			}
			
			echo '
	</tbody>
</table>';
		}
	}
	
	/**
	 * Vis liste over utgivelsene i forbindelse med administrasjon
	 */
	protected function pubs_list()
	{
		ess::$b->page->add_title("Avisutgivelser", "Administrasjon");
		
		// hent utgivelsene
		$pagei = new pagei(pagei::ACTIVE_GET, "side", pagei::PER_PAGE, 10);
		$result = $pagei->query("SELECT ffn_id, ffn_published, ffn_published_time, ffn_cost, ffn_sold, ffn_income, ffn_title, ffn_created_time FROM ff_newspapers WHERE ffn_ff_id = {$this->ff->id} ORDER BY ffn_created_time DESC");
		
		echo  '
<p class="c">Avisutgivelser | <a href="avis?ff_id='.$this->ff->id.'&amp;u&amp;new">Opprett utgivelse</a></p>';
		
		// ingen utgivelser?
		if (mysql_num_rows($result) == 0)
		{
			echo '
<p class="c">Ingen utgivelser er opprettet.</p>';
		}
		
		else
		{
			// vis utgivelsene
			echo '
<table class="table center'.($pagei->pages == 1 ? ' tablemb' : '').'">
	<thead>
		<tr>
			<th>Tittel</th>
			<th>Publisert</th>
			<th>Solgt</th>
			<th>Inntekt</th>
			<th>Pris</th>
			<th>Opprettet</th>
		</tr>
	</thead>
	<tbody>';
			
			$i = 0;
			while ($row = mysql_fetch_assoc($result))
			{
				echo '
		<tr'.(++$i % 2 == 0 ? ' class="color"' : '').'>
			<td><a href="avis?ff_id='.$this->ff->id.'&amp;u&amp;ffn='.$row['ffn_id'].'">'.htmlspecialchars($row['ffn_title']).'</a></td>';
				
				// publisert?
				if ($row['ffn_published'] != 0)
				{
					echo '
			<td>'.ess::$b->date->get($row['ffn_published_time'])->format().'</td>
			<td class="r">'.game::format_number($row['ffn_sold']).'</td>
			<td class="r">'.game::format_cash($row['ffn_income']).'</td>
			<td class="r">'.game::format_cash($row['ffn_cost']).'</td>';
				}
				else
				{
					echo '
			<td colspan="3">Ikke publisert</td>
			<td class="r">'.game::format_cash($row['ffn_cost']).'</td>';
				}
				
				echo '
			<td>'.ess::$b->date->get($row['ffn_created_time'])->format().'</td>
		</tr>';
			}
			
			echo '
	</tbody>
</table>';
			
			if ($pagei->pages > 1)
			{
				echo '
<p class="c">Navigasjon: '.$pagei->pagenumbers().'</p>';
			}
		}
	}
	
	/**
	 * Vis en bestemt utgivelse
	 */
	protected function specific_publication()
	{
		// hent informasjon
		$ffn = ff_avis_utgivelse::get(intval(getval("ffn")), $this->ff);
		
		// fant ikke?
		if (!$ffn)
		{
			ess::$b->page->add_message("Fant ikke utgivelsen.", "error");
			$this->ff->load_page();
		}
		
		redirect::store("avis?ff_id={$this->ff->id}&ffn=$ffn->id");
		ess::$b->page->add_title($ffn->data['ffn_title']);
		
		// hent kjøpsinformasjon
		$ffnp = null;
		if (login::$logged_in)
		{
			$result = ess::$b->db->query("SELECT ffnp_cost, ffnp_time FROM ff_newspapers_payments WHERE ffnp_ffn_id = $ffn->id AND ffnp_up_id = ".login::$user->player->id);
			$ffnp = mysql_fetch_assoc($result);
		}
		
		// ikke publisert?
		if ($ffn->data['ffn_published'] == 0)
		{
			if (!$this->ff->access(3))
			{
				ess::$b->page->add_message("Fant ikke utgivelsen.", "error");
				$this->ff->load_page();
			}
			
			redirect::handle("avis?ff_id={$this->ff->id}&u&ffn=$ffn->id");
		}
		
		// må vi kjøpe avisutgivelsen?
		if ($this->ff->active && (!login::$logged_in || (!$this->ff->access() && !access::is_nostat() && !$ffnp)))
		{
			// har ikke kjøpt avisen, men avisen er gratis?
			if (login::$logged_in && (!$ffnp && $ffn->data['ffn_cost'] == 0))
			{
				// opprett rad for betaling
				ess::$b->db->query("INSERT INTO ff_newspapers_payments SET ffnp_ffn_id = $ffn->id, ffnp_up_id = ".login::$user->player->id.", ffnp_cost = {$ffn->data['ffn_cost']}, ffnp_time = ".time());
				
				// oppdater utgivelsen
				ess::$b->db->query("UPDATE ff_newspapers SET ffn_sold = ffn_sold + 1, ffn_income = ffn_income + {$ffn->data['ffn_cost']} WHERE ffn_id = $ffn->id");
				
				redirect::handle();
			}
			
			// må kjøpe avisen
			else
			{
				ess::$b->page->add_title("Kjøp utgivelse");
				
				// kjøpe?
				if (login::$logged_in && isset($_POST['buy']))
				{
					$sid = postval("sid");
					$price = postval("price");
					if ($sid != login::$info['ses_id'])
					{
						ess::$b->page->add_message("Ugyldig.", "error");
					}
					
					// har prisen endret seg?
					elseif ($price != $ffn->data['ffn_cost'])
					{
						ess::$b->page->add_message("Prisen har endret seg. Du må utføre handlingen på nytt.", "error");
					}
					
					// har ikke nok penger
					elseif (login::$user->player->data['up_cash'] < $ffn->data['ffn_cost'])
					{
						ess::$b->page->add_message("Du har ikke nok penger på hånda.", "error");
					}
					
					// kjøp utgivelse
					else
					{
						// trekk fra pengene fra brukeren
						if ($ffn->data['ffn_cost'] != 0)
						{
							ess::$b->db->begin();
							ess::$b->db->query("UPDATE users_players SET up_cash = up_cash - {$ffn->data['ffn_cost']} WHERE up_id = ".login::$user->player->id." AND up_cash >= {$ffn->data['ffn_cost']}");
						}
						
						// mislykket
						if ($ffn->data['ffn_cost'] != 0 && ess::$b->db->affected_rows() == 0)
						{
							ess::$b->db->rollback();
							ess::$b->page->add_message("Du har ikke nok penger på hånda.", "error");
						}
						
						else
						{
							// legg til oppføring
							ess::$b->db->query("INSERT INTO ff_newspapers_payments SET ffnp_ffn_id = $ffn->id, ffnp_up_id = ".login::$user->player->id.", ffnp_cost = {$ffn->data['ffn_cost']}, ffnp_time = ".time());
							
							// gi pengene til firmaet
							ess::$b->db->query("UPDATE ff SET ff_bank = ff_bank + {$ffn->data['ffn_cost']} WHERE ff_id = {$this->ff->id}");
							
							// stats for firmaet
							$ffn->ff->stats_update("money_in", $ffn->data['ffn_cost']);
							
							// oppdater utgivelsen
							ess::$b->db->query("UPDATE ff_newspapers SET ffn_sold = ffn_sold + 1, ffn_income = ffn_income + {$ffn->data['ffn_cost']} WHERE ffn_id = $ffn->id");
							ess::$b->db->commit();
							
							ess::$b->page->add_message("Du har kjøpt utgivelsen for ".game::format_cash($ffn->data['ffn_cost']).".");
							redirect::handle();
						}
					}
				}
				
				
				echo '
<p class="c">'.htmlspecialchars($ffn->data['ffn_title']).' | Kjøp utgivelse | <a href="avis?ff_id='.$this->ff->id.'">Tilbake</a></p>
<div class="section center w200">
	<h2>Utgivelseinformasjon</h2>
	<dl class="dd_right">
		<dt>Publisert</dt>
		<dd>'.ess::$b->date->get($ffn->data['ffn_published_time'])->format().'</dd>
		<dt>Solgt utgivelser</dt>
		<dd>'.game::format_number($ffn->data['ffn_sold']).'</dd>
	</dl>
	<p>Artikler:</p>';
				
				// hent artiklene
				$result = ess::$b->db->query("SELECT ffna_title FROM ff_newspapers_articles WHERE ffna_ffn_id = $ffn->id ORDER BY ffna_title");
				
				// ingen artikler?
				if (mysql_num_rows($result) == 0)
				{
					echo '
	<p>Ingen artikler.</p>';
				}
				
				// list opp artiklene
				else
				{
					echo '
	<ul>';
					
					while ($row = mysql_fetch_assoc($result))
					{
						echo '
		<li>'.htmlspecialchars($row['ffna_title']).'</li>';
					}
					
					echo '
	</ul>';
				}
				
				echo '
</div>';
				
				if (login::$logged_in)
				{
					echo '
<form action="" method="post">
	<input type="hidden" name="sid" value="'.login::$info['ses_id'].'" />
	<input type="hidden" name="price" value="'.$ffn->data['ffn_cost'].'" />
	<div class="section center w200">
		<h2>Kjøp utgivelse</h2>
		<p>Før du får tilgang til denne utgivelsen må du kjøpe den.</p>
		<dl class="dd_right">
			<dt>Pris</dt>
			<dd>'.game::format_cash($ffn->data['ffn_cost']).'</dd>
		</dl>
		<p class="c">'.show_sbutton("Kjøp utgivelse", 'name="buy"').'</p>
	</div>
</form>';
				}
				else
				{
					echo '
<p class="c">Du må <a href="&rpath;/">logge inn</a> for å kjøpe og lese denne utgivelsen.</p>';
				}
				
				$this->ff->load_page();
			}
		}
		
		// hent data
		$data = $ffn->build_avis_html();
		
		echo '
<p class="c">'.htmlspecialchars($ffn->data['ffn_title']).($this->ff->access(3) ? ' | <a href="avis?ff_id='.$this->ff->id.'&amp;u&amp;ffn='.$ffn->data['ffn_id'].'">Vis detaljer</a>' : '').' | <a href="avis?ff_id='.$this->ff->id.'">Tilbake</a></p>'.$data;
	}
	
	/**
	 * Vise publiserte utgivelser
	 */
	protected function published()
	{
		ess::$b->page->add_title("Utgivelser");
		
		// hent publiserte utvivelser
		$pagei = new pagei(pagei::ACTIVE_GET, "side", pagei::PER_PAGE, 10);
		$ffnp_q = login::$logged_in ? "ffnp_ffn_id = ffn_id AND ffnp_up_id = ".login::$user->player->id : "FALSE";
		$result = $pagei->query("SELECT ffn_id, ffn_published_time, ffn_cost, ffn_title, ffn_sold, ffn_description, ffnp_time FROM ff_newspapers LEFT JOIN ff_newspapers_payments ON $ffnp_q WHERE ffn_ff_id = {$this->ff->id} AND ffn_published != 0 ORDER BY ffn_published_time DESC");
		
		echo '
<p class="c">Utgivelser</p>';
		
		// ingen publiserte utgivelser?
		if (mysql_num_rows($result) == 0)
		{
			echo '
<p class="c">Ingen utgivelser er publisert.</p>';
		}
		
		else
		{
			echo '
<p class="c">'.$pagei->total.' utgivelse'.($pagei->total == 1 ? '' : 'r').' er publisert:</p>';
			
			#$i = $pagei->total - ($pagei->per_page*($pagei->active-1));
			while ($row = mysql_fetch_assoc($result))
			{
				echo '
<div class="section center w200">
	<h2><a href="avis?ff_id='.$this->ff->id.'&amp;ffn='.$row['ffn_id'].'">'.htmlspecialchars($row['ffn_title']).'</a></h2>
	<dl class="dd_right">
		<dt>Publisert</dt>
		<dd>'.ess::$b->date->get($row['ffn_published_time'])->format().'</dd>
		<dt>Solgte utgivelser</dt>
		<dd>'.game::format_number($row['ffn_sold']).'</dd>
		<dt>Pris</dt>
		<dd>'.game::format_cash($row['ffn_cost']).'</dd>
		<dt>Kjøpt?</dt>
		<dd>'.($row['ffnp_time'] ? '<a href="avis?ff_id='.$this->ff->id.'&amp;ffn='.$row['ffn_id'].'">Ja</a> ('.ess::$b->date->get($row['ffnp_time'])->format().')' : 'Nei [<a href="avis?ff_id='.$this->ff->id.'&amp;ffn='.$row['ffn_id'].'">Kjøp</a>]').(access::is_nostat() ? ' (nostat)' : '').'</dd>
	</dl>
	<div class="p">'.$this->ff->format_description($row['ffn_description']).'</div>
</div>';
				#$i--;
			}
			
			// flere sider?
			if ($pagei->pages > 1)
			{
				echo '
<p class="c">Navigasjon: '.$pagei->pagenumbers().'</p>';
			}
		}
	}
}
