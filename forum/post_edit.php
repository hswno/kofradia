<?php

require "../base.php";
global $_base, $__server;

access::no_guest();

// hent inn forum modulen
essentials::load_module("forum");

// hva skal redigeres?
$edit_type = getval("type");

// rediger emne
if ($edit_type == "emne")
{
	// finn emnet
	$topic = new forum_topic(getval("id"));
	
	// lagre endringer?
	if (isset($_POST['save']))
	{
		$title = postval("title");
		$text = postval("text");
		$section = postval("section");
		
		// type forumtråd og låst/ulåst
		$type = NULL;
		$locked = NULL;
		if ($topic->forum->fmod)
		{
			$type = isset($_POST['type']) ? postval("type") : NULL;
			$locked = isset($_POST['locked']);
		}
		
		// forsøk å utfør endringene
		$topic->edit($title, $text, $section, $type, $locked);
	}
	
	// legg til tittel
	$_base->page->add_title("Forum", "Rediger forumtråd", $topic->info['ft_title']);
	
	echo '
<div class="bg1_c forumw">
	<h1 class="bg1">Rediger forumtråd<span class="left"></span><span class="right"></span></h1>
	<p class="h_left"><a href="topic?id='.$topic->id.'">&laquo; Tilbake til forumtråden</a></p>
	<div class="bg1">
		<boxes />
		<div id="topic_info_edit"></div>
		<div class="forum_reply_edit_c">
		<form action="" method="post">
			<dl class="dl_2x">
				<dt>Tittel</dt>
				<dd>
					<input type="text" name="title" id="topic_title" class="styled w200" value="'.htmlspecialchars(postval("title", $topic->info['ft_title'])).'" maxlength="'.forum::TOPIC_TITLE_MAX_LENGTH.'" />';
	
	if (!$topic->forum->ff || access::has("mod"))
	{
		echo '
					<select name="section" id="topic_section">';
		
		// hent alle forumkategoriene vi har tilgang til
		$sections = forum::get_forum_list();
		$section = isset($_POST['section']) && isset($sections[$_POST['section']]) ? ((int) $_POST['section']) : $topic->info['ft_fse_id'];
		foreach ($sections as $row)
		{
			$name = $row['name'];
			echo '
						<option value="'.$row['fse_id'].'"'.($section == $row['fse_id'] ? ' selected="selected"' : '').'>'.htmlspecialchars($name).'</option>';
		}
		
		echo '
					</select>';
	}
	
	if ($topic->forum->fmod)
	{
		// type
		$type = isset($_POST['type']) ? $_POST['type'] : 0;
		if ($type < 1 || $type > 3) $type = $topic->info['ft_type'];
		
		// låst/ulåst
		$locked = isset($_POST['type']) ? isset($_POST['locked']) : ($topic->info['ft_locked'] != 0);
		
		echo '
					<select name="type" id="topic_type">
						<option value="1"'.($type == 1 ? ' selected="selected"' : '').'>Normal forumtråd</option>
						<option value="2"'.($type == 2 ? ' selected="selected"' : '').'>Sticky forumtråd</option>
						<option value="3"'.($type == 3 ? ' selected="selected"' : '').'>Viktig forumtråd</option>
					</select>
				</dd>
				<dt>Låst</dt>
				<dd>
					<input type="checkbox" name="locked" id="topic_locked"'.($locked ? ' checked="checked"' : '').' /><label for="topic_locked"> Lås forumtråden for endringer</label>';
	}
	
	echo '
				</dd>
				<dt>Innhold</dt>
				<dd><textarea name="text" id="topic_text" rows="20" cols="75">'.htmlspecialchars(postval("text", $topic->info['ft_text'])).'</textarea></dd>
			</dl>
			<p class="c">
				'.show_sbutton("Lagre endringer", 'name="save" accesskey="s" id="topic_save"').'
				'.show_sbutton("Forhåndsvis", 'name="preview" accesskey="p" id="topic_preview"').'
			</p>
		</form>
		</div>
		<div id="topic_info_preview" class="forum">';
	
	// forhåndsvise?
	if (isset($_POST['preview']))
	{
		// sett opp data
		$data = $topic->extended_info();
		$data['ft_text'] = postval("text");
		$data['ft_last_edit'] = time();
		$data['ft_last_edit_up_id'] = login::$user->player->id;
		
		// vis forhåndsvisning
		echo forum::template_topic_preview($data);
	}
	
	echo '
		</div>
	</div>
</div>';
	
	// div javascript
	$_base->page->add_js_file($__server['relative_path']."/js/forum.js");
	$_base->page->add_js_domready('
	new EditForumTopic('.$topic->id.', '.((int)$topic->info['ft_last_edit']).');');
	
	$_base->page->load();
}

// rediger forumsvar
elseif ($edit_type == "svar")
{
	// hent forumsvaret
	$reply = new forum_reply(getval("id"));
	
	// fant ikke forumsvaret?
	if (!$reply->info)
	{
		$reply->error_404();
	}
	
	// kontroller tilgang osv
	$reply->require_access();
	$reply->get_topic();
	
	// lagre endringer?
	if (isset($_POST['save']))
	{
		$text = postval("text");
		
		// forsøk å utfør endringene
		$reply->edit($text);
	}
	
	// legg til tittel
	$_base->page->add_title("Forum", "Rediger forumsvar", $reply->topic->info['ft_title']);
	
	echo '
<div class="bg1_c forumw">
	<h1 class="bg1">Rediger forumsvar i '.htmlspecialchars($reply->topic->info['ft_title']).'<span class="left"></span><span class="right"></span></h1>
	<p class="h_left"><a href="topic?id='.$reply->topic->id.'&amp;replyid='.$reply->id.'">&laquo; Tilbake til forumsvaret</a></p>
	<div class="bg1">
		<div class="forum_reply_edit_c">
		<form action="" method="post">
			<dl class="dl_2x">
				<dt>Innhold</dt>
				<dd><textarea name="text" rows="20" cols="75">'.htmlspecialchars(postval("text", $reply->info['fr_text'])).'</textarea></dd>
			</dl>
			<p class="c">
				'.show_sbutton("Lagre endringer", 'name="save" accesskey="s"').'
				'.show_sbutton("Forhåndsvis", 'name="preview" accesskey="p"').'
			</p>
		</form>
		</div>';
	
	// forhåndsvise?
	if (isset($_POST['preview']))
	{
		// sett opp data
		$data = $reply->extended_info();
		$data['ft_text'] = postval("text");
		$data['ft_last_edit'] = time();
		$data['fr_last_edit_up_id'] = login::$user->player->id;
		
		// vis forhåndsvisning
		echo '
		<div class="forum">'.forum::template_topic_reply_preview($data).'
		</div>';
	}
	
	echo '
	</div>
</div>';
	
	$_base->page->load();
}

// ukjent
else
{
	$_base->page->add_message("Ukjent handling.");
	redirect::handle("forum");
}