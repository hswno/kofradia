<?php namespace Kofradia\Forum;

/**
 * Forumsvar (ajax)
 */
class ReplyAjax extends Reply
{
	/** Hent forumtråd objekt */
	protected function get_topic_obj($topic_id)
	{
		return new \Kofradia\Forum\TopicAjax($topic_id);
	}
	
	/** Ikke tilgang til forumsvaret */
	protected function error_403()
	{
		\ajax::text("ERROR:403-REPLY", \ajax::TYPE_INVALID);
	}
	
	/** Forumtråden er låst */
	protected function delete_error_locked()
	{
		\ajax::text("Forumtråden er låst. Du kan ikke slette forumsvaret.", \ajax::TYPE_INVALID);
	}
	
	/** Forumsvaret er allerede slettet */
	protected function delete_dupe()
	{
		$this->delete_complete();
	}
	
	/** Forumsvaret ble slettet */
	protected function delete_complete()
	{
		// hent utvidet informasjon og returner HTML-malen
		\ajax::html(parse_html($this->topic->forum->template_topic_reply($this->extended_info())));
	}
	
	/** Forumsvaret er allerede gjenopprettet */
	protected function restore_dupe()
	{
		$this->restore_complete();
	}
	
	/** Forumsvaret ble gjenopprettet */
	protected function restore_complete()
	{
		// hent utvidet informasjon og returner HTML-malen
		\ajax::html(parse_html($this->topic->forum->template_topic_reply($this->extended_info())));
	}
	
	/** Redigering feilet */
	protected function edit_error_failed()
	{
		\ajax::text("ERROR:EDIT-FAILED", \ajax::TYPE_INVALID);
	}
	
	/** Forumtråden er låst */
	protected function edit_error_locked()
	{
		\ajax::text("Forumtråden er låst. Du kan ikke redigere forumsvaret.", \ajax::TYPE_INVALID);
	}
	
	/** For kort lengde i forumsvaret */
	protected function edit_error_length()
	{
		\ajax::text("Forumsvaret kan ikke inneholde færre enn ".\Kofradia\Forum\Category::REPLY_MIN_LENGTH." bokstaver/tall.", \ajax::TYPE_INVALID);
	}
	
	/** Ingen endringer ble utført */
	protected function edit_error_nochange()
	{
		\ajax::text("Ingen endringer ble utført.", \ajax::TYPE_INVALID);
	}
	
	/** Forumsvaret ble redigert */
	protected function edit_complete()
	{
		// hent utvidet informasjon og returner HTML-malen inni XML
		\ajax::xml('<data><reply id="'.$this->id.'" last_edit="'.$this->info['fr_last_edit'].'">'.htmlspecialchars(parse_html($this->topic->forum->template_topic_reply($this->extended_info()))).'</reply></data>');
	}
}