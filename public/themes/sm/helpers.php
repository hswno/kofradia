<?php

class theme_helper
{
	static function get_extended_access_boxes()
	{
		$obj = new \Kofradia\Twig\TemplateHelper();
		return $obj->getExtendedAccessBoxes();
	}
}