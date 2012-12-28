<?php

class diff
{
	/**
	 * Returnere diff(1) format mellom to tekster. Kan så brukes i patch() funksjonen.
	 * 
	 * @param string $text_old
	 * @param string $text_new
	 * @return string
	 */
	function make($text_old, $text_new)
	{
		// del opp til linjer
		$lines_old = explode("\n", $text_old);
		$lines_new = explode("\n", $text_new);
		
		/*$x = array_pop($lines_old);
		if ($x != "")
		{
			$lines_old[] = $x;
			//$lines_old[] = "$x\n\\ No newline at end of file";
		}
		
		$x = array_pop($lines_new);
		if ($x != "")
		{
			$lines_old[] = $x;
			//$lines_new[] = "$x\n\\ No newline at end of file";
		}*/
	
		// reverse inndeks
		$reverse_old = array();
		$reverse_new = array();
		foreach ($lines_old as $key => $value) if ($value != "") $reverse_old[$value][] = $key;
		foreach ($lines_new as $key => $value) if ($value != "") $reverse_new[$value][] = $key;
		
		$current_old = 0;
		$current_new = 0;
		$actions = array();
		
		// gå gjennom dataen
		while ($current_old < count($lines_old) && $current_new < count($lines_new))
		{
			// lik linje?
			if ($lines_old[$current_old] == $lines_new[$current_new])
			{
				$actions[] = 4;
				$current_old++;
				$current_new++;
				continue;
			}
			
			// finn korteste bevegelse fra nåværende plassering
			$best_old = count($lines_old);
			$best_new = count($lines_new);
			$s_old = $current_old;
			$s_new = $current_new;
			
			while ($s_old+$s_new < $best_old+$best_new)
			{
				$d = -1;
				if (isset($lines_new[$s_new]) && isset($reverse_old[$lines_new[$s_new]]))
				{
					foreach ($reverse_old[$lines_new[$s_new]] as $n)
					{
						if ($n >= $s_old)
						{
							$d = $n;
							break;
						}
					}
				}
				if ($d >= $s_old && $d+$s_new < $best_old+$best_new)
				{
					$best_old = $d;
					$best_new = $s_new;
				}
				
				$d = -1;
				if (isset($lines_old[$s_old]) && isset($reverse_new[$lines_old[$s_old]]))
				{
					foreach ($reverse_new[$lines_old[$s_old]] as $n)
					{
						if ($n >= $s_new)
						{
							$d = $n;
							break;
						}
					}
				}
				if ($d >= $s_new && $s_old+$d < $best_old+$best_new)
				{
					$best_old = $s_old;
					$best_new = $d;
				}
				
				$s_old++;
				$s_new++;
			}
			
			while ($current_old < $best_old)
			{
				// slettede elementer
				$actions[] = 1;
				$current_old++;
			}
			while ($current_new < $best_new)
			{
				// nye elementer
				$actions[] = 2;
				$current_new++;
			}
		}
		
		// gjenstående linjer
		while ($current_old < count($lines_old))
		{
			// slettede elementer
			$actions[] = 1;
			$current_old++;
		}
		while ($current_new < count($lines_new))
		{
			// nye elementer
			$actions[] = 2;
			$current_new++;
		}
		
		// slutt
		$actions[] = 8;
		
		// formatter endringer
		$result = array();
		$op = 0;
		$x0 = 0;
		$x1 = 0;
		$y0 = 0;
		$y1 = 0;
		
		foreach ($actions as $action)
		{
			if ($action == 1)
			{
				// slettet
				$op |= $action;
				$x1++;
				continue;
			}
			
			if ($action == 2)
			{
				// nytt element
				$op |= $action;
				$y1++;
				continue;
			}
			
			if ($op > 0)
			{
				$xstr = ($x1 == ($x0+1)) ? $x1 : ($x0+1).",$x1";
				$ystr = ($y1 == ($y0+1)) ? $y1 : ($y0+1).",$y1";
				
				if ($op == 1)
				{
					// kun slettet linjer
					$result[] = "{$xstr}d{$y1}";
				}
				elseif ($op == 3)
				{
					// endret
					$result[] = "{$xstr}c{$ystr}";
				}
				while ($x0 < $x1)
				{
					// vis de slettede linjene
					$result[] = '< '.$lines_old[$x0];
					$x0++;
				}
				
				if ($op == 2)
				{
					// kun lagt til
					$result[] = "{$x1}a{$ystr}";
				}
				elseif ($op == 3)
				{
					// endret
					$result[] = '---';
				}
				while ($y0 < $y1)
				{
					// vis de nye linjene
					$result[] = '> '.$lines_new[$y0];
					$y0++;
				}
			}
			
			$x1++;
			$x0 = $x1;
			
			$y1++;
			$y0 = $y1;
			
			$op = 0;
		}
		
		#$result[] = '';
		
		return implode("\n", $result);
	}
}