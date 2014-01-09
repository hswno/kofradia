<?php namespace Kofradia;

class Response {
	public $data;

	public function output()
	{
		header("Content-Type: text/html; charset=utf-8");
		echo \ess::$b->page->postParse($this->data);

		echo "\r\n<!--\r\n".\ess::$b->profiler->getPrettyTable()."\r\n-->\r\n";
	}

	public function setContents($data)
	{
		$this->data = $data;
	}

	public function getContents()
	{
		return $this->data;
	}
}