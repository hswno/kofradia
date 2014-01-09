<?php

class theme_helper
{
	function get_extended_access_boxes()
	{
		$obj = new \Kofradia\Twig\TemplateHelper();
		return $obj->getExtendedAccessBoxes();
	}
}