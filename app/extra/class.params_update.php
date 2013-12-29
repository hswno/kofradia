<?php

/**
 * Benytter params objektet
 * Knytter dataen mot et felt i en rad i databasen
 */
class params_update extends params
{
	/** Tabellen */
	private $link_table = NULL;
	
	/** Kolonnenavnet */
	private $link_column = NULL;
	
	/** Radbegrensning */
	private $link_where = NULL;
	
	/** State (locked) */
	private $locked = false;

	/** Endret siden forrige lagring? */
	private $changed_state = false;
	
	/** Constructor - Link mot en bestemt celle i en tabell */
	public function __construct($text, $table, $column, $where)
	{
		// definer variabler
		$this->link_table = $table;
		$this->link_column = $column;
		$this->link_where = $where;
		
		// hente innhold og låse cellen?
		if ($text === -1)
		{
			$this->lock();
		}
		
		// eller legge til tekst fra før
		else
		{
			$this->add_text($text);
		}
	}
	
	/** Lås raden cellen befinner seg i og hent friske verdier */
	public function lock()
	{
		// allerede låst?
		if ($this->locked) return;
		$this->locked = true;

		// er ikke låst i databasemodulen?
		// lås raden og hent friske verdier
		\Kofradia\DB::get()->beginTransaction();
		
		$result = \Kofradia\DB::get()->query("SELECT $this->link_column FROM $this->link_table WHERE $this->link_where LIMIT 1 FOR UPDATE");
		
		// erstatt med friske verdier
		$this->params = array();
		$this->add_text($result->fetchColumn(0));
	}
	
	/** Fjern en enhet */
	public function remove($name, $save = false)
	{
		// kontroller lås
		if (!$this->locked)
		{
			$this->lock();
		}
		
		// finnes den?
		$ret = false;
		if (isset($this->params[$name]))
		{
			unset($this->params[$name]);
			$this->changed_state = true;
			$ret = true;
		}
		
		// lagre?
		if ($save)
		{
			$this->commit();
		}

		return $ret;
	}
	
	/** Oppdater/legg til en enhet */
	public function update($name, $value, $save = false)
	{
		// kontroller lås
		if (!$this->locked)
		{
			$this->lock();
		}
		
		// oppdater params
		$this->params[$name] = $value;
		$this->changed_state = true;
		
		// lagre?
		if ($save)
		{
			$this->commit();
		}
	}
	
	/** Lagre params til cellen */
	public function commit($free = true)
	{
		// ikke låst?
		if (!$this->locked)
		{
			throw new HSException("params_update->commit: Cannot commit unlocked row.");
		}

		// ingen endringer?
		if (!$this->changed_state) {
			if ($free) {
				\Kofradia\DB::get()->commit();
				$this->locked = false;
			}
			return;
		}
		
		// oppdater databasen
		\Kofradia\DB::get()->exec("UPDATE $this->link_table SET $this->link_column = ".\Kofradia\DB::quote($this->build())." WHERE $this->link_where LIMIT 1");
		$this->changed_state = false;
		
		// frigjøre?
		if ($free)
		{
			\Kofradia\DB::get()->commit();
			$this->locked = false;
		}
	}
}