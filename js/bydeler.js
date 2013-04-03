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

/** Ressurs på bydelskart */
var BydelResource = new Class({
	Implements: Options,
	options: {
		container: null,
		image: '',
		image_alt: '',
		text: 'Ukjent',
		x: 0,
		y: 0,
		img_size: [0, 0],
		opacity: .5,
		opacity_over: .8,
		opacity_text: .5,
		url: null
	},
	initialize: function(container)
	{
		this.options.container = container;
		this.paint();
	},
	paint: function()
	{
		this.elm = new Element("div", {"class": "bydel_resource", "styles": {"left": this.options.x-this.options.img_size[0]/2, "top": this.options.y-this.options.img_size[1]/2}}).inject(this.options.container);
		this.a = new Element("a", {"opacity": this.options.opacity, "href": this.options.url}).inject(this.elm).addEvents({
			"mouseover": this.mouseenter.bind(this),
			"mouseout": this.mouseleave.bind(this),
			"click": this.click.bind(this)
		});
		this.img = new Element("img", {"src": this.options.image, "alt": this.options.image_alt}).inject(this.a);
		this.a.get("tween").setOptions({"duration": 150});
		
		this.text = new Element("div", {"opacity": this.options.opacity_text, "text": this.options.text}).inject(this.elm, "top").fade("hide");
		this.text.get("tween").setOptions({"duration": 150});
	},
	mouseenter: function()
	{
		this.a.fade(this.options.opacity_over);
		this.text.get("tween").set("opacity", this.options.opacity_text).start("opacity", 1);
	},
	mouseleave: function()
	{
		this.a.get("tween").cancel();
		this.a.setOpacity(this.options.opacity);
		this.text.get("tween").cancel().set("opacity", 0);
	},
	click: $lambda
});

/** FF på kart */
var BydelResourceFF = new Class({
	Extends: BydelResource,
	initialize: function(data, container, x, y)
	{
		this.setOptions({
			data: data,
			image: imgs_http+"/bydeler/familiepunkt.png",
			image_alt: "FF",
			text: data["ff_name"],
			x: data["br_pos_x"]-x,
			y: data["br_pos_y"]-y,
			img_size: [22, 22],
			url: relative_path + "/ff/?ff_id="+data["ff_id"]
		});
		this.parent(container);
	}
});

/** Ledig plass på kart */
var BydelResourceSelect = new Class({
	Extends: BydelResource,
	initialize: function(data, container, x, y, click_function)
	{
		this.setOptions({
			data: data,
			image: imgs_http+"/bydeler/freepunkt.png",
			image_alt: "Ledig plass",
			text: "Velg plass",
			x: data["br_pos_x"]-x,
			y: data["br_pos_y"]-y,
			img_size: [22, 22]
		});
		this.click = click_function;
		this.parent(container);
	}
});

