<?php

require "../base.php";

ess::$b->page->add_title("Utviklerverktøy");

echo '
<h1>Utviklerverktøy</h1>
<p>Her finner du verktøy for å administrere utviklersiden.</p>
<ul class="spacer">
	<li><a href="set_pass">Endre passord på en bruker</a></li>
	<li><a href="login">Logg inn som en annen bruker</a></li>
	<li><a href="replace_db">Erstatt databasen med ny versjon</a></li>
</ul>
<p>Opprett gjerne nye scripts hvis det er handlinger som man føler kan være nødvendige å utføre på utviklersiden.</p>';

ess::$b->page->load();