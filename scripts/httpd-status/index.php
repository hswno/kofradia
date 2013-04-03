<?php

require "../../base.php";
global $_base;

$_base->page->theme_file = "doc";
access::need("mod");
$_base->page->add_title("Apache status");

echo '
<h1>Apache status</h1>
<p>
	Velg sorteringsrekkefølge:
</p>';

$orders = array(
	"last" => array("Siste visning &raquo;", "Sorterer etter siste visning"),
	"count" => array("Antall rader oppført &raquo;", "Sorterer etter antall rader oppført"),
);

foreach ($orders as $order => $info)
{
	echo '
<p>
	<a href="?order='.urlencode($order).'">'.$info[0].'</a>
</p>';
}


if (isset($_GET['order']) && array_key_exists($_GET['order'], $orders))
{
	$order = $_GET['order'];
	$_base->page->add_message($orders[$order][1]);
}
else
{
	if (isset($_GET['order']))
	{
		$_base->page->add_message("Ugyldig sorteringsnavn.", "error");
	}
	
	$_base->page->load();
}


$request = new httpreq();
$data = $request->get("/httpd-status-server", array());

if (!$data)
{
	echo '
<p><b>Feil:</b> Kunne ikke hente data.</p>';
}

else
{
	$data = $data['content'];
	
	// offset
	$data = substr($data, strpos($data, '<b>Request</b></td></tr>'));
	
	/*
	
	<tr bgcolor="#ffffff"><td><b>0-0</b><td>12488<td>10/4899/26246<td><b>K</b>
	
	<td>100.31<td>2<td>3<td>54.7<td>22.59<td>126.61
	<td nowrap><font face="Arial,Helvetica" size="-1">80.212.189.104</font><td nowrap><font face="Arial,Helvetica" size="-1">www.kofradia.no</font><td nowrap><font face="Arial,Helvetica" size="-1">GET /antibot/bilde?aid=12022&amp;id=9&amp;c=117269810661 HTTP/1.1</font></tr>
	
	
	*/
	
	
	
	
	$result = false;
	if (!preg_match_all('#<tr.*<td>(.*)<td>(.*)<td>(.*)<td>(.*)
	<td>(.*)<td>(.*)<td>(.*)<td>(.*)<td>(.*)<td>(.*)
	<td[^>]*>(?:<font face="Arial,Helvetica" size="-1">)(.*)(?:</font>)<td[^>]*>(?:<font face="Arial,Helvetica" size="-1">)(.*)(?:</font>)<td[^>]*>(?:<font face="Arial,Helvetica" size="-1">)(.*)(?:</font>)#', $data, $result, PREG_SET_ORDER))
	{
		echo '
<p>Ingen samsvar!</p>';
	}
	
	else
	{
		/*
			1: Srv	Child Server number - generation
			2: PID	OS process ID
			3: Acc	Number of accesses this connection / this child / this slot
			4: M	Mode of operation
			5: CPU	CPU usage, number of seconds
			6: SS	Seconds since beginning of most recent request
			7: Req	Milliseconds required to process most recent request
			8: Conn	Kilobytes transferred this connection
			9: Child	Megabytes transferred this child
			10: Slot	Total megabytes transferred this slot
			11: Host
			12: VHost
			13: Request
		*/
		
		$hosts = array();
		$hosts_count = array();
		$hosts_last = array();
		
		foreach ($result as $key => $row)
		{
			$hosts_count[$row[11]]++;
			$hosts_last[$row[11]] = array_key_exists($row[11], $hosts_last) ? min($hosts_last[$row[11]], $row[6]) : $row[6];
			$hosts[$row[11]][] = array_merge($row, array($hosts_last[$row[11]]));
		}
		
		if ($order == "count")
		{
			// sorter med flest rader øverst
			array_multisort($hosts_count, SORT_DESC, $hosts_last, SORT_ASC, $hosts);
		}
		else
		{
			// sorter med siste visning øverst
			array_multisort($hosts_last, SORT_ASC, $hosts_count, SORT_DESC, $hosts);
		}
		
		echo '
<table class="table tablemb">
	<thead>
		<tr>
			<th>Last</th>
			<th>CPU</th>
			<th>BW</th>
			<th>VHost</th>
			<th>Request</th>
		</tr>
	</thead>
	<tbody>';
		
		foreach ($hosts as $host => $rows)
		{
			$count = count($rows);
			
			echo '
		<tr class="spacer">
			<td colspan="5">&nbsp;</td>
		</tr>
		<tr>
			<th colspan="5"><span style="float: right">Records: '.$count.'</span> Host: <a href="../../admin/brukere/finn?ip='.urlencode($host).'">'.htmlspecialchars($host).'</a></th>
		</tr>';
			
			foreach ($rows as $row)
			{
				echo '
		<tr>
			<td>'.$row[6].' <span style="color: #666666">second(s)</span></td>
			<td>'.$row[5].'</td>
			<td>'.$row[8].'</td>
			<td>'.$row[12].'</td>
			<td>'.$row[13].'</td>
		</tr>';
			}
		}
		
		echo '
	</tbody>
</table>';
	}
}

$_base->page->load();