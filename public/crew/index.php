<?php

require "config.php";
global $_base, $__server;

if (isset($_GET['facebook_cache_clear']))
{
	cache::delete("facebook_posts");
	ess::$b->page->add_message("Cache for Facebook-feed på forsiden ble slettet.");
	
	redirect::handle("crew");
}

echo '
<div class="bg1_c xxsmall">
	<h1 class="bg1">Crew<span class="left"></span><span class="right"></span></h1>
	<div class="bg1">
		<p>Informasjon:</p>
		<ul>
			<li><a href="crewlogg">Vis crewlogg</a></li>
			<li><a href="../min_side?u&a=crewlog">Vis crewhendelser</a></li>
			<li><a href="banned">Vis aktive blokkeringer</a></li>
			<li><a href="deaktiverte">Vis liste over deaktiverte brukere/spillere</a></li>
			<li><a href="advarsel">Vis liste over brukere med høy advarselpoeng</a></li>
			<li><a href="soknader?all">Vis tidligere søknader</a></li>
		</ul>
		<p>Verktøy:</p>
		<ul>
            <li><a href="../extended_access">Endre eget passord til utvidet tilganger</a></li>
			<li><a href="htpass">Endre eget HT-Pass</a></li>
			<li><a href="./?facebook_cache_clear">Slett cache for Facebook-feed på forsiden</a></li>'.(access::has("forum_mod") ? '
			<li><a href="'.$__server['relative_path'].'/polls/admin">Administrer avstemninger</a></li>' : '').'
		</ul>
	</div>
</div>';

$_base->page->load();
