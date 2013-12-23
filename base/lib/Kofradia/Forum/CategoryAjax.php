<?php namespace Kofradia\Forum;

/**
 * For AJAX handlinger i forumet
 */
class CategoryAjax extends Category {

	/** Feil: 404 */
	protected function error_404()
	{
		\ajax::text("ERROR:404-FORUM", \ajax::TYPE_INVALID);
	}
	
	/** Feil: 403 (ikke tilgang) */
	protected function error_403()
	{
		\ajax::text("ERROR:403-FORUM", \ajax::TYPE_INVALID);
	}
	
	/** Blokkert fra å utføre forumhandlinger */
	protected function blocked($blokkering)
	{
		\ajax::html("Du er blokkert fra å utføre handlinger i forumet.<br />Blokkeringen varer til ".\ess::$b->date->get($blokkering['ub_time_expire'])->format(\date::FORMAT_SEC)." (".\game::counter($blokkering['ub_time_expire']-time()).").<br />\n"
			."<b>Begrunnelse:</b> ".\game::format_data($blokkering['ub_reason'], "bb-opt", "Ingen begrunnelse gitt."), \ajax::TYPE_INVALID);
	}
	
	/** Har ikke høy nok rank for å skrive i forumet */
	protected function add_topic_error_rank()
	{
		// sett opp ranknavnet
		$rank_info = \game::$ranks['items_number'][self::TOPIC_MIN_RANK][$rank_id];
		
		\ajax::html("Du har ikke høy nok rank for å skrive i dette forumet. Du må ha nådd ranken <b>".htmlspecialchars($rank_info['name'])."</b>.", \ajax::TYPE_INVALID);
	}
	
	/**
	 * Må vente før ny forumtråd kan legges til
	 * @param integer $wait ventetid
	 */
	protected function add_topic_error_wait($wait)
	{
		\ajax::html("Du må vente ".\game::counter($wait)." før du kan opprette ny forumtråd.", \ajax::TYPE_INVALID);
	}
	
	/** For kort eller lang lengde i tittelen til forumtråden */
	protected function add_topic_error_length_title()
	{
		\ajax::html("Tittelen kan ikke inneholde færre enn ".\Kofradia\Forum\Category::TOPIC_TITLE_MIN_LENGTH." eller flere enn ".\Kofradia\Forum\Category::TOPIC_TITLE_MAX_LENGTH." tegn.", \ajax::TYPE_INVALID);
	}
	
	/** For kort lengde i innholdet til forumtråden */
	protected function add_topic_error_length()
	{
		\ajax::html("Forumtråden kan ikke inneholde færre enn ".\Kofradia\Forum\Category::TOPIC_MIN_LENGTH." bokstaver/tall.", \ajax::TYPE_INVALID);
	}
	
	/** Ugyldig type */
	protected function add_topic_error_type()
	{
		\ajax::html("Ugyldig type.", \ajax::TYPE_INVALID);
	}
	
	/** Forumtråden ble redigert */
	protected function add_topic_complete($topic_id)
	{
		\ess::$b->page->add_message("Forumtråden ble opprettet.");
		
		\ajax::text("REDIRECT:".\ess::$s['relative_path']."/forum/topic?id=$topic_id");
	}
}