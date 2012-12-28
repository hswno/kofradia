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
 * Support-henvendelse
 */
var SupportHenvendelse = new Class({
	/** Construct */
	initialize: function(su_id, own)
	{
		this.su_id = su_id;
		this.own = own;
		var self = this;
		
		// vise/skjule meldingsskjema
		$("support_reply_header").addEvent("click", function()
		{
			var elm = $("support_reply_container");
			if (elm.hasClass("hide"))
			{
				// vis skjemaet
				elm.removeClass("hide");
				self.timer_enabled = true;
				self.timer_start();
			}
			else
			{
				// skjul skjemaet
				elm.addClass("hide");
				self.timer_enabled = false;
				self.timer_stop();
			}
		});
		
		// koble til preview knappen
		$("support_reply_preview_button").addEvent("click", function()
		{
			$("support_reply_preview")
				.fade("hide")
				.removeClass("hide")
				.fade("in");
			
			// forhåndsvis innhold
			preview_bb(null, $("support_reply_text").get("value"), [], "support_reply_preview_view");
		});
		
		// fjerne forhåndsvisningen
		$$("#support_reply_preview h2").addEvent("click", function()
		{
			$("support_reply_preview").addClass("hide");
		});
		
		if (!this.own)
		{
			// status for timer
			this.timer_state = false;
			this.timer_enabled = false;
			this.timer = false;
			
			// starte timer?
			if (!$("support_reply_container").hasClass("hide"))
			{
				this.timer_enabled = true;
				this.timer_start();
			}
			
			// aktiver aktiv/idle funksjon for siden
			document.addEvents({
				"active": function()
				{
					self.timer_start();
				},
				"idle": function()
				{
					self.timer_stop();
				}
			});
		}
	},
	
	/** Opprett XHR-objekt */
	create_xhr: function()
	{
		this.xhr = new Request({
			"url": relative_path + "/support/",
			"data": { "su_id": this.su_id, "load_status": 1 },
			"autoCancel": true
		});
		
		var self = this;
		this.xhr.addEvents({
			"success": function(text, xml)
			{
				// oppdater statusboks
				self.set_status(text);
			},
			"failure": function(xhr)
			{
				// deaktiver timer
				self.timer_enabled = false;
				self.timer_stop();
				
				// sett status
				if (xhr.responseText == "ERROR:404-SUPPORT")
				{
					self.set_status("<p>Du har ikke lenger tilgang til denne henvendelsen.</p>");
					return;
				}
				
				self.set_status(ajax.format_error(xhr.responseText));
			}
		});
	},
	
	/** Sett status */
	set_status: function(html)
	{
		html = ajax.parse_data(html);
		$("support_reply_status").set("html", html).check_html();
		ajax.refresh();
	},
	
	/** Starte timer */
	timer_start: function()
	{
		// skal ikke starte, evt. kjører?
		if (!this.timer_enabled || this.timer_state) return;
		this.timer_state = true;
		
		// må opprette xhr?
		if (!this.xhr) this.create_xhr();
		
		// oppdater info nå
		this.xhr.send();
		
		// stopp mulig timer og aktiver ny
		$clear(this.timer);
		this.timer = this.xhr.send.bind(this.xhr).periodical(5000);
	},
	
	/** Stopp timer */
	timer_stop: function()
	{
		// kjører ikke?
		if (!this.timer_state) return;
		this.timer_state = false;
		
		// stopp timer og avbryt evt. xhr
		$clear(this.timer);
		this.xhr.cancel();
	}
});