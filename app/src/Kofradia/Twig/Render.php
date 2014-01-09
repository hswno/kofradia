<?php namespace Kofradia\Twig;

use \Kofradia\Controller;

class Render extends \Twig_Extension {
	public function getFunctions()
	{
		return array(
			new \Twig_SimpleFunction('render', array($this, 'renderFragment'), array('is_safe' => array('html'))),
		);
	}

	public function renderFragment($uri, $options = array())
	{
		return Controller::execute($uri, $options)->getContents();
	}

	public function getName()
	{
		return 'kofradia_render';
	}
}