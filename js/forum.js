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

/** Feilbehandling for forumet */
function forum_format_error(data, no_html)
{
	if (data == "ERROR:403-FORUM") return "Du har ikke tilgang til dette forumet. Mest sannsynlig har du blitt logget ut av utvidede tilganger.";
	if (data == "ERROR:404-REPLY") return "Forumsvaret er slettet.";
	return ajax.format_error(data, no_html);
}

/**
 * Opprette ny forumtråd
 */
var NewForumTopic = new Class({
	/** Construct */
	initialize: function(forum_id)
	{
		this.forum_id = forum_id;
		this.container_add = $("topic_info_add");
		this.container = $("topic_info");
		this.info = {
			"title": $("topic_title"),
			"text": $("topic_text"),
			"type": $("topic_type"),
			"locked": $("topic_locked"),
			"add": $("topic_add"),
			"preview": $("topic_preview")
		};
		var self = this;
		
		// legg til events
		this.info.add.addEvent("click", function(event)
		{
			event.stop();
			
			// forsøk å legg til
			self.add();
		});	
		this.info.preview.addEvent("click", function(event)
		{
			event.stop();
			
			// forhåndsvis
			self.preview();
		});
	},
	
	/** Forhåndsvise forumtråden */
	preview: function()
	{
		// ajax objekt
		var self = this;
		if (!this.preview_xhr)
		{
			this.preview_xhr = new Request({
				"url": relative_path + "/ajax/forum/topic_preview",
				"autoCancel": true
			});
			
			this.preview_xhr.addEvents({
				// vellykket mottatt
				"success": function(text)
				{
					self.container
						.set("html", text)
						.check_html()
						.fade("in");
				},
				
				// mislykket
				"failure": function(xhr)
				{
					self.container
						.set("html", '<div class="forum_preview_info error_box">'+forum_format_error(xhr.responseText)+'</div>')
						.check_html()
						.fade("in");
				}
			});
		}
		
		// sett info
		var height = Math.max(0, this.container.getSize().y - 20);
		this.container.setOpacity(0).set("html", '<div class="forum_preview_info">Henter forhåndsvisning..</div>').fade(0.5);
		this.container.getElement("div").setStyle("min-height", height);
		
		// hent forhåndsvisning
		this.preview_xhr.options.data = {"text": this.info.text.get("value")};
		this.preview_xhr.send();
	},
	
	/** Legg til forumtråden */
	add: function()
	{
		// avbryt mulig forhåndsvisning
		if (this.preview_xhr) this.preview_xhr.cancel();
		this.container.empty();
		
		// ajax objekt
		if (!this.add_xhr)
		{
			this.add_xhr = new Request({
				"url": relative_path + "/ajax/forum/topic_add",
				"data": { "sid": User.s_id, "forum_id": this.forum_id },
				"autoCancel": true
			});
			
			var self = this;
			this.add_xhr.addEvents({
				// vellykket mottatt
				"success": function(text)
				{
					// videresende?
					if (text.substring(0, 8) == "REDIRECT")
					{
						navigateTo(text.substring(9));
					}
					
					self.container_add.set("html", '<div class="forum_preview_info"><b>Forumtråden ble lagt til.</b></div>').fade("in");
				},
				
				// mislykket
				"failure": function(xhr)
				{
					self.container_add.set("html", '<div class="forum_preview_info error_box">'+forum_format_error(xhr.responseText)+'</div>').fade("in").check_html();
				}
			});
		}
		
		// sett info
		this.container_add.setOpacity(0).set("html", '<div class="forum_preview_info"><b>Oppretter forumtråd..</b></div>').fade(0.5);
		
		// legg til svaret
		this.add_xhr.options.data["title"] = this.info.title.get("value");
		this.add_xhr.options.data["text"] = this.info.text.get("value");
		if (this.info.type)
			this.add_xhr.options.data["type"] = this.info.type.get("value");
		if (this.info.locked)
			this.add_xhr.options.data["locked"] = this.info.locked.get("checked") ? 1 : 0;
		this.add_xhr.send();
	}
});

/**
 * Redigering av en forumtråd
 */
var EditForumTopic = new Class({
	/** Construct */
	initialize: function(topic_id, last_edit)
	{
		this.topic_id = topic_id;
		this.container_edit = $("topic_info_edit");
		this.container_preview = $("topic_info_preview");
		this.last_edit = last_edit;
		this.info = {
			"title": $("topic_title"),
			"text": $("topic_text"),
			"section": $("topic_section"),
			"type": $("topic_type"),
			"locked": $("topic_locked"),
			"save": $("topic_save"),
			"preview": $("topic_preview")
		};
		var self = this;
		
		// legg til events
		this.info.save.addEvent("click", function(event)
		{
			event.stop();
			
			// forsøk å lagre endringer
			self.edit();
		});	
		this.info.preview.addEvent("click", function(event)
		{
			event.stop();
			
			// forhåndsvis
			self.preview();
		});
	},

	/** Forhåndsvise forumtråden */
	preview: function()
	{
		// ajax objekt
		var self = this;
		if (!this.preview_xhr)
		{
			this.preview_xhr = new Request({
				"url": relative_path + "/ajax/forum/topic_preview",
				"data": { "topic_id": this.topic_id },
				"autoCancel": true
			});
			
			this.preview_xhr.addEvents({
				// vellykket mottatt
				"success": function(text)
				{
					self.container_preview
						.set("html", text)
						.check_html()
						.fade("in");
				},
				
				// mislykket
				"failure": function(xhr)
				{
					self.container_preview
						.set("html", '<div class="forum_preview_info error_box">'+forum_format_error(xhr.responseText)+'</div>')
						.check_html()
						.fade("in");
				}
			});
		}
		
		// sett info
		var height = Math.max(0, this.container_preview.getSize().y - 20);
		this.container_preview.setOpacity(0).set("html", '<div class="forum_preview_info">Henter forhåndsvisning..</div>').fade(0.5);
		this.container_preview.getElement("div").setStyle("min-height", height);
		
		// hent forhåndsvisning
		this.preview_xhr.options.data["text"] = this.info.text.get("value");
		this.preview_xhr.send();
	},
	
	/** Lagre endringer for forumtråden */
	edit: function()
	{
		// avbryt mulig forhåndsvisning
		if (this.preview_xhr) this.preview_xhr.cancel();
		this.container_preview.empty();
		
		// ajax objekt
		if (!this.edit_xhr)
		{
			this.edit_xhr = new Request({
				"url": relative_path + "/ajax/forum/topic_edit",
				"data": { "sid": User.s_id, "topic_id": this.topic_id },
				"autoCancel": true
			});
			
			var self = this;
			this.edit_xhr.addEvents({
				// vellykket mottatt
				"success": function(text)
				{
					// videresende?
					if (text.substring(0, 8) == "REDIRECT")
					{
						navigateTo(text.substring(9));
					}
					
					self.container_edit.set("html", '<div class="forum_preview_info"><b>Endringene ble lagret.</b></div>').fade("in");
				},
				
				// mislykket
				"failure": function(xhr)
				{
					self.info.save.set("disabled", false);
					
					var text = xhr.responseText;
					if (text.substring(0, 27) == "ERROR:TOPIC-ALREADY-EDITED:")
					{
						self.last_edit = text.substring(27);
						text = "Denne forumtråden har blitt redigert av noen andre etter du begynte å redigere. Trykk lagre på nytt for å overstyre."
							+'<br /><a href="'+relative_path+'/forum/topic?id='+self.topic_id+'" target="_blank">Vis forumtråden i nytt vindu for å sammenlikne.</a>';
					}
					else text = forum_format_error(text);
					
					self.container_edit.set("html", '<div class="forum_preview_info error_box">'+text+'</div>').fade("in").check_html();
				}
			});
		}
		
		// sett info
		this.container_edit.setOpacity(0).set("html", '<div class="forum_preview_info"><b>Lagrer endringer i forumtråden..</b></div>').fade(0.5);
		this.info.save.set("disabled", true);
		
		// legg til svaret
		this.edit_xhr.options.data["last_edit"] = this.last_edit;
		this.edit_xhr.options.data["title"] = this.info.title.get("value");
		this.edit_xhr.options.data["text"] = this.info.text.get("value");
		if (this.info.section) this.edit_xhr.options.data["section"] = this.info.section.get("value");
		if (this.info.type)
			this.edit_xhr.options.data["type"] = this.info.type.get("value");
		if (this.info.locked)
			this.edit_xhr.options.data["locked"] = this.info.locked.get("checked") ? 1 : 0;
		this.edit_xhr.send();
	}
});

/**
 * Visning av forumtråd
 */
var ForumTopic = new Class({
	options: {
		// hvor ofte skal det sjekkes for endringer?
		interval: 3000 // hvert 3. sekund
	},
	
	/** Construct */
	abort: false,
	initialize: function(topic_id, id_list, last_edit_list, is_last_page, show_deleted, access, topic_last_edit)
	{
		// scrolle?
		var scroll_to = $("forum_scroll_here");
		if (scroll_to)
		{
			(function() { scroll_to.goto(-15); }).delay(100);
		}
		
		this.handleImages();
		
		// container for emnet og svarene
		this.container = $("forum_topic_container");
		
		// lagre variabler
		this.topic_id = topic_id;
		this.topic_last_edit = topic_last_edit;
		this.id_list = id_list;
		this.last_id = this.id_list.getLast();
		this.get_new = !!is_last_page;
		this.show_deleted = !!show_deleted;
		this.time_last = Math.floor(window.serverTime/1000);
		this.access = access;
		this.last_edit_list = new Hash(last_edit_list);
		
		// sjekk for lenker
		this.detectLinks();
		this.detectLinksOnce();
		
		// start oppdateringer
		this.startUpdate();
	},
	
	/** Kontroller etter div html */
	check: function()
	{
		this.container.check_html().find_report_links();
		this.detectLinks();
	},
	
	/** Oppdag lenker til forumhandlinger */
	detectLinks: function()
	{
		var self = this;
		
		// redigere lenker
		this.container.getElements(".forum_link_reply_edit").each(function(elm)
		{
			// allerede gått gjennom?
			if (elm.retrieve("forum_parsed")) return;
			elm.store("forum_parsed", true);
			
			// legg til event
			elm.addEvent("click", function(event)
			{
				event.stop();
				self.reply_edit(this.get("rel"));
			});
		});
		
		// slette lenker
		this.container.getElements(".forum_link_reply_delete").each(function(elm)
		{
			// allerede gått gjennom?
			if (elm.retrieve("forum_parsed")) return;
			elm.store("forum_parsed", true);
			
			// legg til event
			elm.addEvent("click", function(event)
			{
				event.stop();
				self.reply_delete(this.get("rel"));
			});
		});
		
		// gjenopprette lenker
		this.container.getElements(".forum_link_reply_restore").each(function(elm)
		{
			// allerede gått gjennom?
			if (elm.retrieve("forum_parsed")) return;
			elm.store("forum_parsed", true);
			
			// legg til event
			elm.addEvent("click", function(event)
			{
				event.stop();
				self.reply_restore(this.get("rel"));
			});
		});
		
		// annonsere svar
		this.container.getElements(".forum_link_reply_announce").each(function(elm)
		{
			// allerede gått gjennom?
			if (elm.retrieve("forum_parsed")) return;
			elm.store("forum_parsed", true);
			
			// legg til event
			elm.addEvent("click", function(event)
			{
				event.stop();
				self.reply_announce(this.get("rel"));
			});
		});
	},
	
	/** Oppdag lenker som kun skal sjekkes en gang */
	detectLinksOnce: function()
	{
		if (!this.get_new) return;
		var self = this;
		
		// vis svarskjema
		if (User.u_id)
		{
			$("default_main").getElements(".forum_link_replyform").each(function(elm)
			{
				elm.addEvent("click", function(event)
				{
					event.stop();
					self.reply_form_show();
				});
			});
			
			// legge til svar
			$("forum_reply_button_add").addEvent("click", function(event)
			{
				event.stop();
				self.reply_add();
			});
			
			// forhåndsvis svar
			$("forum_reply_button_preview").addEvent("click", function(event)
			{
				event.stop();
				if (self.reply_container) self.reply_container.empty();
				self.reply_preview($("reply_preview"), $("replyText").get("value"));
			});
		}
		
		// hindre kjøring flere ganger
		this.detectLinksOnce = $empty;
	},
	
	/** Legge til events på bildene i forumet */
	handleImages: function()
	{
		var self = this;
		
		// finn alle containers og legg til events i alle bildene
		$$(".forum_text,.forum_signature").each(function(c)
		{
			// finn bildene
			c.getElements("span.bb_image").each(function(i)
			{
				self.addImage(c, i);
			});
			
			c.store("prev_height", c.getSize().y);
		});
	},
	
	/** Sørger for at siden blir scrollet ned når et bilde ovenfor det synlige området blir lastet inn */
	addImage: function(container, img)
	{
		// [img]?
		var bbimg = img.retrieve("BBImg");
		var obj = img;
		if (bbimg)
		{
			obj = bbimg;
		}
		
		obj.addEvent("load", function()
		{
			var height = container.getSize().y;
			var prev_height = container.retrieve("prev_height");
			
			// høyden har forandret seg
			if (height != prev_height)
			{
				var pos_y = img.getPosition().y;
				var scroll = window.getScroll();
				
				// lagre høyden
				container.store("prev_height", height);
				
				// scroll dersom det synlige området er nedenfor bildet
				if (scroll.y > pos_y)
				{
					// scroll tilsvarende som høyden endret seg
					window.scrollTo(scroll.x, scroll.y+height-prev_height);
				}
			}
		});
	},
	
	/**
	 * Start oppdatering av endringer i forum tråden:
	 * - Henter nye svar hvis man er på siste sider
	 * - Oppdaterer svar med nye endringer
	 * - Fjernet svar hvis noen blir slettet
	 */
	startUpdate: function()
	{
		// legg til info om nye forumsvar
		var text = this.get_new
			? "Nye forumsvar i forumtråden vil automatisk bli lagt til nedenfor denne boksen."
			: "Forumsvarene på denne siden blir automatisk oppdatert ved endringer.";
		
		this.info = this.addInfo(text);
		this.info_span = new Element("span", {"style": "float: right; color: #666"}).inject(this.info, "top");
		
		// sett events for aktiv/inaktiv
		var self = this;
		document.addEvents({
			"active": function()
			{
				if (!self.info_inactive || self.abort) return;
				
				// fjern melding om inaktivitet
				self.info_inactive.destroy();
				
				// start timer og oppdatert
				self.startUpdateTimer();
				self.update();
			},
			"idle": function()
			{
				if (self.abort) return;
				
				// legg til melding om inaktivitet
				self.info_inactive = self.addInfo("Henter ikke meldinger pga. inaktivitet.");
				
				// avbryt mulig xhr
				self.xhr.cancel();
				
				// stop timer
				$clear(self.timer);
			}
		});
		
		// opprett ajax objekt
		this.xhr = new Request({
			"url": relative_path + "/ajax/forum/topic_updates",
			"data": {
				"sid": User.s_id,
				"topic_id": this.topic_id
			},
			"autoCancel": true
		});
		this.xhr.addEvents({
			// data vellykket mottatt
			"success": this.dataSuccess.bind(this),
			
			// mislykket
			"failure": this.dataFailure.bind(this)
		});
		
		// hente nye svar?
		if (this.get_new) this.xhr.options.data["get_new"] = 1;
		
		// ikke fjerne slettede svar?
		if (this.show_deleted) this.xhr.options.data["no_delete"] = 1;
		
		// start timer
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
		this.info_span.set("text", "Oppdaterer..");
		this.xhr.options.data["r_id_list"] = this.id_list.join(",");
		this.xhr.options.data["r_last_id"] = this.last_id ? this.last_id : 0;
		this.xhr.options.data["time"] = this.time_last;
		this.xhr.options.data["topic_last_edit"] = this.topic_last_edit;
		this.xhr.send();
	},
	
	/** Mottatt data */
	dataSuccess: function(text, xml)
	{
		// sjekk for oppdaterte svar
		var rows = xml.getElementsByTagName("updated")[0].getElementsByTagName("post");
		for (var i = 0; i < rows.length; i++)
		{
			var reply_id = rows[i].getAttribute("id");
			var obj = new Element("div").set("html", rows[i].firstChild.nodeValue).getFirst();
			
			// samme som forrige tidspunkt?
			if (this.last_edit_list.get(reply_id) == rows[i].getAttribute("last_edit")) continue;
			
			// redigerer vi dette svaret?
			if (this.reply_in_edit.has(reply_id))
			{
				this.reply_in_edit[reply_id][0] = obj;
				new Element("div", {
					"class": "forum_reply_info info_box",
					"html": "Dette forumsvaret har blitt redigert av noen andre etter du begynte å redigere."
						+'<br /><a href="'+relative_path+'/forum/topic?id='+this.topic_id+'&amp;replyid='+reply_id+'" target="_blank">Vis forumsvaret i nytt vindu for å sammenlikne.</a>'
				}).setOpacity(0).inject(this.reply_in_edit[reply_id][1], "top").fade("in");
			}
			
			// skjult?
			else if (this.reply_hidden.has(reply_id))
			{
				this.reply_hidden[reply_id] = obj;
			}
			
			else
			{
				var c = $("m_"+reply_id);
				if (c)
				{
					obj.setOpacity(0).replaces(c).fade("in");
				}
			}
			
			// oppdater last_edit
			this.last_edit_list.set(reply_id, rows[i].getAttribute("last_edit"));
		};
		
		// sjekk om selve forumtråden er oppdatert
		var row = xml.getElementsByTagName("tupdated");
		if (row.length > 0)
		{
			row = row[0];
			var c = $("t"+this.topic_id);
			if (c)
			{
				// bytt ut
				new Element("div").set("html", row.firstChild.nodeValue).getFirst().setOpacity(0).replaces(c).fade("in");
			}
			
			this.topic_last_edit = row.getAttribute("last_edit");
		}
		
		// sjekk for slettede svar
		var rows = xml.getElementsByTagName("deleted")[0].getElementsByTagName("post");
		for (var i = 0; i < rows.length; i++)
		{
			var reply_id = rows[i].firstChild.nodeValue;
			
			// redigerer vi dette svaret?
			if (this.reply_in_edit.has(reply_id))
			{
				// har vi tilgang til å redigere slettede svar?
				/*if (this.access)
				{
					alert("Svaret du redigerer har blitt slettet. Du kan alikevel utføre endringer.");
				}
				else
				{
					alert("Svaret ditt har blitt slettet. Du vil ikke ha mulighet til å lagre endringene du utfører.");
				}*/
				new Element("div", {"class": "forum_reply_info"+(this.access ? " info_box" : " error_box"), "text": "Dette forumsvaret har blitt slettet."+(this.access ? '' : ' Du har ikke mulighet til å lagre endringene.')}).setOpacity(0).inject(this.reply_in_edit[reply_id][1], "top").fade("in");
			}
			else
			{
				var c = $("m_"+reply_id);
				if (c)
				{
					// fade ut og fjern
					c.get("morph").setOptions({"duration": 2000}).start({"opacity": 0, "height": 0, "margin-top": -5, "margin-bottom": -5, "border": 0, "padding": 0}).chain(
						function() { c.destroy(); }
					);
				}
			}
			this.id_list.erase(reply_id);
		};
		
		// sjekk for nye svar
		var rows = xml.getElementsByTagName("new")[0].getElementsByTagName("post");
		for (var i = 0; i < rows.length; i++)
		{
			var reply_id = rows[i].getAttribute("id");
			
			new Element("div").set("html", rows[i].firstChild.nodeValue).getFirst().setOpacity(0).inject(this.container).fade("in");
			this.id_list.include(reply_id);
			
			// legg til last_edit
			this.last_edit_list.set(reply_id, rows[i].getAttribute("last_edit"));
			this.last_id = reply_id;
		};
		
		// kontroller html
		this.check();
		
		// lagre sist tidspunkt
		this.time_last = xml.getElementsByTagName("topic")[0].getAttribute("time");
		
		// vis sist oppdatert tidspunkt
		var d = new Date($time()+window.servertime_offset);
		this.info_span.set("html", "Oppdatert " + str_pad(d.getHours()) + ":" + str_pad(d.getMinutes()) + ":" + str_pad(d.getSeconds()));
	},
	
	/** Mislykket */
	dataFailure: function(xhr)
	{
		// infoboks
		new Element("div", {"class": "forum_reply_info error_box", "html": forum_format_error(xhr.responseText)}).inject(this.container);
		
		// stopp timer
		this.abort = true;
		$clear(this.timer);
		
		// sett oppdateringsmelding
		this.info_span.set("text", "Oppdateringer avbrutt.");
	},
	
	/** Legg til infoboks */
	addInfo: function(html)
	{
		var elm = new Element("div", {"class": "forum_info", "html": html});
		elm.inject(this.container);
		return elm;
	},
	
	/** Vise svarboks */
	reply_form_show: function()
	{
		// skjul lenkene
		$$(".forum_link_replyform").each(function(elm)
		{
			elm.setStyle("display", "none");
		});
		
		// vis svarskjemaet
		var form = $("container_reply");
		form.setOpacity(0).setStyle("display", "block").fade("in");
		
		// scroll ned
		form.goto(-40);
		
		// sett fokus
		(function(){ form.getElement("textarea").focus(); }).delay(100);
	},
	
	/** Forhåndsvise svar */
	reply_preview: function(container, text, reply_id)
	{
		// eget ajax objekt for hver container
		if (!container.xhr)
		{
			container.xhr = new Request({
				"url": relative_path + "/ajax/forum/reply_preview",
				"data": { "topic_id": this.topic_id },
				"autoCancel": true
			});
			
			// forhåndsviser vi et redigert svar?
			if (reply_id) container.xhr.options.data["reply_id"] = reply_id;
			
			container.xhr.addEvents({
				// vellykket mottatt
				"success": function(text)
				{
					container
						.set("html", text)
						.check_html()
						.fade("in");
				},
				
				// mislykket
				"failure": function(xhr)
				{
					container
						.set("html", '<div class="forum_preview_info error_box">'+forum_format_error(xhr.responseText)+'</div>')
						.check_html()
						.fade("in");
				}
			});
		}
		
		// sett info
		var height = Math.max(0, container.getSize().y - 20);
		container.setOpacity(0).set("html", '<div class="forum_preview_info">Henter forhåndsvisning..</div>').fade(0.5);
		container.getElement("div").setStyle("min-height", height);
		
		// hent forhåndsvisning
		container.xhr.options.data["text"] = text;
		container.xhr.send();
	},
	
	/** Legg til svar */
	reply_add: function()
	{
		// opprett ajax objekt om vi ikke har det fra før
		if (!this.reply_xhr)
		{
			this.reply_container = new Element("div").inject($("reply_preview"), "before");
			this.reply_textarea = $("replyText");
			this.reply_xhr = new Request({
				"url": relative_path + "/ajax/forum/reply_add",
				"data": { "sid": User.s_id, "topic_id": this.topic_id },
				"autoCancel": true
			});
			
			var self = this;
			this.reply_xhr.addEvents({
				// vellykket mottatt
				"success": function(text)
				{
					// videresende?
					if (text.substring(0, 8) == "REDIRECT")
					{
						navigateTo(text.substring(9));
					}
					
					new Element("div", {"class": "forum_reply_info", "html": "<b>Svaret ble lagt til.</b>"}).inject(self.reply_container.empty().fade("in"));
				},
				
				// mislykket
				"failure": function(xhr)
				{
					new Element("div", {"class": "forum_reply_info error_box", "html": forum_format_error(xhr.responseText)}).inject(self.reply_container.empty().fade("in")).check_html();
				}
			});
		}
		
		// skjul evt. forhåndsvisning
		$("reply_preview").empty();
		
		// sett info
		new Element("div", {"class": "forum_reply_info", "html": "<b>Legger til svar..</b>"}).inject(this.reply_container.empty().setOpacity(0).fade(0.5));
		
		// legg til svaret
		this.reply_xhr.options.data["text"] = this.reply_textarea.get("value");
		this.reply_xhr.options.data["announce"] = $("announce") && $("announce").get("checked") ? 1 : 0;
		this.reply_xhr.options.data["no_concatenate"] = $("no_concatenate") && $("no_concatenate").get("checked") ? 1 : 0;
		this.reply_xhr.send();
	},
	
	/** Slett svar */
	reply_delete: function(reply_id)
	{
		// finn svaret
		var reply = $("m_" + reply_id);
		var self = this;
		
		// fant ikke?
		if (!reply) return;
		
		// bytt ut data
		var div = new Element("div", {"class": "forum_reply_info", "html": "<b>Sletter svar..</b>"})
			.setStyle("min-height", reply.getSize().y-20)
			.setOpacity(0)
			.replaces(reply)
			.fade("in");
		
		// sett opp xhr
		var xhr = new Request({
			"url": relative_path + "/ajax/forum/reply_delete",
			"data": {
				"sid": User.s_id,
				"topic_id": this.topic_id,
				"reply_id": reply_id
			}
		});
		
		xhr.addEvents({
			// vellykket
			"success": function(text)
			{
				// viser vi slettede svar?
				if (self.show_deleted)
				{
					// bytt ut med ny HTML
					new Element("div", {"html": text}).getFirst().setOpacity(0).replaces(div).fade("in");
					div.destroy();
					self.check();
				}
				
				else
				{
					(function()
					{
						div.get("morph").setOptions({"duration": 2000}).start({"opacity": 0, "min-height": 0, "height": 0, "margin-top": -5, "margin-bottom": -5, "padding-top": 0, "padding-bottom": 0}).chain(function(){ div.destroy(); });
					}).delay(1000);
					
					if (self.access)
					{
						div.setOpacity(0.5).set("html", 'Svaret ble slettet. <a href="#">Gjenopprett</a>').fade("in");
						div.getElement("a").addEvent("click", function(event)
						{
							event.stop();
							div.get("morph").cancel();
							self.reply_restore(reply_id, div);
						});
					}
					else
					{
						div.setOpacity(0.5).set("html", "Svaret ble slettet.").fade("in");
					}
					
					// fjern fra id-lista
					self.id_list.erase(reply_id);
				}
			},
			
			// mislykket
			"failure": function(xhr)
			{
				// vis feilmelding
				alert(forum_format_error(xhr.responseText), true);
				
				// bytt tilbake til forumsvaret
				reply.setOpacity(0.5).replaces(div).fade("in");
				div.destroy();
			}
		});
		
		// forsøk å slette
		xhr.send();
	},
	
	/** Gjenopprett svar */
	reply_restore: function(reply_id, obj)
	{
		// finn svaret
		if (obj) reply = obj;
		else var reply = $("m_" + reply_id);
		var self = this;
		
		// fant ikke?
		if (!reply) return;
		
		// bytt ut data
		var div = new Element("div", {"class": "forum_reply_info", "html": "<b>Gjenoppretter svar..</b>"})
			.setStyle("min-height", reply.getSize().y-20)
			.setOpacity(0)
			.replaces(reply)
			.fade("in");
		
		// sett opp xhr
		var xhr = new Request({
			"url": relative_path + "/ajax/forum/reply_restore",
			"data": {
				"sid": User.s_id,
				"topic_id": this.topic_id,
				"reply_id": reply_id
			}
		});
		
		xhr.addEvents({
			// vellykket
			"success": function(text)
			{
				// bytt ut med ny HTML
				new Element("div", {"html": text}).getFirst().setOpacity(0).replaces(div).fade("in");
				div.destroy();
				self.check();
				
				// sørg for at forumsvaret er i listen
				self.id_list.include(reply_id);
			},
			
			// mislykket
			"failure": function(xhr)
			{
				// vis feilmelding
				alert(forum_format_error(xhr.responseText, true));
				
				// bytt tilbake til forumsvaret
				reply.setOpacity(0.5).replaces(div).fade("in");
				div.destroy();
			}
		});
		
		// forsøk å gjenopprette
		xhr.send();
	},
	
	/** Annonser svar */
	reply_hidden: new Hash(),
	reply_announce: function(reply_id)
	{
		// finn svaret
		var reply = $("m_" + reply_id);
		var self = this;
		
		// fant ikke?
		if (!reply) return;
		
		// bytt ut data
		var div = new Element("div", {"class": "forum_reply_info", "html": "Annonserer svaret.."})
			.setStyle("min-height", reply.getSize().y-20)
			.setOpacity(0.5)
			.replaces(reply)
			.fade("in");
		
		// marker at svaret er "skjult" i tilfelle oppdateringer
		this.reply_hidden.include(reply_id, reply);
		
		// sett opp xhr
		var xhr = new Request({
			"url": relative_path + "/ajax/forum/reply_advertise",
			"data": {
				"sid": User.s_id,
				"topic_id": this.topic_id,
				"reply_id": reply_id
			}
		});
		
		xhr.addEvents({
			// vellykket
			"success": function(text)
			{
				alert(text);
				
				// fjern annonser knappen
				reply.getElement(".forum_link_reply_announce").destroy();
				
				// bytt tilbake til forumsvaret
				reply.setOpacity(0.5).replaces(div).fade("in");
				div.destroy();
				self.id_list.include(reply_id);
			},
			
			// mislykket
			"failure": function(xhr)
			{
				// vis feilmelding
				alert(forum_format_error(xhr.responseText, true));
				
				// bytt tilbake til forumsvaret
				reply.setOpacity(0.5).replaces(div).fade("in");
				div.destroy();
				self.id_list.include(reply_id);
			}
		});
		
		// forsøk å gjenopprette
		xhr.send();
	},
	
	/** Rediger svar */
	reply_in_edit: new Hash(),
	reply_edit: function(reply_id)
	{
		// finn svaret
		var reply = $("m_" + reply_id);
		var self = this;
		
		// fant ikke?
		if (!reply) return;
		
		// lag en container og fade ut/inn boksene
		var div = new Element("div", {"class": "forum_reply_edit_container"});
		div.setOpacity(0);
		reply.get("tween").start("opacity", 0).chain(function()
		{
			div.replaces(reply).fade("in");
			var elm = div.getElement("textarea");
			if (elm) (function(){ elm.focus(); }).delay(100);
		});
		
		// marker at svaret blir redigert i tilfelle oppdateringer
		this.reply_in_edit.include(reply_id, [reply, div]);
		
		// legg til rediger boks
		var div_edit = new Element("div", {"class": "forum_reply_edit_box"}).inject(div);
		
		// container for preview
		var preview_container = new Element("div").inject(div);
		
		// container for feil etc ved lagring
		var save_container = new Element("div").inject(div, "top");
		
		// lagre endringer
		var save_xhr = null;
		var save = function()
		{
			// sørg for xhr objekt
			if (!save_xhr)
			{
				save_xhr = new Request({
					"url": relative_path + "/ajax/forum/reply_edit",
					"data": {
						"sid": User.s_id,
						"reply_id": reply_id
					},
					"autoCancel": true
				});
				save_xhr.addEvents({
					// endringene ble lagret
					"success": function(text, xml)
					{
						preview_container.empty();
						self.reply_in_edit.erase(reply_id);
						
						// svar skal være i xml: <data><reply id last_edit>html
						var elm = xml.getElementsByTagName("reply")[0];
						reply = new Element("div", { "html": elm.firstChild.nodeValue }).getFirst().setOpacity(0);
						self.last_edit_list.set(reply_id, elm.getAttribute("last_edit"));
						
						// bruk abort funksjonen for å fade ut og vise nytt innhold
						abort();
					},
					
					// endringene kunne ikke lagres
					"failure": function(xhr)
					{
						var text = xhr.responseText;
						if (text.substring(0, 27) == "ERROR:REPLY-ALREADY-EDITED:")
						{
							self.last_edit_list.set(reply_id, text.substring(27));
							text = "Dette forumsvaret har blitt redigert av noen andre etter du begynte å redigere. Trykk lagre på nytt for å overstyre."
								+'<br /><a href="'+relative_path+'/forum/topic?id='+self.topic_id+'&amp;replyid='+reply_id+'" target="_blank">Vis forumsvaret i nytt vindu for å sammenlikne.</a>';
						}
						else text = forum_format_error(text);
						new Element("div", {"class": "forum_reply_info error_box", "html": text})
							.inject(save_container.empty().setOpacity(0).fade("in"));
						preview_container.empty();
					}
				});
			}
			
			// sett status
			preview_container
				.setOpacity(0)
				.set("html", '<div class="forum_preview_info"><b>Lagrer endringer..</b>')
				.fade("in");
			save_container.empty();
			
			// forsøk å lagre endringer
			save_xhr.options.data["last_edit"] = self.last_edit_list.get(reply_id);
			save_xhr.options.data["text"] = textarea.get("value");
			save_xhr.send();
		};
		
		// forhåndsvise endringer
		var preview = function()
		{
			self.reply_preview(preview_container, textarea.get("value"), reply_id);
			save_container.empty();
		}
		
		// avbryte endringer
		var abort = function()
		{
			// fade ut
			reply.get("tween").clearChain();
			var show_reply = function()
			{
				self.reply_in_edit.erase(reply_id);
				reply.fade("in");
				div.destroy();
			}
			if (reply.getOpacity() == 0)
			{
				div.get("tween").start("opacity", 0).chain(
					function()
					{
						reply.setOpacity(0);
						reply.replaces(div);
						self.check();
						show_reply();
					}
				);
			}
			else show_reply();
			
			// avbryt mulig xhr og fjern lagre og forhåndsvismulighetene
			if (save_xhr) save_xhr.cancel();
			if (preview_container.xhr) preview_container.xhr.cancel();
			save = preview = $empty;
			
			// sørg for at svaret er i ID-listen (slik at det blir oppdatert om nødvendig)
			self.id_list.include(reply_id);
		};
		
		// sett opp xhr
		var xhr = new Request({
			"url": relative_path + "/ajax/forum/reply_raw",
			"data": {
				"sid": User.s_id,
				"topic_id": this.topic_id,
				"reply_id": reply_id
			}
		});
		
		xhr.addEvents({
			// vellykket
			"success": function(text)
			{
				// sett opp skjema
				div_edit.set("html", '<div class="forum_reply_edit_h">Rediger forumsvar</div><div class="forum_reply_edit_c"><dl class="dd_right"><dt>Innhold</dt><dd><textarea></textarea></dd></dl><p class="c"><input type="button" class="button" value="Lagre endringer" /> <input type="button" class="button" value="Forhåndsvis" /> <input type="button" class="button" value="Avbryt" /></p></div>');
				textarea = div_edit.getElement("textarea").set("value", text);
				(function(){ textarea.focus(); }).delay(100);
				
				var buttons = div_edit.getElements("input");
				buttons[0].addEvent("click", function(event)
				{
					event.stop();
					save();
				});
				buttons[1].addEvent("click", function(event)
				{
					event.stop();
					preview();
				});
				buttons[2].addEvent("click", function(event)
				{
					event.stop();
					abort();
				});
			},
			
			// mislykket
			"failure": function(xhr)
			{
				// vis feilmelding
				alert(forum_format_error(xhr.responseText, true));
				
				// avbryt
				abort();
			}
		});
		
		div_edit.set("html", '<div class="forum_reply_info" style="margin-bottom: 0">Henter data..</div>');
		
		// hent data
		xhr.send();
	}
});