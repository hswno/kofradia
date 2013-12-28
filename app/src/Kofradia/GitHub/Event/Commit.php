<?php namespace Kofradia\GitHub\Event;

class Commit {
	/**
	 * Process data from GitHub API
	 *
	 * @param array Data from GitHub API for this commit
	 * @param PushEvent The action which triggered this
	 * @return \Kofradia\GitHub\Event\Commit
	 */
	public static function process($data, PushEvent $push)
	{
		$commit = new static();
		$commit->push = $push;
		$commit->time = new \DateTime($data['timestamp']);
		$commit->author_name = $data['author']['name'];
		$commit->author_email = $data['author']['email'];
		$commit->url = $data['url'];
		$commit->message = $data['message'];
		return $commit;
	}

	/**
	 * The push-action it belongs
	 *
	 * @var \Kofradia\GitHub\Event\PushEvent
	 */
	public $push;

	/**
	 * Commit time
	 *
	 * @var \DateTime
	 */
	public $time;

	/**
	 * Author's name
	 *
	 * @var string
	 */
	public $author_name;

	/**
	 * Author's email
	 *
	 * @var string
	 */
	public $author_email;

	/**
	 * URL to commit
	 *
	 * @var string
	 */
	public $url;

	/**
	 * Commit message
	 *
	 * @var string
	 */
	public $message;

	/**
	 * Return message describing commit in HTML-format
	 *
	 * @return string
	 */
	public function getDescriptionHTML()
	{
		return sprintf('%s <a href="%s">pushet kode</a> til %s <u>%s</u>: <i>%s</i>',
			htmlspecialchars($this->author_name ?: $this->author_email),
			htmlspecialchars($this->url),
			htmlspecialchars($this->push->repository),
			htmlspecialchars($this->push->ref),
			htmlspecialchars($this->message));
	}

	/**
	 * Return message describing commit in IRC-format
	 *
	 * @return string
	 */
	public function getDescriptionIRC()
	{
		return sprintf("%%u%s%%u pushet kode til %%u%s%%u (%s) %s %s",
			$this->author_name ?: $this->author_email,
			$this->push->repository,
			$this->push->ref,
			$this->url,
			$this->message);
	}
}