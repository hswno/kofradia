<?php namespace Kofradia\Forum;

/**
 * Forumtråd (ajax)
 */
class TopicAjax extends Topic
{
	/** Slettet, men tilgang */
	protected function deleted_with_access(){}
	
	/** Slettet og uten tilgang, eller finnes ikke */
	protected function error_404()
	{
		\ajax::text("ERROR:404-TOPIC", \ajax::TYPE_INVALID);
	}
	
	/** Hent informasjon om forumkategorien og kontroller tilgang */
	protected function load_forum()
	{
		$this->forum = new \Kofradia\Forum\CategoryAjax($this->info['ft_fse_id']);
		$this->forum->require_access();
	}
	
	/** Redigering feilet */
	protected function edit_error_failed()
	{
		\ajax::text("ERROR:EDIT-FAILED", \ajax::TYPE_INVALID);
	}
	
	/** Har ikke tilgang til å redigere forumtråden */
	protected function edit_error_403()
	{
		\ajax::text("ERROR:403-TOPIC", \ajax::TYPE_INVALID);
	}
	
	/** Forumtråden er låst */
	protected function edit_error_locked()
	{
		\ajax::text("Forumtråden er låst. Du kan ikke redigere den.", \ajax::TYPE_INVALID);
	}
	
	/** For kort eller lang lengde i tittelen til forumtråden */
	protected function edit_error_length_title()
	{
		\ajax::text("Tittelen kan ikke inneholde færre enn ".\Kofradia\Forum\Category::TOPIC_TITLE_MIN_LENGTH." eller flere enn ".\Kofradia\Forum\Category::TOPIC_TITLE_MAX_LENGTH." tegn.", \ajax::TYPE_INVALID);
	}
	
	/** For kort lengde i innholdet til forumtråden */
	protected function edit_error_length()
	{
		\ajax::text("Forumtråden kan ikke inneholde færre enn ".\Kofradia\Forum\Category::TOPIC_MIN_LENGTH." bokstaver/tall.", \ajax::TYPE_INVALID);
	}
	
	/** Ugyldig forumkategori */
	protected function edit_error_section()
	{
		\ajax::text("ERROR:404-NEW-FORUM", \ajax::TYPE_INVALID);
	}
	
	/** Ugyldig type */
	protected function edit_error_type()
	{
		\ajax::text("ERROR:INVALID-TYPE", \ajax::TYPE_INVALID);
	}
	
	/** Ingen endringer ble utført */
	protected function edit_error_nochange()
	{
		\ajax::text("Ingen endringer ble utført.", \ajax::TYPE_INVALID);
	}
	
	/** Forumtråden ble redigert */
	protected function edit_complete()
	{
		\ess::$b->page->add_message("Endringene i forumtråden ble lagret.");
		
		\ajax::text("REDIRECT:".\ess::$s['relative_path']."/forum/topic?id={$this->id}");
	}
	
	/** Forumet er låst */
	protected function add_reply_error_locked()
	{
		\ajax::text("Forumtråden er låst. Du kan ikke legge til nye forumsvar.", \ajax::TYPE_INVALID);
	}
	
	/** Forumet er låst */
	protected function add_reply_error_deleted()
	{
		\ajax::text("Forumtråden er slettet. Du kan ikke legge til nye forumsvar.", \ajax::TYPE_INVALID);
	}
	
	/**
	 * Må vente før nytt forumsvar kan legges til
	 * @param integer $wait ventetid
	 */
	protected function add_reply_error_wait($wait)
	{
		\ajax::html("Du må vente ".\game::counter($wait)." før du kan opprette forumsvaret.", \ajax::TYPE_INVALID);
	}
	
	/** For kort lengde i forumsvaret */
	protected function add_reply_error_length()
	{
		\ajax::text("Forumsvaret kan ikke inneholde færre enn ".\Kofradia\Forum\Category::REPLY_MIN_LENGTH." bokstaver/tall.", \ajax::TYPE_INVALID);
	}
	
	/**
	 * Forumsvaret ble lagt til (merged)
	 * @param integer $reply_id
	 */
	protected function add_reply_merged($reply_id)
	{
		\ess::$b->page->add_message("Siden det siste forumsvaret tilhørte deg, har teksten blitt redigert inn i det forumsvaret.");
		
		\ajax::text("REDIRECT:".\ess::$s['relative_path']."/forum/topic?id={$this->id}&replyid=$reply_id");
	}
	
	/**
	 * Forumsvaret ble lagt til (som nytt forumsvar)
	 */
	protected function add_reply_complete($reply_id)
	{
		\ajax::text("REDIRECT:".\ess::$s['relative_path']."/forum/topic?id={$this->id}&replyid=$reply_id");
	}
	
	/**
	 * Hent ut et bestemt forumsvar i forumtråden
	 * @param integer $reply_id
	 * @return forum_reply_ajax
	 */
	public function get_reply($reply_id)
	{
		// forsøk å hent forumsvaret
		$reply = new \Kofradia\Forum\ReplyAjax($reply_id, $this);
		
		// fant ikke?
		if (!$reply->info)
		{
			return false;
		}
		
		return $reply;
	}
}