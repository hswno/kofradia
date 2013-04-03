<?php

require "config.php";
p_crew_deaktiverte::main();

class p_crew_deaktiverte
{
	/**
	 * Main
	 */
	public static function main()
	{
		// vis liste over valg
		echo '
<h1>Deaktiverte brukere/spillere</h1>
<ul>
	<li><a href="deaktiverte?a=brukere">Vis deaktiverte brukere &raquo;</a></li>
	<li><a href="deaktiverte?a=spillere">Vis deaktiverte spillere &raquo;</a></li>
</ul>';
		
		// hva skal vi vise?
		switch (getval("a"))
		{
			case "brukere":
				self::add_css();
				self::vis_brukere();
			break;
			
			case "spillere":
				self::add_css();
				self::vis_spillere();
			break;
			
			default:
				ess::$b->page->add_title("Deaktiverte brukere/spillere");
		}
	}
	
	/**
	 * CSS
	 */
	protected static function add_css()
	{
		ess::$b->page->add_css('
.pcd_crew { text-align: center; color: #555 }
.pcd_self { text-align: center; background-color: #1C1C1C !important }');
	}
	
	/**
	 * Tabell header
	 */
	protected static function table_header()
	{
		echo '
<table class="table center">
	<thead>
		<tr>
			<th>Hvem</th>
			<th>Når</th>
			<th>Deaktivert selv?</th>
			<th>Begrunnelse</th>
		</tr>
	</thead>
	<tbody>';
	}
	
	/**
	 * Tabell footer
	 */
	protected static function table_footer($pagei)
	{
		echo '
	</tbody>
</table>
<p class="c">'.$pagei->pagenumbers().'</p>';
	}
	
	/**
	 * Vise deaktiverte brukere
	 */
	protected static function vis_brukere()
	{
		global $__server;
		ess::$b->page->add_title("Deaktiverte brukere");
		
		// hente listen over brukere
		$pagei = new pagei(pagei::PER_PAGE, 20, pagei::ACTIVE_GET, "side");
		$result = $pagei->query("
			SELECT
				u_id, u_email, u_access_level, u_deactivated_time, u_deactivated_up_id, u_deactivated_reason, u_deactivated_note,
				up_id, up_name, up_access_level, up_deactivated_time, up_deactivated_up_id, up_deactivated_reason, up_deactivated_note
			FROM
				users
				JOIN users_players ON up_id = u_active_up_id
			WHERE
				u_access_level = 0
			ORDER BY u_deactivated_time DESC");
		
		echo '
<h1>Deaktiverte brukere</h1>';
		self::table_header();
		
		while ($row = mysql_fetch_assoc($result))
		{
			if ($row['u_access_level'] == 0)
			{
				echo '
		<tr>
			<td>'.game::profile_link($row['up_id'], $row['up_name'], $row['up_access_level'], true, $__server['relative_path'].'/min_side?u_id='.$row['u_id']).'</td>
			<td class="nowrap r">'.ess::$b->date->get($row['u_deactivated_time'])->format().'</td>
			<td'.($row['u_deactivated_up_id'] == $row['up_id'] ? ' class="pcd_self">Ja' : ' class="pcd_crew">Nei').'</td>
			<td>'.game::format_data($row['u_deactivated_reason']).'</td>
		</tr>';
			}
		}
		
		self::table_footer($pagei);
	}
	
	/**
	 * Vis deaktiverte spillere
	 */
	protected static function vis_spillere()
	{
		global $__server;
		ess::$b->page->add_title("Deaktiverte brukere");
		
		// hente listen over brukere
		$pagei = new pagei(pagei::PER_PAGE, 20, pagei::ACTIVE_GET, "side");
		$result = $pagei->query("
			SELECT
				u_id, u_email, u_access_level, u_deactivated_time, u_deactivated_up_id, u_deactivated_reason, u_deactivated_note,
				up_id, up_name, up_access_level, up_deactivated_time, up_deactivated_up_id, up_deactivated_reason, up_deactivated_note
			FROM
				users
				JOIN users_players ON u_id = up_u_id
			WHERE
				up_access_level = 0 AND (u_access_level != 0 OR u_deactivated_time != up_deactivated_time)
			ORDER BY up_deactivated_time DESC");
		
		echo '
<h1>Deaktiverte spillere</h1>
<p>Merk: Denne listen viser ikke spillere som har blitt deaktivert samtidig som brukeren ble deaktivert.</p>';
		self::table_header();
		
		while ($row = mysql_fetch_assoc($result))
		{
			if ($row['u_access_level'] == 0)
			{
				echo '
		<tr>
			<td>'.game::profile_link($row['up_id'], $row['up_name'], $row['up_access_level'], true, $__server['relative_path'].'/min_side?up_id='.$row['up_id']).'</td>
			<td class="nowrap r">'.ess::$b->date->get($row['up_deactivated_time'])->format().'</td>
			<td'.($row['up_deactivated_up_id'] == $row['up_id'] ? ' class="pcd_self">Ja' : ' class="pcd_crew">Nei').'</td>
			<td>'.game::format_data($row['up_deactivated_reason']).'</td>
		</tr>';
			}
		}
		
		self::table_footer($pagei);
	}
}

ess::$b->page->load();