<?php

require "config.php";

ess::$b->page->add_title("Oversikt over brukere med høy advarselpoeng");

/*
 * Poengberegning:
 * poeng regnes linjært med utgangspunkt i maksimalt antall poeng og varigheten
 *   p = aktuelle poeng
 *   m = maksimale antall poeng
 *   s = maksimal tid
 *   t = tid siden hendelsen ble lagt til
 * p = m(1 - t/s) 
 * 
 */


$time = time();

// opprett temporary tabell
\Kofradia\DB::get()->exec("
	CREATE TEMPORARY TABLE temp_results (
		temp_up_id INT(11) UNSIGNED NOT NULL DEFAULT 0,
		temp_points INT(11) UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY (temp_up_id)
	) ENGINE = MEMORY");



// analyser forumsvar
$lca_id = crewlog::$actions['forum_reply_delete'][0];
$m = 10;
$s = 2160 * 3600; // 90 dager
$expire = $time - $s;
\Kofradia\DB::get()->exec("
	INSERT INTO temp_results (temp_up_id, temp_points)
		SELECT lc_a_up_id, $m * (1 - ($time - lc_time) / $s) AS points
		FROM log_crew
		WHERE lc_lca_id = $lca_id AND lc_time > $expire
	ON DUPLICATE KEY UPDATE temp_points = temp_points + VALUES(temp_points)");



// analyser advarsler
$lca_id = crewlog::$actions['user_warning'][0];
$lcd_lce_id_priority = 3;
$lcd_lce_id_invalidated = 5;
$m_1 = 40;
$m_2 = 60;
$m_3 = 100;
$s_1 = 2160 * 3600; // 90 dager
$s_2 = 2880 * 3600; // 120 dager
$s_3 = 4320 * 3600; // 180 dager
$expire_1 = $time - $s_1;
$expire_2 = $time - $s_2;
$expire_3 = $time - $s_3;
\Kofradia\DB::get()->exec("
	INSERT INTO temp_results (temp_up_id, temp_points)
		SELECT lc_a_up_id, IF(d1.lcd_data_int = 1,
			$m_1 * (1 - ($time - lc_time) / $s_1),
			IF (d1.lcd_data_int = 3,
				$m_3 * (1 - ($time - lc_time) / $s_3),
				$m_2 * (1 - ($time - lc_time) / $s_2)
			)) AS points
		FROM log_crew
			JOIN log_crew_data d1 ON d1.lcd_lc_id = lc_id AND d1.lcd_lce_id = $lcd_lce_id_priority
			LEFT JOIN log_crew_data d2 ON d2.lcd_lc_id = lc_id AND d2.lcd_lce_id = $lcd_lce_id_invalidated
		WHERE lc_lca_id = $lca_id AND lc_time > $expire AND (d2.lcd_data_int IS NULL OR d2.lcd_data_int = 0)
	ON DUPLICATE KEY UPDATE temp_points = temp_points + VALUES(temp_points)");



// vis oversikt
echo '
<div class="bg1_c xsmall">
	<h1 class="bg1">Oversikt over brukere med høy advarselpoeng<span class="left"></span><span class="right"></span></h1>
	<div class="bg1">';

$pagei = new pagei(pagei::PER_PAGE, 30, pagei::ACTIVE_GET, "side");
$result = $pagei->query("
	SELECT SUM(temp_points) AS points, active.up_id
	FROM temp_results
		JOIN users_players ref ON temp_up_id = ref.up_id
		JOIN users ON u_id = ref.up_u_id
		JOIN users_players active ON u_active_up_id = active.up_id
	WHERE u_access_level != 0
	GROUP BY active.up_id
	ORDER BY points DESC");

if ($result->rowCount() == 0)
{
	echo '
		<p>Fant ingen spillere.</p>';
}

else
{
	echo '
		<table class="table tablemt center">
			<thead>
				<tr>
					<th>Aktiv spiller</th>
					<th>Antall poeng</th>
				</tr>
			</thead>
			<tbody>';
	
	while ($row = $result->fetch())
	{
		echo '
				<tr>
					<td><user id="'.$row['up_id'].'" /></td>
					<td class="r">'.game::format_number($row['points']).'</td>
				</tr>';
	}
	
	echo '
			</tbody>
		</table>
		<p class="c">'.$pagei->pagenumbers().'</p>
	</div>
</div>';
}

ess::$b->page->load();