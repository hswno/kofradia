<?php namespace Kofradia\Page;

class Message {
	/**
	 * Forge a new object
	 *
	 * @param string Content of the message
	 * @param string Type of the message (info, error, ..)
	 * @param string Class of the message (for grouping)
	 * @return \Kofradia\Page\Message
	 */
	public static function forge($content, $type = null, $class = null)
	{
		$m = new static();
		$m->content = $content;
		$m->type = $type;
		$m->class = $class;
		return $m;
	}

	/**
	 * The class for this message
	 *
	 * Used to group messages
	 *
	 * @var string
	 */
	public $class;

	/**
	 * The content of the message
	 *
	 * @var string
	 */
	public $content;

	/**
	 * The type of this message
	 *
	 * @var string
	 */
	public $type;

	/**
	 * Specific name
	 *
	 * @var string
	 */
	public $name;

	/**
	 * Get HTML box template
	 *
	 * @return string
	 */
	public function getHTMLBox()
	{
		// hva slags type melding?
		switch ($this->type)
		{
			// feilmelding
			case "error":
				return '<div class="error_box">'.$this->content.'</div>';
			break;
			
			// informasjon
			case "info":
				return '<div class="info_box">'.$this->content.'</div>';
			break;

			default:
				return $this->content;
		}
	}
}