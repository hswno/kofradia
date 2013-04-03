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

var main_server = document.domain.test('^kofradia\.no$');
var static_link = static_link || "/static";

/**
 * Legger til mulighet for å kjøre en bestemt event kun et bestemt antall ganger
 */
Native.implement([Event, Element, Window, Document], {
	addEventTimes: function(type, fn, times)
	{
		var self = this, times = parseInt(times) || 1, i = 0, new_fn = function()
		{
			fn.apply(null, arguments);
			if (++i >= times) self.removeEvent(type, arguments.callee);
		};
		this.addEvent(type, new_fn);
		return new_fn;
	}
});

Native.implement([Element, Document], {
	check_html: function()
	{
		check_html(this);
		return this;
	},
	find_report_links: function()
	{
		sm_scripts.find_report_links(this);
		return this;
	}
});

/**
 * Scrolle til et element i y-retning
 */
Element.implement({
	goto: function(offset, instant)
	{
		if (!offset) offset = 0;
		
		// direkte
		if (instant)
		{
			window.scrollTo(false, this.getPosition().y+offset);
		}
		
		// myk scroll
		else
		{
			window.scroll.start(false, this.getPosition().y+offset);
		}
		
		return this;
	}
});



// start HashListener 1.0
/*
---
description: A Class that provides a cross-browser history-management functionaility, using the browser hash to store the application's state

license: MIT-style

authors:
- Arieh Glazer
- Dave De Vos
- Digitarald

requires:
- core/1.2.4: [Class,Class.Extras,Element]

provides: [HashListener]

patched by Henrik Steen
...
*/
$extend(Element.NativeEvents, {
	hashchange: 1
});

var HashListener = new Class({
	Implements : [Options,Events],
	options : {
		blank_page : 'blank.html',
		start : false
	},
	iframe : null,
	currentHash : '',
	firstLoad : true,
	handle : false,
	useIframe : (Browser.Engine.trident && (typeof(document.documentMode)=='undefined' || document.documentMode < 8)),
	ignoreLocationChange : false,
	initialize : function(options){
		var $this=this;
		
		this.setOptions(options);
		
		// Disable Opera's fast back/forward navigation mode
		if (Browser.Engine.presto && window.history.navigationMode) {
			window.history.navigationMode = 'compatible';
		}
		
		// IE8 in IE7 mode defines window.onhashchange, but never fires it...
		if (('onhashchange' in window) && (typeof(document.documentMode) == 'undefined' || document.documentMode > 7)){
			// The HTML5 way of handling DHTML history...
			window.addEvent('hashchange' , function () {
				$this.handleChange($this.getHash());
			});
		} else  {
			if (this.useIframe){
				this.initializeHistoryIframe();
			} 
		} 
		
		window.addEvent('unload', function(event) {
			$this.firstLoad = null;
		});
		
		if (this.options.start) this.start();
	},
	initializeHistoryIframe : function(){
		var hash = this.getHash(), doc;
		this.iframe = new IFrame({
			src		: this.options.blank_page,
			styles	: { 
				'position'	: 'absolute',
				'top'		: 0,
				'left'		: 0,
				'width'		: '1px', 
				'height'	: '1px',
				'visibility': 'hidden'
			}
		}).inject(document.body);

		doc	= (this.iframe.contentDocument) ? this.iframe.contentDocument  : this.iframe.contentWindow.document;
		doc.open();
		doc.write('<html><body id="state">' + hash + '</body></html>');
		doc.close();
		return;
	},
	checkHash : function(){
		var hash = this.getHash(), ie_state, doc;
		if (this.ignoreLocationChange) {
			this.ignoreLocationChange = false;
			return;
		}

		if (this.useIframe){
			doc	= (this.iframe.contentDocument) ? this.iframe.contentDocumnet  : this.iframe.contentWindow.document;
			ie_state = doc.body.innerHTML;
			
			if (ie_state!=hash){
				this.setHash(ie_state);
				hash = ie_state;
			} 
		}		
		
		if (this.currentLocation == hash) {
			return;
		}
		
		this.currentLocation = hash;
		
		this.handleChange(hash);
	},
	setHash : function(newHash){
		window.location.hash = this.currentLocation = newHash;
		
		if (
			('onhashchange' in window) &&
			(typeof(document.documentMode) == 'undefined' || document.documentMode > 7)
		   ) return;
		
		this.handleChange(newHash);
	},
	getHash : function(){
		var m;
		if (Browser.Engine.gecko){
			m = /#(.*)$/.exec(window.location.href);
			return m && m[1] ? m[1] : '';
		}else if (Browser.Engine.webkit){
			return decodeURI(window.location.hash.substr(1));
		}else{
			return window.location.hash.substr(1);
		}
	},
	handleChange: function(newHash)
	{
		if (newHash == this.currentHash) return;
		this.currentHash = newHash;
		
		this.fireEvent('hashChanged',newHash);
		this.fireEvent('hash-changed',newHash);
	},
	setIframeHash: function(newHash) {
		var doc	= (this.iframe.contentDocument) ? this.iframe.contentDocumnet  : this.iframe.contentWindow.document;
		doc.open();
		doc.write('<html><body id="state">' + newHash + '</body></html>');
		doc.close();
		
	},
	updateHash : function (newHash){
		if ($type(document.id(newHash))) {
			this.debug_msg(
				"Exception: History locations can not have the same value as _any_ IDs that might be in the document,"
				+ " due to a bug in IE; please ask the developer to choose a history location that does not match any HTML"
				+ " IDs in this document. The following ID is already taken and cannot be a location: "
				+ newLocation
			);
		}
		
		this.ignoreLocationChange = true;
		
		if (this.useIframe) this.setIframeHash(newHash);
		else this.setHash(newHash);
	},
	start : function(){
		this.handle = this.checkHash.periodical(100, this);
	},
	stop : function(){
		$clear(this.handle);
	}
});
// end HashListener





/*
 * Hent bruker id og session id fra cookies
 * Sjekker om brukeren er logget inn
 */
User = {
	u_id: false,
	s_id: false
};


/*
 * INITIALIZE
 */
window.addEvent("domready", function()
{
	window.js_domready = (new Date).getTime();
	
	// sjekk brukerinfo
	var sm_id = Cookie.read(window.pcookie+"id");
	if (sm_id)
	{
		sm_id = sm_id.split(":");
		User.u_id = sm_id[1];
		User.s_id = sm_id[0];
	}
	
	// sjekk for inaktivitet - for å deaktivere en rekke automatiske funksjoner, evige redirects etc
	document.Idle = true;
	new IdleChecker(document);
	
	// finn elementer som skal scrolles til
	window.scroll = new Fx.Scroll(window, {duration: 250, transition: Fx.Transitions.Expo.easeOut});
	var scroll_to = $$(".scroll_here,#scroll_here").getLast();
	if (scroll_to)
	{
		// scroll til siste elementet
		scroll_to.goto(-15);
	}
	
	// finn ut tidsforskjell
	var offset = 0;
	if (serverTime)
	{
		offset = serverTime - $time() + (new Date).getTimezoneOffset()*60*1000;
	}
	
	// ta høyde for tid siden script start?
	if ($defined(js_start)) offset += $time()-js_start;
	
	// lagre offset som global
	window.servertime_offset = offset;
	
	// klokka
	var server_klokka = $("server_klokka");
	if (server_klokka) server_klokka = server_klokka.getFirst();
	if (server_klokka)
	{
		// sett info for brukeren
		var offset_t = (offset/1000).toFixed(1);
		if (offset_t == 0)
		{
			server_klokka.set("title", "Din klokke går helt identisk som Kofradia sin");
		}
		else if (offset_t > 0)
		{
			server_klokka.set("title", "Din klokke går ca. "+offset_t+" sekunder tregere enn Kofradia sin");
		}
		else
		{
			server_klokka.set("title", "Din klokke går ca. "+Math.abs(offset_t)+" sekunder kjappere enn Kofradia sin");
		}
		
		(function()
		{
			var d = new Date($time()+offset);
			server_klokka.set("html", Lang.weekdays.get(d.getDay()) + " " + d.getDate() + ". " + Lang.months.get(d.getMonth()) + " " + d.getFullYear() + " - " + str_pad(d.getHours()) + ":" + str_pad(d.getMinutes()) + ":" + str_pad(d.getSeconds()));
		}).periodical(1000);
	}
	
	// sjekk etter nedtellere, linker til profiler etc
	check_html(document);
	
	// logget inn?
	if (User.u_id)
	{
		// hurtigtast for å logge ut
		new KeySequence(["esc","esc","esc"], function()
		{
			this.last = [];
			if (confirm("Sikker på at du vil logge ut?"))
			{
				navigateTo(relative_path + "/loggut?sid="+User.s_id)
			}
		});
		
		window.theme_lock = window.theme_lock || false;
		
		// brukerinfo, innboks og hendelser?
		if ($("pm_new") && !theme_lock)
		{
			// brukerinfo
			window.addEvent("update_up_bydel", function(bydel)
			{
				this.set("html", bydel);
			}.bind($("status_bydel")));
			window.addEvent("update_up_cash", function(cash)
			{
				this.set("html", cash);
			}.bind($("status_cash")));
			window.addEvent("update_up_rankpos", function(rankpos)
			{
				this.set("html", rankpos);
			}.bind($("status_rankpos")));
			window.addEvent("update_upst", function(data)
			{
				// ingen endring?
				if (window.retrieve("upst_data") == data) return;
				window.store("upst_data", data);
				
				// up_health|up_energy|up_protection|up_rank|up_wanted
				data = data.split("|");
				function setit(id, value, text)
				{
					var e = $(id), s = e.getElements("span");
					s[3].setStyle("width", (value == "null" ? 0 : Math.min(100, value.toFloat().round(0)))+"%");
					s[1].set("html", (text ? text : (value == "null" ? 'Ingen' : format_number(value) + " %")));
					if (id == "upst_health" || id == "upst_energy" || id == "upst_protection")
					{
						// sett korrekt klasse for fargelegging
						var c = e.hasClass("levelwarn") ? "levelwarn" : (e.hasClass("levelcrit") ? "levelcrit" : "");
						if (value == "null") { if (c != "") e.removeClass(c); }
						else if (value < 20) { if (c != "levelcrit") { e.addClass("levelcrit"); if (c != "") e.removeClass(c); } }
						else if (value < 50) { if (c != "levelwarn") { e.addClass("levelwarn"); if (c != "") e.removeClass(c); } }
						else if (c != "") e.removeClass(c);
					}
					if (id == "upst_wanted") { if (value > 80) e.addClass("levelwarn"); else e.removeClass("levelwarn"); } 
				}
				setit("upst_health", data[0]);
				setit("upst_energy", data[1]);
				setit("upst_protection", data[2]);
				if ($("upst_rank"))
				{
					s = data[3].split(":");
					setit("upst_rank", s[0], format_number(s[1]));
					setit("upst_wanted", data[4]);
				}
			});
			
			// innboks
			var pm_new = $("pm_new");
			window.addEvents({
				"update_inbox_new": function(count)
				{
					this.empty();
					if (count == 0) return;
					var html = '<p class="notification_box"><a href="'+relative_path+'/innboks"><b>'+count+' '+(count == 1 ? 'ny' : 'nye')+'</b> '+(count == 1 ? 'melding' : 'meldinger')+'</a></p>';
					this.set("html", html);
				}.bind(pm_new)
			});
			
			// hendelser
			var log_new = $("log_new");
			window.addEvents({
				"update_up_log_new": function(count)
				{
					this.empty();
					if (count == 0) return;
					var html = '<p class="notification_box"><a href="'+relative_path+'/min_side?log"><b>'+count+' '+(count == 1 ? 'ny' : 'nye')+'</b> '+(count == 1 ? 'hendelse' : 'hendelser')+'</a></p>';
					this.set("html", html);
				}.bind(log_new)
			});
			
			// sett opp status boks
			window.addEvent("status_user_info", function(content)
			{
				if (content === true) return;
				
				this.empty();
				if (content)
				{
					new Element("div").addClass("status_box").set("html", content).inject(this);
				}
			}.bind($("status_info")));
			
			// pokerutfordringer
			(function()
			{
				var elm = $("poker_active");
				if (elm)
				{
					window.addEvent("update_pa", function(count)
					{
						this.set("html", count <= 0 ? '' : count);
					}.bind(elm));
				}
			})();
			
			// auksjoner
			(function()
			{
				var elm = $("auksjoner_active");
				if (elm)
				{
					window.addEvent("update_a", function(count)
					{
						this.set("html", count <= 0 ? '' : count);
					}.bind(elm));
				}
			})();
			
			// antall spillere i fengsel
			(function()
			{
				var elm = $("fengsel_count");
				if (elm)
				{
					window.addEvent("update_f", function(count)
					{
						this.set("html", count <= 0 ? '' : count);
					}.bind(elm));
				}
			})();
			
			// status for bruker
			new Status.User();
		}
		
		// fiks fokus ved cusearch
		(function()
		{
			var elm = $("cusearch");
			if (elm)
			{
				var t = false; // timer
				elm.getElements("input").addEvent("focus", function()
				{
					clearTimeout(t);
					elm.addClass("cusearch_hover");
				}).addEvent("blur", function()
				{
					t = elm.removeClass.delay(300, elm, "cusearch_hover");
				});
				
				new KeySequence("esc,F,esc", function()
				{
					elm.addClass("cusearch_hover");
					elm.getElement("input").focus();
				});
				
				// følg med nedover på siden
				var top = $("default_header_subline").getPosition().y;
				var f = false;
				window.addEvent("scroll", function()
				{
					if (window.getScroll().y > top)
					{
						if (!f) { elm.addClass("cusearch_fixed"); f = true; }
					} else {
						if (f) { elm.removeClass("cusearch_fixed"); f = false; }
					}
				});
				
				
			}
		})();
	}
	
	// google analytics
	if (main_server)
	{
		var gaJsHost = (("https:" == document.location.protocol) ? "https://ssl." : "http://www.");
		Asset.javascript(gaJsHost + "google-analytics.com/ga.js", {
			"onload": function()
			{
				if (!$defined(window.pageTracker))
				{
					window.pageTracker = _gat._getTracker("UA-1889723-1");
					window.pageTracker._initData();
					window.pageTracker._trackPageview();
				}
			}
		});
	}
	
	window.js_inited = (new Date).getTime();
	window.fireEvent("sm_domready");
	
	window.js_loaded = (new Date).getTime();
	
	var elm = $("js_time");
	if (elm)
	{
		elm.appendText(" - ").grab(new Element("span").setStyle("cursor", "pointer").appendText("HTML/JS: ca. " + ((window.js_loaded-window.js_start)/1000) + " sek").addEvent("click", time_debug));
	}
});


/*
 * Forskjellige funksjoner som utføres av de sidene som trenger det
 */
var sm_scripts = {
	/**
	 * Hent HistoryManager objekt
	 * @requires HashListener
	 * Syntaks i document.hash: #var1=data1;var2=data2
	 * I utgangspunktet kun ment for enkel data hvor ; og = ikke er inkludert i nøkkel/verdi
	 */
	"load_hm": function()
	{
		var HM = new Class({
			Extends: HashListener,
			initialize: function(options)
			{
				this.parent(options);
				this.addEvent("hashChanged", this.checkChange.bind(this));
				this.start();
			},
			hashlist: new Hash(),
			hashtmp: new Hash(),
			checkChange: function(hash)
			{
				if (hash.substring(0, 1) != "?") hash = "";
				var list = this.getHashObj(hash.substring(1));
				
				this.hashlist.each(function(value, key)
				{
					// fjernet?
					if (!list.has(key))
					{
						this.fireEvent(key+"-removed");
						this.hashlist.erase(key);
						return;
					}
					
					// oppdatert?
					var v = list.get(key);
					if (value != v)
					{
						this.hashlist.set(key, v);
						this.fireEvent(key+"-updated", v);
						this.fireEvent(key+"-changed", v);
					}
					
					list.erase(key);
				}.bind(this));
				
				list.each(function(value, key)
				{
					// lagt til
					this.hashlist.set(key, value);
					this.fireEvent(key+"-added", value);
					this.fireEvent(key+"-changed", value);
				}.bind(this));
				
				this.hashtmp = new Hash(this.hashlist);
			},
			getHashObj: function(string)
			{
				var list = new Hash();
				string.split(";").each(function(val)
				{
					if (val == "") return;
					var d = val.split("=", 2);
					if (!d[1]) d[1] = "";
					list.set(d[0], d[1]);
				});
				return list;
			},
			getString: function(hash)
			{
				var list = [];
				hash.each(function(value, key)
				{
					list.push(key+(value == "" ? "" : "=" + value));
				});
				return list.join(";");
			},
			set: function(key, value)
			{
				this.hashtmp.set(key, value);
				this.update();
			},
			remove: function(key)
			{
				this.hashtmp.erase(key);
				this.update();
			},
			update: function()
			{
				if (this.hashtmp.getLength() == 0) this.updateHash("");
				else this.updateHash("?"+this.getString(this.hashtmp));
			},
			recheck: function()
			{
				this.hashlist = new Hash();
				this.checkChange(this.getString(this.hashtmp));
			}
		});
		window.HM = new HM();
		
		this.load_hm = $empty;
	},
	
	/**
	 * Sjekk for rapporteringslenker
	 */
	"report_links": function()
	{
		// sørg for at klassen er opprettet
		this.init_report_links();
		
		window.addEvent("sm_domready", function()
		{
			sm_scripts.find_report_links(document.body);
		});
	},
	
	/**
	 * Sjekk etter rapporteringslenker
	 */
	"find_report_links": function(scope)
	{
		// finn rapporteringslenkene
		scope.getElements(".report_link").each(function(elm)
		{
			// allerede gått gjennom?
			if (elm.retrieve("ReportBox")) return;
			
			elm.store("ReportBox", new ReportBox(elm));
		});
	},
	
	/**
	 * Opprett rapporteringslink objektet
	 */
	"init_report_links": function()
	{
		/**
		 * Bokser for rapportering
		 */
		window.ReportBox = new Class({
			Extends: FBox,
			initialize: function(elm)
			{
				this.element = elm;
				
				// NAME,REF_ID,FLAG_PRIVATE
				this.data = this.element.get("rel").split(",");
				
				this.connect(this.element, false, true);
				this.overlay(true, 0.5);
				
				this.pos_x = this.pos_y = "center";
				this.rel_x = $("default_main");
				this.rel_y = this.element.getParent("div");
			},
			create_box: function()
			{
				var self = this;
				this.keysequence = new KeySequence(["esc"], function()
				{
					self.hide(true);
				});
				this.parent();
				this.boxw.setStyle("width", "280px");
				
				// finn riktig tittel
				var title = "Rapporter";
				switch (this.data[0])
				{
					case "pm": title += " privat melding"; break;
					case "ft": title += " forumtråd"; break;
					case "fr": title += " forumsvar"; break;
					case "signature": title += " signatur"; break;
					case "profile": title += " profil"; break;
				}
				
				// tittelen og melding
				this.status_box = new Element("div").inject(this.boxo, "top");
				new Element("h1").set("text", title).inject(this.boxo, "top");
				var obj = new Element("div");
				
				if (this.data[0] == "pm")
				{
					obj.set("html", '<p><b>Merk:</b> Ved å rapportere denne meldingen gir du moderatorene tilgang til denne meldingstråden.</p>');
				}
				
				if (this.data[2] == "1")
				{
					new Element("p").set("html", "Ingen andre brukere enn Crewet vil se at du utfører denne rapporteringen.").inject(obj);
				}
				
				// skjemaet
				var textarea = new Element("textarea", {"rows": 5, "styles": {"width": "95%"}});
				new Element("form").addEvent("submit", function(event)
				{
					event.stop();
					if (!this.xhr)
					{
						this.xhr = new Request({url: relative_path + "/ajax/report"});
						this.xhr.addEvents({
							"success": function(text)
							{
								self.status_box.empty();
								obj = new Element("div").set("html", text);
								new Element("p").grab(new Element("a", {"text": "Lukk"}).addEvent("click", function()
								{
									self.hide(true);
								})).inject(obj);
								self.populate(obj);
								self.element.set("text", "Rapportert nå");
								setTimeout(function(){self.hide(true);}, 5000);
							},
							"failure": function(xhr)
							{
								var val = xhr.responseText;
								if (val.substring(0, 6) == "ERROR:")
								{
									val = '<p>En feil oppsto: <b>'+val.substring(6)+'</b></p>';
								}
								self.status_box.set("html", '<div class="error_box">'+val+'</div>');
								textarea.focus();
							}
						});
					}
					
					self.status_box.set("html", '<div class="info_box"><p>Sender inn rapportering...</p></div>');
					this.xhr.send({"data": {"type": self.data[0], "note": textarea.value, "ref": self.data[1]}});
				})
				.grab(new Element("p").appendText("Begrunnelse:"))
				.grab(
					new Element("p").grab(textarea)
				)
				.grab(new Element("p", {"class": "r", "style": "line-height: 20px"})
					.grab(new Element("input", {"type": "submit", "class": "button", "style": "float: left", "value": "Rapporter"}))
					.appendText(" ")
					.grab(new Element("a", {"text": "Avbryt"}).addEvent("click", function()
					{
						self.hide(true);
					}))
					.appendText(" ")
					.grab(new Element("span", {"style": "color: #888888", "text": "[esc]"}))
				)
				.inject(obj);
				
				// legg til
				this.populate(obj);
				var focus = function(){setTimeout(function(){textarea.focus();}, 100);};
				this.element.addEvent("click", function()
				{
					focus();
				});
				focus();
			},
			show: function()
			{
				this.parent();
				this.keysequence.start();
				//this.boxw.get("tween").chain(function(){alert("chain show")});
			},
			hide: function(timer)
			{
				this.parent(timer);
				this.keysequence.stop();
			}
		});
		
		// hindre denne funksjonen å bli utført flere ganger
		this.init_report_links = $empty;
	},
	
	/**
	 * Pokerfunksjoner
	 */
	"poker_parse": function()
	{
		window.addEvent("sm_domready", function()
		{
			var Pokerkort = new Class({
				options: {padding: 2},
				initialize: function(element)
				{
					this.elm = $(element);
					
					// ikke en del av et skjema?
					if (this.elm.getParent().get("tag") != "label")
					{
						if (this.elm.hasClass("result"))
						{
							this.active();
							//this.elm.addClass("result");
						}
						return;
					}
					
					this.input = this.elm.getParent().getPrevious("input");
					this.state = false;
					
					new Element("a", {href:"#"}).addClass("spillekort_a").inject(this.elm.parentNode).wraps(this.elm).addEvents({
						"click": this.toggle.bind(this),
						"mouseover": this.active.bind(this),
						"mouseout": this.inactive.bind(this),
						"focus": this.active.bind(this),
						"blur": this.inactive.bind(this)
					});
					
					this.input.setStyle("display", "none");
					if (this.input.checked)
					{
						this.toggle(null, true);
					}
				},
				active: function(e)
				{
					if (this.state) return;
					this.elm
						.setStyle("width", this.elm.getStyle("width").toInt()+this.options.padding*2)
						.setStyle("height", this.elm.getStyle("height").toInt()+this.options.padding*2)
						.setStyle("padding", this.elm.getStyle("padding").toInt()-this.options.padding);
					this.state = true;
				},
				inactive: function()
				{
					if (!this.state || this.input.checked) return;
					this.elm
						.setStyle("width", this.elm.getStyle("width").toInt()-this.options.padding*2)
						.setStyle("height", this.elm.getStyle("height").toInt()-this.options.padding*2)
						.setStyle("padding", this.options.padding+this.elm.getStyle("padding").toInt());
					this.state = false;
				},
				toggle: function(e, force)
				{
					if (force === true)
					{
						this.elm.addClass("marked");
						this.input.checked = true;
						this.active();
					}
					else
					{
						this.elm.toggleClass("marked");
						this.input.checked = this.elm.hasClass("marked");
						e.stop();
					}
				}
			});
			
			// legg til kortfunksjoner på kortene
			$$(".spillekort").each(function(elm)
			{
				new Pokerkort(elm);
			});
		});
		
		// hindre denne funksjonen å bli utført flere ganger
		this.poker_parse = $empty;
	}
}


/**
 * For debugging: Finne ut hvor lang tid det tar å kjøre deler av javascriptet
 */
function time_debug()
{
	// js_start -> js_mootools_loaded -> js_default_loaded -> js_domready -> js_inited -> js_loaded
	alert(
		"js_mootools_loaded ("+(js_mootools_loaded-js_start)+" ms)\n" +
		"js_default_loaded ("+(js_default_loaded-js_mootools_loaded)+" ms)\n" +
		"js_domready ("+(js_domready-js_default_loaded)+" ms)\n" +
		"js_inited ("+(js_inited-js_domready)+" ms)\n" +
		"js_loaded ("+(js_loaded-js_inited)+" ms)\n" +
		"\n" +
		"Totalt: ca. "+(js_loaded-js_start)+" ms"
	);
}


/**
 * Sjekker for nedtellere, linker til profiler etc
 */
function check_html(dom)
{
	dom = $(dom) || document;
	
	// sjekk etter class="counter" nedtellere
	detect_counters(dom);
	
	// sjekk etter profillenker
	detect_playerinfo(dom);
	
	// sjekk etter [img]
	detect_bb_img(dom);
	
	// sjekk etter boxhandle (checkbox-er)
	boxHandleObj.locate(dom);
}

/**
 * Behandler [img]
 */
var BBImg = new Class({
	Implements: Events,
	ep: 0,
	handled: false,
	w: 0, h: 0, lwp: null,
	textspan: null,
	scaled: false,
	initialize: function(element)
	{
		if (element.get("tag") == "img")
		{
			element = new Element("span").wraps(element);
		}
		
		this.element = element;
		this.src = element.getElement("img").get("src");
		this.element.empty();
	},
	handle: function()
	{
		if (this.handled) return; this.handled = true;
		
		var p = this.element;
		while (p = p.getParent())
		{
			this.parent = p;
			if (this.parent.tagName == "A") this.parent.setStyle("text-decoration", "none");
			
			if (this.parent.getStyle("display") == "inline") continue;
			
			// sjekk om det faktisk er noe innhold
			var s = this.parent.getComputedSize();
			if (s.width - this.ep > 0) break;
			
			this.ep += s.totalWidth - s.width + this.parent.getComputedStyle("margin-left").toInt() + this.parent.getComputedStyle("margin-right").toInt();
		}
		
		var self = this;
		
		(function()
		{
			new Element("span", {text: "Laster bilde..", "class": "bb_image_loading"}).inject(self.element);
		}).delay(1);
		
		// opprett bildeobjekt
		this.img = new Asset.image(this.src, {
			onload: function()
			{
				self.insert();
				window.addEvent("resize", self.resize.bind(self));
			}
		});
		
	},
	insert: function()
	{
		this.w = this.img.get("width");
		this.h = this.img.get("height");
		this.element.empty();
		this.resize(true);
		this.fireEvent("load", this.element);
	},
	
	resize: function(is_first)
	{
		// finn ut om bildet må forminskes
		var wp = this.parent.getComputedSize()['width'] - this.ep;
		
		// samme størrelse på container som sist?
		if (!is_first && wp == this.lwp) return;
		
		// skaleres
		if (this.w > wp)
		{
			// er ikke skalert?
			if (!this.scaled && !is_first) this.element.empty();
			
			if (wp < 10) wp = 10;
			
			// må forminskes
			this.img.set("width", wp);
			this.img.set("height", this.h*wp/this.w);
			
			if (!this.scaled)
			{
				// opprett lenke for å putte rundt
				var l = new Element("a", {
					"href": this.src,
					"target": "_blank"
				});
				
				this.textspan = new Element("span", {
					"class": "bb_image_text",
					"style": "display: block"
				}).inject(this.element);
			}
			
			// oppdater tekst
			this.textspan.set("text", "Bildet er forminsket ("+Math.round(wp/this.w*100)+" %) - trykk for å se full størrelse ("+this.w+"x"+this.h+"px)");
			
			if (!this.scaled) this.img.inject(l.inject(this.element, "top"));
			this.scaled = true;
		}
		
		else
		{
			// var skalert?
			if (this.scaled)
			{
				this.element.empty();
			}
			
			if (this.scaled || is_first) this.img.inject(this.element);
			this.scaled = false;
		}
		
		this.lwp = wp;
	}
});

/**
 * Sjekk etter [img] BB-kode
 */
function detect_bb_img(dom)
{
	var elms = $(dom).getElements("span.bb_image, img.scale");
	elms.each(function(elm)
	{
		// allerede gått gjennom?
		if ($(elm).retrieve("BBImg"))
		{
			return;
		}
		
		elm.store("BBImg", new BBImg(elm));
	});
	elms.each(function(elm)
	{
		$(elm).retrieve("BBImg").handle();
	});
}


/*
 * Språkinnstillinger
 */
var LangBase = new Class({
	initialize: function(data)
	{
		this.data = data;
	},
	get: function(type, size, count)
	{
		var self = this;
		return $try(function()
		{
			return self.data[type][size || "full"][!$defined(count) || count == 1 ? 0 : 1];
		});
	}
});
var Lang = new LangBase({
	seconds: {
		full: ["sekund", "sekunder"],
		partial: ["sek", "sek"],
		short: ["s", "s"]
	},
	minutes: {
		full: ["minutt", "minutter"],
		partial: ["min", "min"],
		short: ["m", "m"]
	},
	hours: {
		full: ["time", "timer"],
		partial: ["time", "timer"],
		short: ["t", "t"]
	},
	days: {
		full: ["dag", "dager"],
		partial: ["dag", "dager"],
		short: ["d", "d"]
	},
	weeks: {
		full: ["uke", "uker"],
		partial: ["uke", "uker"],
		short: ["u", "u"]
	}
});
Lang.weekdays = new LangBase({
	"0": { full: ["søndag", "søndager"], short: ["søn", "søn"] },
	"1": { full: ["mandag", "mandager"], short: ["man", "man"] },
	"2": { full: ["tirsdag", "tirsdager"], short: ["tir", "tir"] },
	"3": { full: ["onsdag", "onsdager"], short: ["ons", "ons"] },
	"4": { full: ["torsdag", "torsdager"], short: ["tor", "tor"] },
	"5": { full: ["fredag", "fredager"], short: ["fre", "fre"] },
	"6": { full: ["lørdag", "lørdager"], short: ["lør", "lør"] }
});
Lang.months = new LangBase({
	"0": { full: ["januar", "januar"], short: ["jan", "jan"] },
	"1": { full: ["februar", "februar"], short: ["feb", "feb"] },
	"2": { full: ["mars", "mars"], short: ["mar", "mar"] },
	"3": { full: ["april", "april"], short: ["apr", "apr"] },
	"4": { full: ["mai", "mai"], short: ["mai", "mai"] },
	"5": { full: ["juni", "juni"], short: ["jun", "jun"] },
	"6": { full: ["juli", "juli"], short: ["jul", "jul"] },
	"7": { full: ["august", "august"], short: ["aug", "aug"] },
	"8": { full: ["september", "september",], short: ["sep", "sep"] },
	"9": { full: ["oktober", "oktober"], short: ["okt", "okt"] },
	"10": { full: ["november", "november"], short: ["nov", "nov"] },
	"11": { full: ["desember", "desember"], short: ["des", "des"] }
});


/*
 * Statusoppdateringer
 */
var Status = {};
Status.User = new Class({
	Implements: Options,
	
	// TODO: benytte webstorage der det er mulig, se http://dev.w3.org/html5/webstorage/
	options: {
		// hvor ofte fersk data skal hentes
		load_interval: 5000,
		
		// hvor ofte data skal sjekkes
		check_interval: 1000
	},
	
	initialize: function(options)
	{
		this.setOptions(options);
		this.load_last = $time();
		this.load = true;
		
		// for cookies
		this.cookie = new Hash.Cookie(window.pcookie+"user_status", {autoSave: false});
		
		// for ajax
		this.request = new Request({url: relative_path + "/ajax/my_info", data: {sid: User.s_id}, autoCancel: true});
		this.request.errors = 0;
		this.request.addEvents({
			// når data blir mottatt
			"success": function(text, xml)
			{
				this.request.errors = 0;
				this.setNotInactive();
				
				// les data
				var infos = {
					"inbox_new": parseInt(xmlGetValue(xml, "u_inbox_new")),
					"up_log_new": parseInt(xmlGetValue(xml, "up_log_new")),
					"up_bydel": xmlGetValue(xml, "up_bydel_name"),
					"up_cash": xmlGetValue(xml, "up_cash"),
					"up_rankpos": xmlGetValue(xml, "up_rank_position"),
					"upst": xmlGetValue(xml, "up_health")
						+ "|" + xmlGetValue(xml, "up_energy")
						+ "|" + xmlGetValue(xml, "up_protection")
						+ "|" + xmlGetValue(xml, "up_rank")
						+ "|" + xmlGetValue(xml, "up_wanted"),
					"pa": xmlGetValue(xml, "poker_active"),
					"a": xmlGetValue(xml, "auksjoner_active"),
					"f": xmlGetValue(xml, "fengsel_count")
				};
				
				// oppdater cookie
				var time = $time();
				this.cookie.set("time", time).set("u_id", User.u_id);
				this.cookie.extend(infos);
				this.cookie.save();
				this.load_last = time;
				
				// vis data
				this.loadFromCookie();
			}.bind(this),
			"failure": function(xhr)
			{
				// ikke lengre logget inn?
				if (xhr.responseText == "ERROR:SESSION-EXPIRE")
				{
					window.fireEvent("status_user_info", '<h2>Logget ut</h2><p class="c">Det ser ut som du ikke lengre er logget inn med denne brukeren.</p>');
					this.load = false;
				}
				else if (xhr.responseText == "ERROR:WRONG-SESSION-ID")
				{
					window.fireEvent("status_user_info", '<h2>Logget ut</h2><p class="c">Du er logget inn med en annen bruker.</p>');
					this.load = false;
				}
				else if (this.request.errors > 2) // 3. feilen
				{
					window.fireEvent("status_user_info", '<h2>Ukjent feil</h2><p class="c">Ukjent feil oppsto. Oppdatering av status har blitt deaktivert.</p>');
					this.stop();
				}
				else
				{
					this.request.errors++;
					this.request.send();
				}
				this.setAsInactive();
			}.bind(this)
		});
		
		// deaktiver oppdatering ved inaktivitet
		var self = this;
		document.addEvents({
			"active": function()
			{
				if (!self.timer) window.fireEvent("status_user_info", '<div class="c"><h2>Henter status</h2><p>Henter status..</p></div>');
				else if (self.load) window.fireEvent("status_user_info", null);
				self.start();
			},
			"idle": function()
			{
				self.stop();
				if (self.load)
				{
					window.fireEvent("status_user_info", "<div class='c'><h2>Statusoppdatering</h2><p>Oppdatering av status har blitt deaktivert på grunn av inaktivitet.</p><p>Beveg musen for å aktivere.</p></div>");
					self.setAsInactive();
				}
			}
		});
		
		this.start();
	},
	
	// oppdater informasjon fra cookie
	loadFromCookie: function()
	{
		this.last_load = this.cookie.get("time");
		
		var u_id = this.cookie.get("u_id");
		if (u_id != User.u_id)
		{
			window.fireEvent("status_user_info", "Du er ikke lengre logget inn med denne brukeren.");
			this.setAsInactive();
			this.load = false;
			return;
		}
		
		this.cookie.each(function(value, key)
		{
			if (key == "u_id" || key == "time") return;
			window.fireEvent("update_"+key, value);
		});
		
		this.setNotInactive();
		this.load = true;
	},
	
	// oppdater status
	updateStatus: function()
	{
		var time = $time();
		
		// hent nye cookies
		this.cookie.load();
		
		// sjekk for data i cookie fra annet vindu
		var time_cookie = parseInt(this.cookie.get("time"));
		if (time_cookie > this.load_last && time_cookie + this.options.load_interval >= time)
		{
			// avbryt xhr hvis kjører
			this.request.cancel();
			
			this.loadFromCookie();
			return;
		}
		
		// gått for lang tid siden vi hentet/forsøkte å hente data?
		if (this.load && time >= this.load_last + this.options.load_interval)
		{
			this.request.send();
			this.load_last = time;
		}
	},
	
	// start å hente data
	start: function()
	{
		if (this.timer) return;
		this.load_last = 0;
		this.updateStatus();
		this.timer = this.updateStatus.periodical(this.options.check_interval, this);
	},
	
	// stopp å hente data
	stop: function()
	{
		$clear(this.timer);
		this.request.cancel();
		this.timer = false;
	},
	
	setAsInactive: function()
	{
		document.body.addClass("status_user_inactive");
	},
	
	setNotInactive: function()
	{
		window.fireEvent("status_user_info", null);
		document.body.removeClass("status_user_inactive");
	}
});


/*
 * Sjekke etter inaktivitet hos brukeren
 * Kan brukes på alle DOM elementer. Bruker events for å gi beskjed videre ved inaktivet/aktivitet.
 */
var IdleChecker = new Class({
	options: {
		// hvor lang tid det skal gå før vi sjekker etter musebevegelse (aktivitet)
		time_check: 5000,
		
		/* FIXME */
		
		// hvor lang tid det skal gå før vi setter status til inaktiv (idle) etter at den har begynt å sjekke etter aktivitet
		time_idle: 54000 // time_check + this
	},
	
	initialize: function(dom)
	{
		this.dom = dom;
		this.timer = false;
		this.checking = false;
		
		if ($type(this.dom.Idle) != "boolean")
		{
			this.dom.Idle = false;
		}
		
		var self = this;
		this.bind = function(){self.activity();};
		
		// hvis den allerede er satt til Idle, legg til timer for å gi melding om Idle etter check+idle tid
		this.timer = setTimeout(function()
		{
			self.idle();
		}, this.options.time_idle+this.options.time_check);
		
		this.start();
	},
	
	activity: function()
	{
		this.dom.removeEvent("mousemove", this.bind).removeEvent("focus", this.bind).removeEvent("keydown", this.bind);
		
		// idle?
		if (this.dom.Idle)
		{
			this.dom.Idle = false;
			this.dom.fireEvent("active");
		}
		
		// har vi en timer
		if (this.timer)
		{
			$clear(this.timer);
			this.timer = false;
		}
		
		this.checking = false;
		this.start();
	},
	
	start: function()
	{
		// idle?
		if (this.dom.Idle || this.checking)
		{
			this.dom.addEvent("mousemove", this.bind).addEvent("focus", this.bind).addEvent("keydown", this.bind);
			
			if (!this.dom.Idle)
			{
				// hvis det ikke blir noe aktivitet innen bestemt tid, sett til inaktiv
				var self = this;
				this.timer = setTimeout(function()
				{
					self.idle();
				}, this.options.time_idle);
			}
			
			return;
		}
		
		// sjekk for inaktivitet
		this.checking = true;
		var self = this;
		setTimeout(function()
		{
			self.idle();
		}, this.options.time_check);
	},
	
	idle: function()
	{
		// sette til inaktiv?
		if (this.timer)
		{
			this.dom.Idle = true;
			this.dom.fireEvent("idle");
			this.timer = false;
			return;
		}
		
		this.start();
	}
});


/*
 * Nedteller for <* class="counter" rel="timeout[,(refresh|url)]"/> element.
 */
var Countdown = new Class({
	timesize: "full",
	initialize: function(element)
	{
		this.element = element;
		this.element.removeClass("counter").addClass("counters");
		
		this.params = this.element.get("rel").split(",");
		this.time = this.params[0].toInt();
		this.redirect = $defined(this.params[1]) ? (this.params[1] == "refresh" ? true : this.params[1]) : false;
		
		var self = this;
		if (this.time > 0) this.interval = setInterval(function(){ self.handleTimer(); }, 1000);
	},
	complete: $empty,
	handleTimer: function()
	{
		this.time--;
		this.element.set("html", timespan(this.time, this.timesize, false, true));
		
		// nådd 0?
		if (this.time == 0)
		{
			this.complete();
			
			// redirect?
			if (this.redirect)
			{
				navigateTo(this.redirect);
			}
			
			$clear(this.interval);
			delete this;
		}
	}
});
function detect_counters(dom)
{
	dom.getElements(".counter").each(function(elm)
	{
		// allerede gått gjennom?
		if ($(elm).retrieve("Countdown"))
		{
			return;
		}
		
		elm.store("Countdown", new Countdown(elm));
	});
}


/**
 * Nedteller for progressbar
 * Fullfører progressbaren til 100%
 */
var CountdownProgressbar = new Class({
	initialize: function(elm, now, total)
	{
		this.elm = elm;
		this.now = now;
		this.total = total;
		
		// start oppdatering
		var self = this;
		this.timer = setInterval(function(){ self.count(); }, 1000);
	},
	setNow: function(value)
	{
		this.now = value;
	},
	setTotal: function(value)
	{
		this.total = value;
	},
	count: function()
	{
		if (this.now >= this.total)
		{
			clearInterval(this.timer);
			return;
		}
		this.update();
	},
	update: function()
	{
		this.now++;
		this.elm.setStyle("width", this.now/this.total*100 + "%");
	}
})


/**
 * Nedteller for progressbar -- for tidspunkt (f.eks. 2 minutter og 52 sekunder gjenstår)
 */
var CountdownProgressbarTime = new Class({
	Extends: CountdownProgressbar,
	initialize: function(elm, now, total, prefix, suffix)
	{
		this.parent(elm, now, total);
		this.prefix = prefix ? prefix : '';
		this.suffix = suffix ? suffix : ' gjenstår';
		this.p = this.elm.getElement("p");
		this.l = this.total - this.now;
	},
	setNow: function(value)
	{
		this.parent(value);
		this.l = this.total - this.now;
	},
	setTotal: function(value)
	{
		this.parent(value);
		this.l = this.total - this.now;
	},
	update: function()
	{
		this.parent();
		this.l--;
		this.p.set("html", this.prefix + timespan(this.l, true, false, true) + this.suffix);
	}
});


/**
 * Utfør funksjoner bassert på kombinasjoner av tastetrykk
 * @param string/array sequence
 * @param function fn 
 * @todo capitals
 */
var KeySequence = new Class({
	initialize: function(sequence, fn)
	{
		this.last = [];
		this.setSequence(sequence);
		this.fn = fn;
		this.start();
	},
	setSequence: function(sequence, int)
	{
		if ($type(sequence) == "string")
		{
			sequence = sequence.split(",");
		}
		
		// konverter tekst til tastkode
		if (!int)
		{
			for (var i = 0; i < sequence.length; i++)
			{
				sequence[i] = Event.Keys.has(sequence[i]) ? Event.Keys[sequence[i]] : sequence[i].charCodeAt();
			}
		}
		
		this.sequence = sequence;
		if (this.last.length > this.sequence.length) this.last = this.last.slice(this.last.length-this.sequence.length, this.last.length);
	},
	start: function()
	{
		this.handleKeyBind = this.handleKeyBind || this.handleKey.bind(this);
		document.addEvent("keyup", this.handleKeyBind);
	},
	stop: function()
	{
		if (!this.handleKeyBind) return;
		document.removeEvent("keyup", this.handleKeyBind);
	},
	handleKey: function(event)
	{
		if (!event.code) return;
		this.last.push(event.code);
		if (this.last.length > this.sequence.length) this.last.shift();
		else if (this.last.length < this.sequence.length) return;
		
		// kontroller at dette er riktig sekvens
		for (var i = 0; i < this.sequence.length; i++)
		{
			// stemmer ikke?
			if (this.last[i] != this.sequence[i])
			{
				return;
			}
		}
		
		// riktig sekvens
		this.fn(event);
	}
});


/*
 * Legg til funksjon for å debugge tastetrykk
 */
new KeySequence("esc,D,E,B,U,G", function(event)
{
	if (this.debug)
	{
		document.removeEvent("keydown", this.debugfn);
		this.setSequence(this.debug.join(","), true);
		this.debug = false;
		return;
	}
	
	if (!this.debugfn)
	{
		this.debugfn = function(event)
		{
			if (event.key == "esc") return;
			alert("Du trykket en tast:\nTast: "+event.key+"\nKode: "+event.code+"\n\nTrykk ESC for å deaktivere.");
		}
	}
	
	document.addEvent("keydown", this.debugfn);
	this.debug = this.sequence;
	this.setSequence(["esc"]);
});


/**
 * Videresende til ny side
 * Hvis status er Idle, venter den på aktivitet
 * @param string src
 */
function navigateTo(src)
{
	src = !src || src === true ? document.location.href : src;
	
	var fn = function()
	{
		document.location = src;
	};
	
	// hvis idle, vent til aktiv før vi videresender
	if (document.Idle) document.addEventTimes("active", fn);
	else fn.delay(0);
	
	navigateTo = $empty;
}


/**
 * Floating boks (for f.eks. profilbokser)
 */
var FBox = new Class({
	options: {
		// opacity for boksen
		opacity: 0.95,
		
		// tid før boksen lukker seg automatisk
		delay: 300,
		
		// hvor lang tid den bruker på å fade ut
		duration: 250,
		
		// om overlay skal brukes (settes av this.overlay)
		overlay: false,
		
		// opacity for overlay boksen (settes av this.overlay)
		overlay_opacity: 0.75,
		
		// om boksen skal lukkes dersom overlay klikkes (settes av this.overlay)
		overlay_close: false
	},
	timer: false,
	
	/**
	 * Gjør at boksen lukker seg automatisk når man beveger musa utenfor
	 */
	autoclose: function(check)
	{
		if (check) return;
		
		// bytt ut funksjonen
		var self = this;
		this.autoclose = function()
		{
			self.boxw.addEvents({
				"mouseenter": function()
				{
					self.show();
				},
				"mouseleave": function()
				{
					self.hide();
				}
			});
			
			// hindre events i å bli lagt til flere ganger
			self.autoclose = $empty;
		};
		
		// finnes boksen? legg til events med en gang
		if (this.boxw)
		{
			this.autoclose();
		}
	},
	
	/**
	 * Legg til overlay rundt
	 * @param bool click_to_close - lukke boksen ved å trykke på overlay (utenfor boksen)
	 */
	overlay: function(click_to_close, opacity)
	{
		this.options.overlay = true;
		this.options.overlay_close = !!click_to_close;
		if (opacity) this.options.overlay_opacity = opacity;
	},
	
	/**
	 * Koble denne boksen til et element
	 * @param object elm
	 * @param bool show_hide - boksen blir automatisk synlig/skjult ved mus over/ut
	 * @param bool click - boksen blir kun synlig ved å klikke på elementet
	 */
	connect: function(elm, show_hide, click)
	{
		var self = this;
		
		// event for å klikke på boksen
		if (click)
		{
			elm.addEvent("click", function(event)
			{
				event.stop();
				
				// allerede synlig?
				if (self.boxw && self.boxw.getStyle("visibility") == "visible") return;
				
				self.show();
			});
		}
		
		// event for å vise/skjule boksen ved musa over/ut
		if (show_hide)
		{
			elm.addEvent("mouseenter", function()
			{
				if (!click || (self.boxw && self.boxw.getStyle("visibility") == "visible"))
				{
					self.show();
				}
			});
			elm.addEvent("mouseleave", function()
			{
				if (self.boxw && self.boxw.getStyle("visibility") == "visible")
				{
					self.hide();
				}
			});
		}
	},
	
	/**
	 * Vis (fade inn) boksen
	 */
	show: function()
	{
		// legge til overlay rundt boksen?
		if (this.options.overlay && !this.overlayobj)
		{
			var self = this;
			this.overlayobj = new Element("div", {"class": "bg_overlay", "styles": {"opacity": 0}, "tween": {"duration": this.options.duration}}).inject(document.body);
			if (this.options.overlay_close) this.overlayobj.addEvent("click", function() { self.hide(true); });
			this.overlayobj.fade(this.options.overlay_opacity);
		}
		
		$clear(this.timer);
		
		// kontrolller at boksen finnes
		if (!this.boxw)
		{
			this.create_box();
		}
		
		if (this.boxw.getStyle("opacity") == 0)
		{
			// flytt boksen til riktig posisjon
			this.move();
		}
		
		// fade inn
		this.boxw.setStyle("visibility", "visible");
		this.boxw.fade(this.options.opacity);
	},
	
	/**
	 * Skjul (fade ut) boksen
	 */
	hide: function(timer)
	{
		if (timer)
		{
			// fjerne overlay?
			if (this.overlayobj)
			{
				var ref = this.overlayobj;
				this.overlayobj.get("tween").chain(function(){ ref.destroy(); });
				this.overlayobj.fade("out");
				this.overlayobj = null;
			}
			
			// fjerne events?
			if (this.move_int)
			{
				window.removeEvent("resize", this.move_int);
				window.removeEvent("scroll", this.move_int);
			}
			
			this.boxw.fade("out");
			return;
		}
		
		var self = this;
		this.timer = setTimeout(function()
		{
			self.hide(true);
		}, this.options.delay);
	},
	
	/**
	 * Lag HTML for boksen
	 */
	create_box: function(box_elm)
	{
		if (this.box) return;
		this.box = box_elm ? box_elm : new Element("div");
		this.boxo = new Element("div", {"class": "js_box_b"}).grab(this.box);
		this.boxw = new Element("div", {"class": "js_box js_box_b", "styles": {"opacity": 0}, "tween": {"duration": this.options.duration}}).grab(new Element("div", {"class": "js_box_b"}).grab(this.boxo)).inject(document.body);
		
		// sjekk for event for å lukke boksen
		this.autoclose(true);
	},
	
	/**
	 * Flytt boksen
	 */
	pos_x: "center", // left, center, right
	pos_y: "center", // top, center, bottom
	rel_x: "window", // window, <element>
	rel_y: "window", // window, <element>, x
	offset_x: 0, // [width, center, neg, <integer>]
	offset_y: 0, // [height, center, neg, <integer>]
	outer: window, // ramme som boksen må være inni
	outer_space: [5, 5, 5, 5], // avstand fra kantene
	move: function()
	{
		// fjern gamle events
		if (this.move_int)
		{
			window.removeEvent("resize", this.move_int);
			window.removeEvent("scroll", this.move_int);
		}
		
		// funksjonen som tar seg av flyttingen
		// (for å kunne legge til som events)
		var self = this;
		this.move_int = function()
		{
			// hent variabler
			offset_x = $splat(self.offset_x);
			offset_y = $splat(self.offset_y);
			var size_box = self.boxw.getSize();
			
			// relativobjekt
			var rel_xo = (self.rel_x == "window" ? window : self.rel_x);
			var rel_yo = (self.rel_y == "window" ? window : (self.rel_y == "x" ? rel_xo : self.rel_y));
			
			// posisjonsobjekt for rel x/y
			var pos_xo = (self.rel_x == "window" ? {x:0,y:0} : self.rel_x.getPosition());
			var pos_yo = (self.rel_x == self.rel_y ? pos_xo : (self.rel_y == "x" ? pos_xo : (self.rel_y == "window" ? {x:0,y:0} : self.rel_y.getPosition())));
			
			// offset for rel x/y
			var size_x = (self.pos_x == "left" ? 0 : (self.pos_x == "center" ? rel_xo.getSize().x/2 - size_box.x/2 : rel_xo.getSize().x - size_box.x));
			var size_y = (self.pos_y == "top" ? 0 : (self.pos_y == "center" ? rel_yo.getSize().y/2 - size_box.y/2 : rel_yo.getSize().y - size_box.y));
			
			// finn x-verdien
			var x = pos_xo.x + size_x;
			var y = pos_yo.y + size_y;
			
			// sjekk scroll
			var scroll = (self.rel_x == "window" || self.rel_y == "window" ? window.getScroll() : false);
			if (self.rel_x == "window")
			{
				x += scroll.x;
			}
			if (self.rel_y == "window")
			{
				y += scroll.y;
			}
			
			// legg til offset
			var offset_x_val = 0;
			offset_x.each(function(item)
			{
				if (item == "width") offset_x_val += rel_xo.getSize().x;
				else if (item == "center") offset_x_val += rel_xo.getSize().x/2;
				else if (item == "neg") offset_x_val *= -1;
				else offset_x_val += item;
			});
			var offset_y_val = 0;
			offset_y.each(function(item)
			{
				if (item == "height") offset_y_val += rel_yo.getSize().y;
				else if (item == "center") offset_y_val += rel_yo.getSize().y/2;
				else if (item == "neg") offset_y_val *= -1;
				else offset_y_val += item;
			});
			
			x += offset_x_val;
			y += offset_y_val;
			
			outer_pos = self.outer.getPosition();
			outer_size = self.outer.getSize();
			
			// plasser boksen
			self.boxw.setStyles({
				"left": 0,
				"right": "auto",
				"top": Math.max(outer_pos.y+self.outer_space[0], y)
			});
			
			size_box = self.boxw.getSize();
			
			// sørg for at boksen ikke går utenfor høyre side
			var right = outer_pos.x + outer_size.x + self.outer.getScroll().x - self.outer_space[1];
			if (x + size_box.x > right)
			{
				// sett right verdi i stedet
				self.boxw.setStyles({
					"right": window.getSize().x-right,
					"left": "auto"
				});
			}
			
			else
			{
				self.boxw.setStyle("left", Math.max(self.outer.getPosition().x+self.outer_space[3], x));
			}
		};
		
		// legg til events (ikke resize i IE)
		if (!Browser.Engine.trident) window.addEvent("resize", this.move_int);
		if (this.rel_x == "window" || this.rel_y == "window" || true)
		{
			this.eventScroll = window.addEvent("scroll", this.move_int);
		}
		
		// utfør flytting
		this.move_int();
	},
	
	/**
	 * Oppdatere data
	 * @param string or element data
	 */
	populate: function(data)
	{
		if ($type(data) == "string") this.box.set("html", data);
		else this.box.empty().grab(data);
		
		// flytt boksen på nytt i tilfelle innholdet har strukket boksen
		if (this.move_int) this.move_int();// else this.move();
	}
});


/**
 * Bokser for brukerinformasjon
 */
var Playerinfo = new Class({
	Extends: FBox,
	initialize: function(elm)
	{
		this.element = elm;
		this.up_id = this.element.get("rel").toInt();
		this.state = false;
		this.script = false;
		
		this.pos_x = "left";
		this.pos_y = "top";
		this.rel_x = this.rel_y = this.element;
		this.offset_x = "center";
		this.offset_y = ["height", 3];
		
		this.connect(this.element, true, true);
		this.element.removeEvents("click");
		var self = this;
		this.element.addEvent("click", function(event)
		{
			// allerede synlig?
			if (self.boxw && self.boxw.getStyle("visibility") == "visible") return;
			
			event.stop();
			self.show();
			
			// hente ny data?
			if (!User.Statuses[self.up_id].xhr.running)
			{
				User.Statuses[self.up_id] = null;
				self.create_box();
			}
		});
		this.autoclose();
	},
	create_box: function()
	{
		this.parent();
		
		this.populate("<p>Henter informasjon...</p>");
		
		if (!User.Statuses) User.Statuses = {};
		var added = false;
		if (!User.Statuses[this.up_id])
		{
			added = true;
			var self = this;
			User.Statuses[this.up_id] = {
				"xhr": new Request({
					"url": relative_path + "/ajax/get_player_info",
					"autoCancel": true,
					"data": {
						"up_id": this.up_id, "html": 1
					},
					"evalScripts": function(script)
					{
						// lagre scripts til xhr objektet
						User.Statuses[self.up_id].xhr.script = script;
						//self.script = script;
					}
				})
			};
			User.Statuses[this.up_id].xhr.script = false;
			User.Statuses[this.up_id].xhr.addEvents({
				"success": function(text, xml)
				{
					this.dom = new Element("div").set("html", text);
					this.fireEvent("domready");
				},
				"failure": function()
				{
					// behandle data
					this.dom = new Element("p").appendText("Henting av informasjon mislyktes.");
					this.script = false;
					this.fireEvent("domready");
				}
			});
		}
		
		var self = this;
		var populate = function()
		{
			self.populate(User.Statuses[self.up_id].xhr.dom.cloneNode(User.Statuses[self.up_id].xhr.dom), User.Statuses[self.up_id].xhr.script);
		};
		User.Statuses[this.up_id].xhr.addEvent("domready", populate);
		
		if (!User.Statuses[this.up_id].xhr.running && !added)
		{
			populate();
		}
		
		if (added)
		{
			User.Statuses[this.up_id].xhr.send();
		}
	},
	populate: function(data, script)
	{
		this.parent(data);
		
		// for ajax scriptet for å fikse høyden på bildet
		if (script)
		{
			window.profile_box = this.box;
			$exec(script);
		}
	}
});
function detect_playerinfo(dom)
{
	// må være logget inn
	if (!User.u_id) return;
	
	$(dom).getElements("a.profile_link,span.profile_link").each(function(elm)
	{
		// allerede gått gjennom?
		if ($(elm).retrieve("Playerinfo"))
		{
			return;
		}
		
		elm.store("Playerinfo", new Playerinfo(elm));
	});
}


/*
 * Vis hvor mange uker, dager, timer, minutter sekundene går over
 */ 
function timespan(secs, longtype, nobold, noall)
{
	var size = longtype == "partial" ? "partial" : (longtype ? "full" : "short"), ret = [], ant, split = size == "short" ? "" : " ";

	// antall minutter
	if (secs > 59)
	{
		// antall timer
		if (secs > 3599)
		{
			// antall dager
			if (secs > 86399)
			{
				// antall uker
				if (secs > 604799)
				{
					ant = Math.floor(secs / 604800);
					ret.push((nobold ? ant : "<b>"+ant+"</b>") + split+Lang.get("weeks", size, ant));
					secs -= ant * 604800;
				}

				// dager
				ant = Math.floor(secs / 86400);
				if (!noall || ant != 0) ret.push((nobold ? ant : "<b>"+ant+"</b>") + split+Lang.get("days", size, ant));
				secs -= ant * 86400;
			}

			// timer
			ant = Math.floor(secs / 3600);
			if (!noall || ant != 0) ret.push((nobold ? ant : "<b>"+ant+"</b>") + split+Lang.get("hours", size, ant));
			secs -= ant * 3600;
		}

		// minutter
		ant = Math.floor(secs / 60);
		if (!noall || ant != 0) ret.push((nobold ? ant : "<b>"+ant+"</b>") + split+Lang.get("minutes", size, ant));
		secs -= ant * 60;
	}
	
	// antall sekunder
	if (ret.length == 0 || secs != 0) ret.push((nobold ? secs : "<b>"+secs+"</b>") + split+Lang.get("seconds", size, secs));
	
	// "og" før siste?
	var l = '';
	if (size == "full" && ret.length >= 2)
	{
		l = " og "+ret.pop();
	}
	
	//if (ret.length == 0) ret = ["Ingen"];
	return ret.join(" ")+l;
}


function str_pad(input, padlength, padchar, padtype)
{
	input = input.toString();
	if (!padtype || padtype != "left" || padtype != "right") padtype = "left";
	if (!padchar) padchar = "0";
	if (!padlength) padlength = 2;
	var padsize = padlength - input.length;
	if (padsize > 0)
	{
		var pad = "";
		for (var i = 0; i < padsize; i++)
		{
			pad += padchar;
		}
		if (padtype == "left")
		{
			input = pad + input;
		}
		else
		{
			input += pad;
		}
	}
	return input;
}


// hent xml verdier og attributter
// så slipper vi så mye kode andre steder :]
function xmlGetValue(xmldom, tagname, i, parentname, parenti)
{
	if (!i) i = 0; if (!parenti) parenti = 0;
	if (parentname) { try { var p = xmldom.getElementsByTagName(parentname)[parenti].getElementsByTagName(tagname)[i]; if (p.childNodes.length == 0) return ""; return p.firstChild.nodeValue } catch (e) { return false } }
	else { try { var p = xmldom.getElementsByTagName(tagname)[i]; if (p.childNodes.length == 0) return ""; return p.firstChild.nodeValue } catch (e) { return false } }
}
function xmlGetAttr(xmldom, tagname, attrname, i, parentname, parenti)
{
	if (!i) i = 0; if (!parenti) parenti = 0;
	if (parentname) { try { return xmldom.getElementsByTagName(parentname)[parenti].getElementsByTagName(tagname)[i].attributes.getNamedItem(attrname).value; } catch(e) { return false; } }
	else { try { return xmldom.getElementsByTagName(tagname)[i].attributes.getNamedItem(attrname).value; } catch(e) { return false; } }
}

// skjul alle elementer med et bestemt className
function hideClass(classname, tagname, root)
{
	if (!tagname) tagname = "";
	if (!root) root = document;
	root.getElements(tagname+"."+classname).each(function(elm)
	{
		elm.set("display", "none");
		elm.addClass("hide");
	});
}

// vis alle elementer med et bestemt className
function showClass(classname, tagname, root)
{
	if (!tagname) tagname = "";
	if (!root) root = document;
	root.getElements(tagname+"."+classname).each(function(elm)
	{
		elm.set("display", "");
		elm.removeClass("hide");
	});
}

// showClass/hideClass/abortEvent i en funksjon
function handleClass(show, hide, e, root)
{
	if (!root) root = document;
	$(root).getElements(show).each(function(elm){ elm.style.display = "block"; });
	$(root).getElements(hide).each(function(elm){ elm.style.display = "none"; });
	if (e) abortEvent(e);
}

// avbryte handling/event
function abortEvent(event)
{
	new Event(event).preventDefault();
}


// preloader - hente bilder før de vises
function preload(src)
{
	var img = new Image;
	img.src = src;
}


// ajax grensesnitt
var ajax = {
	js: "",
	parse_data: function(data)
	{
		// hent ut javascript
		var self = this;
		data.stripScripts(function(scripts, text)
		{
			data = text;
			self.js += scripts;
		});
		return data;
	},
	refresh: function(wrap)
	{
		if (!wrap) wrap = document;
		
		// utfør oppgaver som skal kjøres etter innholdet er lastet inn
		check_html(wrap);
		
		// kjør javascript
		$exec(this.js);
		this.js = "";
	},
	
	/** Formattere en feilmelding */
	format_error: function(data, no_html)
	{
		// ikke lenger logget inn?
		if (data == "ERROR:SESSION-EXPIRE" || data == "ERROR:WRONG-SESSION-ID") return "Du er ikke lenger logget inn.";
		
		// mangler noe data?
		if (data.substring(0, 13) == "ERROR:MISSING") return "Mangler data.";
		
		// fant ikke det man lette etter?
		if (data.substring(0, 9) == "ERROR:404")
		{
			var t = '';
			if (data.length > 10) t = " ("+data.substring(10)+")";
			return "Fant ikke det du søkte"+t+".";
		}
		
		// ikke tilgang?
		if (data.substring(0, 9) == "ERROR:403")
		{
			var t = '';
			if (data.length > 10) t = " ("+data.sustring(10)+")";
			return "Du har ikke tilgang"+t+".";
		}
		
		if (data.substring(0, 5) == "ERROR")
		{
			if (data.length > 6) return "Feil oppsto: "+data.substring(6);
			return "Ukjent feil oppsto.";
		}
		
		// ikke html?
		if (no_html) return new Element("div", {"html": data}).get("text");
		
		// returner opprinnelig data
		return data;
	}
}


// for å hindre at former blir utført flere ganger
function noSubmit(form)
{
	form.onsubmit = function() { return false; };
}


/**
 * Forhåndsvise BB-kode (bruker MooTools funksjoner)
 */
function preview(content, element_update)
{
	var elm = $(element_update);
	var xhr = elm.retrieve("xhr");
	if (!xhr)
	{
		xhr = new Request({
			"data": {
				"plain": true
			},
			"url": relative_path + "/ajax/bb",
			"evalScripts": function(js)
			{
				xhr.response.js = js;
			}
		});
		xhr.addEvent("success", function(text, dom)
		{
			if (text == "")
			{
				elm.set("html", "<p><b>Feil:</b> Ingen tekst å vise.</p>");
			}
			else
			{
				elm.empty();
				elm.set("html", text);
			}
			check_html(elm);
			$exec(xhr.response.js);
		});
		xhr.addEvent("failure", function()
		{
			elm.set("html", "<p><b>Feil:</b> Kunne ikke hente data. Prøv på nytt.</p>");
		});
		elm.store("xhr", xhr);
	}
	else
	{
		xhr.cancel();
	}
	xhr.options.data.text = content;
	xhr.send();
}

// for å forhåndsvise BB kode til et element
function preview_bb(event, data, hidden_ids, dst_id, preview_text)
{
	if (event) abortEvent(event);
	
	// vis skjulte elementer
	for (var i = 0; i < hidden_ids.length; i++)
	{
		$(hidden_ids[i]).style.display = "block";
	}
	
	// sett status
	var dst = $(dst_id);
	dst.innerHTML = !preview_text ? "Henter data.."  : preview_text;
	
	// ajax
	var xhr = new Request({
		"url": relative_path + "/ajax/bb",
		"data": { "text": data }
	});
	xhr.addEvents({
		"success": function(data, xml)
		{
			var text = xml.getElementsByTagName("content")[0];
			text = text.childNodes.length > 0 ? text.firstChild.nodeValue : '';
			text = ajax.parse_data(text);
			
			if (text == "") dst.set("text", "Mangler innhold.");
			else dst.set("html", text);
			
			ajax.refresh();
		},
		"failure": function(xhr)
		{
			dst.set("html", "Feil: "+xhr.responseText);
		}
	});
	xhr.send();
}


// forhåndsvise ting
function previewDL(event, elmid, dtid, ddid)
{
	preview_bb(event, $(elmid).value, [dtid, ddid], ddid);
	
	// sett fokus
	$(content).focus();
}

/**
 * Markeringsbokser (som rader)
 */
var boxHandleElms = {};
var boxHandleItem = new Class({
	initialize: function(wrap, box)
	{
		box.setStyle("display", "none");
		
		// deaktivert?
		this.disabled = false;
		if (box.get("disabled"))
		{
			this.disabled = true;
		}
		
		// legg til elementet
		this.name = box.get("rel") || box.get("name"); //.replace(new RegExp("^(.*)\\[.+?\\]$"), "$1[]");
		//this.multiple = !this.name.test("\\[\\]$");
		this.multiple = box.get("type") == "checkbox";
		boxHandleElms[this.name] = boxHandleElms[this.name] || [];
		boxHandleElms[this.name].push(this);
		
		this.wrap = wrap;
		this.box = box;
		this.elements = this.wrap.get("tag") == "tr" ? this.wrap.getChildren("td") : [this.wrap];
		
		// sjekk for mellomrom i cellen
		if (this.wrap.get("tag") == "tr" || this.wrap.get("tag") == "td")
		{
			//var height = 0;
			//console.log(this.wrap.getSize().y);
			//console.log(this.elements[0].getStyle("height") == "auto");
			//height = Math.max(this.wrap.getSize().y, this.elements[0].getStyle("height").toInt()+this.elements[0].getStyle("paddingTop").toInt())
			//console.log(height);
			if (this.wrap.getSize().y < 25 || this.elements[0].getStyle("height") == "auto") this.wrap.addClass("spacerfix");
			//console.log(this.elements[0].getStyle("minHeight"));
		}
		
		if (!this.disabled)
		{
			// sett pointer
			this.wrap.setStyle("cursor", "pointer");
			
			wrap.addEvent("mouseenter", this.mouseover.bind(this));
			wrap.addEvent("mouseleave", this.mouseout.bind(this));
			wrap.addEvent("click", this.click.bind(this));
		}
		
		this.hover = false;
		this.checked = this.box.get("checked");
		this.classname = null;
		this.showimg = !this.wrap.hasClass("box_handle_noimg");
		
		if (this.showimg)
		{
			this.elements[0].setStyles({
				"backgroundImage": 'url('+static_link+'/other/checkbox_'+(this.disabled ? 'disabled' : (this.checked ? 'yes' : 'no'))+'.gif)',
				"backgroundPosition": 'left center',
				"backgroundRepeat": 'no-repeat',
				"paddingLeft": '25px'
			});
		}
		
		if (!this.disabled) this.check();
	},
	mouseover: function()
	{
		this.hover = true;
		this.setBackground();
	},
	mouseout: function()
	{
		this.hover = false;
		this.setBackground();
	},
	click: function(event)
	{
		// klikket vi en link?
		if ($(event.target).get("tag") == "a")
		{
			return;
		}
		
		this.checked = !this.checked;
		
		// har vi noen andre elementer som må krysses ut?
		var self = this;
		if (this.checked && !this.multiple && boxHandleElms[this.name].length > 1)
		{
			boxHandleElms[this.name].each(function(obj)
			{
				if (!obj.checked || obj == self) return;
				obj.checked = false;
				obj.check();
			});
		}
		
		this.check();
	},
	check: function()
	{
		this.box.set("checked", this.checked);
		this.box.fireEvent((this.checked ? "" : "un")+"click");
		if (this.showimg)
		{
			this.elements[0].setStyle("backgroundImage", 'url('+static_link+'/other/checkbox_'+(this.checked ? 'yes' : 'no')+'.gif)');
		}
		this.setBackground();
	},
	setBackground: function()
	{
		// finn ut fargen
		var classname = this.checked ? (this.hover ? "box_handle_checked_hover" : "box_handle_checked") : (this.hover ? "box_handle_hover" : "box_handle_normal");
		if (classname != this.classname)
		{
			var self = this;
			this.elements.each(function(elm)
			{
				elm.removeClass(self.classname).addClass(classname);
			});
			
			this.classname = classname;
		}
		
		return;
			
		//var color = this.checked ? (this.hover ? this.colors[3] : this.colors[2]) : (this.hover ? this.colors[1] : this.colors[0]);
		
		this.elements.each(function(elm)
		{
			elm.setStyle("backgroundColor", color);
		});
	}
});
var boxHandleObj = {
	locate: function(element)
	{
		// finn alle bokswrappere
		$(element).getElements(".box_handle").each(function(wrap)
		{
			// finn boksen
			var box = wrap.getElement("input");
			
			// allerede gått gjennom denne?
			if (box.retrieve("boxHandle")) return;
			box.store("boxHandle", true);
			
			// legg til boksen
			new boxHandleItem(wrap, box);
		});
		
		// finn alle toogle linker
		$(element).getElements(".box_handle_toggle").each(function(elm)
		{
			// allerede gått gjennom denne?
			if (elm.retrieve("boxHandleOK")) return;
			elm.store("boxHandleOK", true);
			
			// finn navn
			var name = elm.get("rel");
			if (!name || !boxHandleElms[name] || !boxHandleElms[name][0].multiple) return;
			
			// legg til event
			elm.addEvent("click", function(event)
			{
				event.stop();
				
				//var checked = !boxHandleElms[name][0].checked;
				boxHandleElms[name].each(function(obj)
				{
					obj.checked = !obj.checked;
					//obj.checked = checked;
					obj.check();
				});
			});
		});
	}
};


/**
 * Vis skjulte elementer og skjul synlige elementer (selectors)
 * @param selector
 * @param event
 */
function toggle_display(selector, event, visiblestate)
{
	$$(selector).each(function(elm)
	{
		if (elm.getStyle("display") == "none")
		{
			var display = elm.retrieve("display") || visiblestate || "block";
			elm.setStyle("display", display);
		}
		else
		{
			elm.store("display", elm.getStyle("display"));
			elm.setStyle("display", "none");
		}
	});
	new Event(event).preventDefault();
}

/**
 * Formatter et nummer med vanlig komma
 */
function format_number(number, decimals, dec_sep, tho_sep)
{
	number = number + "";
	if (!dec_sep) dec_sep = ",";
	if (!tho_sep) tho_sep = " ";
	var neg = false;
	if (number.substring(0, 1) == "-") { neg = true; num = num.substring(1); }
	
	num = number.split(".", 2);
	if (tho_sep != "" && num[0].length > 3)
	{
		var l = num[0].length, n = parseInt(num[0].length / 3), s;
		for (var i = 0; i < n; i++)
		{
			s = l - 3 - 3*i;
			num[0] = num[0].substring(0, s) + tho_sep + num[0].substring(s);
		}
	}
	
	if (num[0] == "") num[0] = "0";
	if (decimals > 0 || (decimals == null && num[1]))
	{
		if (!num[1]) num[1] = "";
		if (decimals != null && num[1].length < decimals) num[1] = str_pad(num[1], decimals, "0", "right");
		num[0] += dec_sep + (decimals == null ? num[1] : num[1].substring(0, decimals));
	}
	
	return (neg ? "-" : '') + num[0];
}



// hent ajax html og plasser i html element
// fra ajax/global
function ajax_html(p, section, data, event, load_html, replace)
{
	p = $(p);
	var elm = new Element("div").set("html", load_html ? load_html : "<h2>Henter innhold</h2><p>Henter innhold...</p>");
	
	if (replace) p.empty().grab(elm);
	else p.grab(elm, "top");
	
	// hent ajax
	var req = new Request({
		"url": relative_path + "/ajax/global",
		"data": {
			"a1": section
		},
		"evalScripts": function(script)
		{
			ajax.js += script;
		}
	});
	if (data)
	{
		for (var key in data)
		{
			req.options.data[key] = data[key];
		}
	}
	
	req.addEvent("failure", function(xhr)
	{
		var elm = new Element("div");
		div.set("html", "<h2>Feil oppsto</h2><p>Noe gikk galt under henting av data.</p><p>Feilmelding: "+xhr.responseText+"</p>");
		p.inject(div, "top");
	});
	
	req.addEvent("success", function(text)
	{
		p.empty().grab(new Element("div").set("html", text));
		ajax.refresh();
	});
	
	// event
	if (event) new Event(event).preventDefault();
	
	// kjør ajax
	req.send();
}

/**
 * Hent ut data fra et skjema
 */
function get_form_data(form, event)
{
	var queryString = [];
	
	// submit knapp?
	if (event && event.target.type == "submit" && event.target.name)
	{
		queryString.push(event.target.name + '=' + encodeURIComponent(event.target.value));
	}
	
	$(form).getElements('input, select, textarea', true).each(function(el)
	{
		if (!el.name || el.disabled) return;
		if (el.tagName.toLowerCase() == "input" && el.type == "submit") return;
		var value = (el.tagName.toLowerCase() == 'select') ? Element.getSelected(el).map(function(opt){
			return opt.value;
		}) : ((el.type == 'radio' || el.type == 'checkbox') && !el.checked) ? null : el.value;
		$splat(value).each(function(val){
			if (typeof val != 'undefined') queryString.push(el.name + '=' + encodeURIComponent(val));
		});
	});
	return queryString.join('&');
}

/**
 * Gjør om slik at skjemaene på siden kjøres via ajax
 */
sm_scripts.ajax_forms = function()
{
	window.AjaxForm = new Class({
		initialize: function(form_element)
		{
			var self = this;
			this.form_element = form_element;
			this.form_element.addEvent("submit", this.submitForm.bind(this));
			this.form_element.getElements("input").each(function(elm)
			{
				elm.addEvent("click", self.submitForm.bind(self));
			});
		},
		submitForm: function(event)
		{
			// sett opp action
			var action = this.form_element.get("action");
			if (!action)
			{
				action = document.location.href;
			}
			
			// hent ut data
			var data = get_form_data(this.form_element, event);
			data += (data.length > 0 ? '&' : '') + "request_ajax=";
			
			// opprett ajax element
			var req = new Request({
				"url": action,
				"data": data,
				"evalScripts": function(script)
				{
					ajax.js += script;
				}
			});
			
			// events
			req.addEvent("failure", function(xhr)
			{
				alert("Feil oppsto\n\nNoe gikk galt under henting av data.\n\n.\n\nFeilmelding: "+xhr.responseText);
			});
			req.addEvent("success", function(text)
			{
				$("default_main").empty().grab(new Element("div").set("html", text));
				ajax.refresh();
			});
			
			// event
			if (event) new Event(event).preventDefault();
			
			// kjør ajax
			req.send();
		}
	});
	
	// domready event
	window.addEvent("sm_domready", function()
	{
		 sm_scripts.ajax_forms_init();
	});
	
	arguments.callee = $empty;
};
sm_scripts.ajax_forms_init = function()
{
	// finn alle skjemaene på siden
	$("default_main").getElements("form").each(function(element)
	{
		new AjaxForm(element);
	});
};


/**
 * Funksjoner for bydeler
 */
sm_scripts.bydeler = function()
{
	window.ByDel = new Class({
		Extends: FBox,
		initialize: function(bydel)
		{
			this.bydel = bydel;
			
			this.create_box();
			this.autoclose();
			this.populate($("map_info_"+this.bydel.id));
			
			var link = $("map_link_"+this.bydel.id).getElement("a");
			this.connect(link, false, true);
			this.overlay(true, 0.5);
			this.outer = $("map");
			this.rel_x = link; this.pos_x = "left"; this.offset_x = -40;
			this.rel_y = link; this.pos_y = "top"; this.offset_y = ["height", 10];
			
			// preload bildet med linja
			if (this.bydel.id != window.bydeler_current)
			{
				this.map_url = "bydeler?map="+window.bydeler.current_x+","+window.bydeler.current_y+","+this.bydel.bydel_x+","+this.bydel.bydel_y;
				preload(this.map_url);
			}
		},
		show: function()
		{
			this.parent();
			
			// vis linja til bydelen
			if (this.map_url)
			{
				$("map_bgline").setStyle("backgroundImage", "url("+this.map_url+")");
			}
		},
		hide: function(timer)
		{
			this.parent(timer);
			
			// fjern linja
			$("map_bgline").setStyle("backgroundImage", null);
		}
	});
	window.bydeler = {
		current: null,
		items: [],
		init: function()
		{
			this.items.each(function(item)
			{
				new ByDel(item);
			});
		}
	};
	window.addEvent("sm_domready", function()
	{
		bydeler.init();
	});
	arguments.callee = $empty;
}

var js_default_loaded = (new Date).getTime(); 