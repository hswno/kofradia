<?php

// for å mekke kolonner etc i tabeller
class tbody
{
	public $cols = 0;
	public $current = -1;
	public $row = 0;

	// construct
	function tbody($cols)
	{
		$this->cols = $cols;
	}

	// vis en <td>
	function append($content, $attribs = '')
	{
		if (++$this->current == $this->cols)
		{
			echo '
		</tr>';
			$this->current = 0;
		}

		if ($this->current == 0)
		{
			echo '
		<tr'.(++$this->row % 2 == 0 ? ' class="color"' : '').'>';
		}

		echo '
			<td'.(!empty($attribs) ? ' '.$attribs : '').'>'.$content.'</td>';
	}

	// send ut siste <td> elementer
	function clean()
	{
		if (++$this->current < $this->cols)
		{
			for (;$this->current < $this->cols; $this->current++)
			{
				echo '
			<td>&nbsp;</td>';
			}
		}

		echo '
		</tr>';
	}
}