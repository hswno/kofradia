<?php

class patch
{
	/**
	 * Kjør patch fra diff() på en tekst.
	 * 
	 * @param string $original
	 * @param string $diff
	 * @return string
	 */
	public static function make($original, $diff)
	{
		$original = explode("\n", $original);
		$diff = explode("\n", $diff);
		$offset = 0;
		$skip = 0;
		
		foreach ($diff as $key => $line)
		{
			// hvilken handling?
			$matches = false;
			if ($skip-- > 0 || !preg_match("/^([0-9]+)(?:,([0-9]+))?([acd])([0-9]+)(?:,([0-9]+))?$/D", $line, $matches))
			{
				// ukjent
				continue;
			}
			
			// range
			$x0 = $matches[1];
			$x1 = $matches[2] == "" ? $x0 : $matches[2];
			$y0 = $matches[4];
			$y1 = isset($matches[5]) ? $matches[5] : $y0;
			
			$x0 += $offset;
			$x1 += $offset;
			
			// handling
			switch ($matches[3])
			{
				case "a":
					// append
					$range = $x1 - $x0 + 1;
					$offset += $range;
					$skip = $range;
					
					// legg til elementene
					$items = array_slice($diff, $key+1, $range);
					foreach ($items as &$item) $item = substr($item, 2);
					array_splice($original, $x0, 0, $items);
					
					break;
					
				case "d":
					// deleted
					$range = $x1 - $x0 + 1;
					$offset -= $range;
					$skip = $range;
					
					// fjern elementene
					array_splice($original, $y0, $range);
					
					break;
					
				case "c":
					// changed/replaced
					$range_d = $x1 - $x0 + 1;
					$range_a = $y1 - $y0 + 1;
					$offset += $range_a - $range_d;
					$skip = $range_a + $range_d + 1;
					
					// fjern elementene
					array_splice($original, $x0-1, $range_d);
					
					// legg til elementene
					$items = array_slice($diff, $key+$range_d+2, $range_a);
					foreach ($items as &$item) $item = substr($item, 2);
					array_splice($original, $x0-1, 0, $items);
					
					break;
			}
		}
		
		return implode("\n", $original);
	}
}