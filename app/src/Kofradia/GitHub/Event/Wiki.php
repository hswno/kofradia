<?php namespace Kofradia\GitHub\Event;

class Wiki {
	use TraitActionText;

	/**
	 * Process data from GitHub API
	 *
	 * @param array Data from GitHub API for this commit
	 * @param WikiEvent The action which triggered this
	 * @return \Kofradia\GitHub\Event\Wiki
	 */
	public static function process($data, WikiEvent $wiki)
	{
		$page = new static();
		$page->wiki = $wiki;
		$page->name = $data['page_name'];
		$page->title = $data['title'];
		$page->action = $data['action'];
		$page->url = $data['html_url'];
		return $page;
	}

	/**
	 * Description of actions
	 *
	 * @var array(string action => string text, ..)
	 */
	public static $action_text = array(
		"edited" => "oppdaterte",
		"created" => "opprettet");

	/**
	 * The wiki-action it belongs
	 *
	 * @var \Kofradia\GitHub\Event\WikiEvent
	 */
	public $wiki;

	/**
	 * Page name
	 *
	 * @var string
	 */
	public $name;

	/**
	 * Page title
	 *
	 * @var string
	 */
	public $title;

	/**
	 * URL to commit
	 *
	 * @var string
	 */
	public $url;

	/**
	 * Return message describing page action in HTML-format
	 *
	 * @return string
	 */
	public function getDescriptionHTML()
	{
		return sprintf('<u>%s</u> %s <a href="%s">%s</a>',
			htmlspecialchars($this->wiki->sender_name),
			htmlspecialchars($this->getActionText()),
			htmlspecialchars($this->url),
			htmlspecialchars($this->title));
	}

	/**
	 * Return message describing page action in IRC-format
	 *
	 * @return string
	 */
	public function getDescriptionIRC()
	{
		return sprintf("%%u%s%%u %s %%u%s%%u %s",
			$this->wiki->sender_name,
			$this->getActionText(),
			$this->title,
			$this->url);
	}
}