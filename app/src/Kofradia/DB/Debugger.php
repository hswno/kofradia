<?php namespace Kofradia\DB;

use \Kofradia\View;

class Debugger {
	/**
	 * Shows the result of a query in a simple table
	 *
	 * @param \PDOStatement
	 */
	public static function debugResult($result)
	{
		$query = $result->queryString;
		$fields = array();
		$table = array();

		if ($result->rowCount() == 0)
		{
			$fields = array("empty");
		}

		else
		{
			$row = $result->fetch();
			foreach (array_keys($row) as $field)
			{
				$fields[] = $field;
			}

			do
			{
				$table[] = $row;
			} while ($row = $result->fetch());
		}

		echo View::forge("app/db/debugger", array(
			"query"  => $query,
			"fields" => $fields,
			"table"  => $table));
	}
}