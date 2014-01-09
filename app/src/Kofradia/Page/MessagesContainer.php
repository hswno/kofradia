<?php namespace Kofradia\Page;

class MessagesContainer implements \ArrayAccess, \IteratorAggregate, \Countable {
	public $messages = array();

	public function getIterator()
	{
		return new \ArrayIterator($this->messages);
	}

	public function count()
	{
		return count($this->messages);
	}

	public function offsetSet($offset, $value)
	{
		$this->messages[$offset] = $value;
	}

	public function offsetExists($offset)
	{
		return isset($this->messages[$offset]);
	}

	public function offsetUnset($offset)
	{
		unset($this->messages[$offset]);
	}

	public function offsetGet($offset)
	{
		return isset($this->messages[$offset]) ? $this->messages[$offset] : null;
	}

	/**
	 * Get a message by its name
	 *
	 * @param string name
	 * @param bool remove it after fetching
	 * @param bool true to format, else return object
	 * @return \Kofradia\Page\Message|string
	 */
	public function getMessageByName($name, $erase = true, $format = null)
	{
		foreach ($this->messages as $key => $message)
		{
			if ($message->name == $name)
			{
				if ($erase)
				{
					unset($this->messages[$key]);
				}

				if ($format)
				{
					return $message->getHTMLBox();
				}

				return $message;
			}
		}
	}

	/**
	 * Add message to container
	 * 
	 * @param \Kofradia\Page\Message
	 * @return \Kofradia\Page\Message
	 */
	public function addMessage(\Kofradia\Page\Message $msg)
	{
		// check if we should remove an old entry first
		if ($msg->name)
		{
			foreach ($this->messages as $key => $message)
			{
				if ($message->name == $msg->name)
				{
					unset($this->messages[$key]);
					break;
				}
			}
		}

		$this->messages[] = $msg;
		return $msg;
	}

	/**
	 * Get formatted boxes for messages and remove the messages
	 *
	 * @param string|array class to match, may be array of multiple
	 * @return string
	 */
	public function getBoxes($class = null)
	{
		if ($class !== null && !is_array($class)) $class = (array) $class;

		$ret = '';
		foreach ($this->messages as $key => $message)
		{
			if (is_null($class) || in_array($message->class, $class))
			{
				$ret .= $message->getHTMLBox();
				unset($this->messages[$key]);
			}
		}

		return $ret;
	}
}