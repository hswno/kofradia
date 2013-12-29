<?php

// data:
// $query
// $fields
// $table

?>
<!DOCTYPE html>
<html lang="no">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<meta name="author" content="Henrik Steen; HenriSt.net" />
<title>Query Debug</title>
<style type="text/css">
<!--
.q_debug td {
	white-space: nowrap;
}
-->
</style>
</head>
<body>
<h1>Query Debug</h1>
<p>
	Debug of MySQL query:<br />
	<pre><?php echo htmlspecialchars($query); ?></pre>
</p>
<table cellpadding="2" cellspacing="0" border="1" frame="hidden" rules="all" class="q_debug">
	<thead>
		<tr>
			<?php foreach ($fields as $field): ?>
			<th bgcolor="#EEEEEE"><?php echo htmlspecialchars($field) ?></th>
			<?php endforeach ?>
		</tr>
	</thead>
	<tbody>
		<?php if (count($table) == 0): ?>
		<tr>
			<td colspan="<?php echo count($fields) ?>">No row exists.</td>
		</tr>
		<?php else: ?>
			<?php foreach ($table as $row): ?>
		<tr>
			<?php foreach ($row as $col): ?>
			<td>
				<?php if ($col == NULL): ?>
				<i style="color: #CCC">NULL</i>
				<?php elseif ($col === ""): ?>
			 	<i style="color: #CCC">TOMT</i>
			 	<?php else: ?>
			 	<?php echo nl2br(htmlspecialchars($col)) ?>
			 	<?php endif ?>
			</td>
			<?php endforeach ?>
		</tr>
			<?php endforeach ?>
		<?php endif ?>
	</tbody>
</table>
<p>
	<a href="http://hsw.no/">hsw.no</a>
</p>
</body>
</html>