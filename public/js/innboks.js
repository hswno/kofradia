/*
 * JavaScript for Kofradia
 * Skrevet av Henrik Steen
 * www.henrist.net
 *
 * Beskyttet av åndsverkloven
 * Alle rettigheter tilhører Henrik Steen dersom ikke annet er oppgitt
 *
 * Copyright (c) 2010 All rights reserved
 */

/**
 * Privat melding
 */
var InboxMessage = new Class({
	options: {
		/** Hvor ofte skal det sjekkes for nye meldinger */
		"interval": 3000
	},
	
	/** Construct */
	initialize: function(it_id, last_id, start_update)
	{
		this.it_id = it_id;
		this.last_id = last_id;
		this.container = $("innboks");
		var self = this;
		
		// vis svarskjema
		$$(".reply_link_form_show").each(function(elm)
		{
			elm.addEvent("click", function(event)
			{
				event.stop();
				self.reply_form_show();
			});
		});
		
		// forhåndsvise svar
		$("reply_link_preview").addEvent("click", function(event)
		{
			event.stop();
			
			previewDL(null, "textContent", "previewDT", "previewDD");
		});
		
		// starte oppdatering?
		if (start_update)
		{
			this.startUpdate();
		}
	},
	
	/** Ignorere active/idle? */
	ignore_state: false,
	
	/** Oppdater etter meldinger */
	startUpdate: function()
	{
		var self = this;
		
		// legg til info om nye meldinger
		this.info = new Element("div", {"class": "innboks_info", "text": "Nye meldinger i denne meldingstråden vil automatisk bli lagt til ovenfor denne boksen."}).inject(this.container, "top");
		this.info_span = new Element("span", {"style": "float: right; color: #666"}).inject(this.info, "top");
		
		// sett events for aktiv/inaktiv
		var self = this;
		document.addEvents({
			"active": function()
			{
				if (self.ignore_state) return;
				if (!self.info_inactive) return;
				
				// fjern melding om inaktivitet
				self.info_inactive.destroy();
				
				// start timer og oppdater
				self.startUpdateTimer();
				self.update();
			},
			"idle": function()
			{
				if (self.ignore_state) return;
				
				// legg til melding om inaktivetet
				self.info_inactive = new Element("div", {"class": "innboks_info", "text": "Henter ikke meldinger pga. inaktivetet."}).inject(self.container, "top");
				
				// avbryt mulig xhr
				self.xhr.cancel();
				
				// stopp timer
				$clear(self.timer);
			}
		});
		
		// sett opp ajax objektet
		this.xhr = new Request({
			"url": relative_path + "/ajax/inbox?it=" + this.it_id + "&a=new_replies",
			"data": { "im_id": this.last_id},
			"autoCancel": true
		});
		
		this.xhr.addEvents({
			"success": function(text, xml)
			{
				// vis sist oppdatert tidspunkt
				var d = new Date($time()+window.servertime_offset);
				self.info_span.set("html", "Oppdatert " + str_pad(d.getHours()) + ":" + str_pad(d.getMinutes()) + ":" + str_pad(d.getSeconds()));
				
				// hent ut meldingene
				var messages = xml.getElementsByTagName("message");
				if (messages.length == 0) return;
				
				// legg til meldingene
				for (var i = 0; i < messages.length; i++)
				{
					new Element("div", {"html": messages[i].firstChild.nodeValue}).inject(self.container, "top");
				}
				
				// sett siste meldingsid
				self.xhr.options.data["im_id"] = xml.getElementsByTagName("list")[0].getAttribute("last_im_id");
				
				// sjekk html
				self.container.getFirst().check_html().find_report_links();
			},
			"failure": function(xhr)
			{
				// ikke lenger logget inn?
				if (xhr.responseText == "ERROR:SESSION-EXPIRE" || xhr.responseText == "ERROR:WRONG-SESSION-ID")
				{
					new Element("div", {"class": "innboks_info", "text": "Du er ikke lenger logget inn. Oppdaterer ikke lenger etter nye meldinger."}).inject(self.container, "top");
				}
				
				else
				{
					new Element("div", {"class": "innboks_info", "html": "<b>Oppdatering feilet:</b> "+xhr.responseText+"<br />Henter ikke lenger oppdateringer."}).inject(self.container, "top");
					self.container.getFirst().check_html();
				}
				
				// stopp timer
				$clear(self.timer);
				
				// sett info
				self.info_span.set("text", "Oppdateringer avbrutt.");
				self.ignore_state = true;
			}
		});
		
		// start oppdatering
		this.startUpdateTimer();
	},
	
	/** Start oppdateringstimer */
	startUpdateTimer: function()
	{
		this.timer = this.update.bind(this).periodical(this.options.interval);
	},
	
	/** Hent oppdateringer */
	update: function()
	{
		// sett info
		this.info_span.set("text", "Oppdaterer..");
		
		// oppdater
		this.xhr.send();
	},
	
	/** Vise svarboks */
	reply_form_show: function()
	{
		// skjul svarlenkene
		$$(".reply_link_form_show").each(function(elm)
		{
			elm.setStyle("display", "none");
		});
		
		// vis svarskjemaet
		var form = $("container_reply");
		form.setOpacity(0).setStyle("display", "block").fade("in");
		
		// scroll ned
		form.goto(-25);
		
		// sett fokus
		(function(){ form.getElement("textarea").focus(); }).delay(100);
	}
});


/**
 * Opprette ny meldingstråd
 */
var innboks_ny = {
	receivers: [],
	limit: 0, // maks antall mottakere (5 totalt)
	inited: false,
	xml: null,
	init: function()
	{
		this.inited = true;
		this.rec = $("rec");			// spillernavnene
		this.rec_list = $("rec_list");	// selve listen
		this.rec_new = $("rec_new");	// ny mottaker
		this.rec_s = $("rec_s");		// statusfelt (x/max)
		this.rec_form = $("rec_form");	// skjemaet
		this.rec_input = this.rec_new.getElement("input");	// søkefeltet
		this.rec_results = this.rec_new.getElement("ul");		// søkeresultater
		this.rec_newm = $("rec_newm");

		// skjul spillernavnene
		var r = this.rec.value;
		this.rec.parentNode.removeChild(this.rec);
		var e = new Element("input", {"name": "receivers", "type": "hidden", "value": r});
		e.inject(this.rec_form);
		this.rec = this.rec_form.lastChild;

		// sett opp statusreferanse
		var t = document.createTextNode("");
		this.rec_s.appendChild(t);
		this.rec_st = this.rec_s.firstChild;

		// sett opp mottakerene
		for (var i = 0; i < this.receivers.length; i++)
		{
			this.add(this.receivers[i][0], this.receivers[i][1], this.receivers[i][2]);
		}

		// oppdater status
		this.update_status();
		this.setm("Fyll inn feltet og trykk enter!");

		// søkefeltet
		var m = this;
		this.rec_input.addEvent("keypress", function(event)
		{
			m.checkKey(m, event);
		});
		
		this.rec_form.getElement("input[name=title]").focus();
	},
	
	// legg til spiller i listen
	add: function(up_id, up_name, view)
	{
		var e = document.createElement("div");
		e.setAttribute("up_id", up_id);
		e.setAttribute("up_name", up_name);
		e.innerHTML = '<li onmouseover="this.className=\'hover\'" onmouseout="this.className=\'\'"><span class="r_user">'+view+'</span><a href="#" onclick="innboks_ny.rem(this); return false" class="r_del">X</a><div class="clear"></li>';
		
		this.rec_list.appendChild(e);
		this.rec_list.style.display = "block";
		check_html(this.rec_list);
	},
	
	// fjern spiller fra listen
	rem: function(elm)
	{
		elm.parentNode.parentNode.parentNode.removeChild(elm.parentNode.parentNode);

		this.update_status();

		if (this.rec_list.childNodes.length == 0)
		{
			// ingen enheter igjen
			this.rec_list.style.display = "none";
		}
	},

	// oppdater status
	update_status: function()
	{
		var ant = this.rec_list.childNodes.length;

		this.rec_st.nodeValue = "(" + ant + "/" + this.limit + ")";

		// vis legg til spiller felt?
		if (ant < this.limit) { this.rec_new.style.display = "block"; }
		else { this.rec_new.style.display = "none"; return false; }

		return true;
	},


	// legg til spiller i søkeresultatene
	new_clean: function() { while (this.rec_results.childNodes.length > 0) { this.rec_results.removeChild(this.rec_results.childNodes[0]); } this.rec_results.style.display = "none"; },
	new_add: function(up_id, up_name, view)
	{
		var e = document.createElement("div");
		e.setAttribute("up_id", up_id);
		e.setAttribute("up_name", up_name);
		e.setAttribute("user_view", view);

		// er vi i listen allerede?
		var in_list = false;
		for (var i = 0; i < this.rec_list.childNodes.length; i++)
		{
			if (this.rec_list.childNodes[i].getAttribute("up_id") == up_id)
			{
				in_list = true;
				break;
			}
		}

		e.innerHTML = '<li onmouseover="this.className=\'hover\'" onmouseout="this.className=\'\'"><span class="r_user">'+view+'</span>'+(in_list ? '<span class="add">+</span>' : '<a href="#" onclick="innboks_ny.new_append(this); return false" class="add">+</a>')+'<div class="clear"></li>';

		this.rec_results.appendChild(e);
		this.rec_results.style.display = "block";
		check_html(this.rec_results);
	},

	// legg til spiller i spillerlisten
	new_append: function(elm)
	{
		var e = elm.parentNode.parentNode;
		var up_id = e.getAttribute("up_id");
		var up_name = e.getAttribute("up_name");
		var view = e.getAttribute("user_view");

		elm.parentNode.parentNode.parentNode.removeChild(elm.parentNode.parentNode);

		this.add(up_id, up_name, view);
		if (!this.update_status()) return;

		if (this.rec_results.childNodes.length == 0) this.search(this.rec_input.value);
		this.rec_input.focus();
	},

	// sjekk tast i input feltet (sjekk for enter etc)
	checkKey: function(t, event)
	{
		// søke?
		if (event.key == "enter")
		{
			t.search(this.rec_input.value);
			event.stop();
		}
	},

	// søk etter spiller
	setm: function(msg) { if (!msg || msg == "") { this.rec_newm.style.display = "none"; } else { this.rec_newm.innerHTML = msg; this.rec_newm.style.display = "block"; } },
	search: function(q)
	{
		this.new_clean();
		this.setm("Søker etter spillere..");
		var self = this;
		
		// xhr ojekt
		var xhr = new Request({
			"url": relative_path + "/ajax/find_user",
			"data": { "limit": 5 },
			"autoCancel": true
		});
		xhr.addEvent("success", function(text, xml)
		{
			self.xml = xml;
			self.displayR();
		});
		xhr.options.data["q"] = "%"+q+"%";
		xhr.options.data["exclude"] = this.active();
		xhr.send();
	},

	displayR: function()
	{
		this.new_clean();
		if (!this.xml) return;
		var list = this.xml.getElementsByTagName("userlist")[0];

		if (list.childNodes.length == 0)
		{
			this.setm("Fant ingen spillere.");
		}

		else
		{
			var e;
			for (var i = 0; i < list.childNodes.length; i++)
			{
				e = list.childNodes[i];
				this.new_add(e.getAttribute("up_id"), e.getAttribute("up_name"), e.firstChild.nodeValue);
			}

			// begrenset?
			var a = list.getAttribute("results");
			var c = list.childNodes.length;
			if (a > c)
			{
				this.setm("Begrenset til " + c + " av " + a + " spillere.");
			}
			else
			{
				this.setm();
			}
		}
	},


	// finn ut hvilke spillere vi har i listen
	active: function()
	{
		var list = [];
		for (var i = 0; i < this.rec_list.childNodes.length; i++)
		{
			list.push(this.rec_list.childNodes[i].getAttribute("up_id"));
		}
		return list.join(",");
	},

	// fullfør formen (send melding)
	submit: function()
	{
		// hent mottakerliste
		var m = this.active();

		// ingen mottakere?
		if (m == "")
		{
			alert("Du må legge til en mottaker!");
			return false;
		}

		this.rec.value = "ID:" + this.active();
		return true;
	}
}