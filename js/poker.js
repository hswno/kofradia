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

/*
 * JavaScript-fil for poker funksjonen
 * Henrik Steen
 */

var poker = {
	init: function() {
		poker.is_over = false;
		
		// farger
		poker.c_normal = "#488C9F";
		poker.c_normal_hover = "#81B7C7";
		poker.c_clicked = "#183138";
		poker.c_clicked_hover = "#183138";
		
		// legg til events på kort boksene
		for (var i = 0; i < 5; i++) {
			poker.addCheckboxEvent(i, "mouseover", "mouseover");
			poker.addCheckboxEvent(i, "mouseout", "mouseout");
			poker.addCheckboxEvent(i, "click", "select");
			
			// sjul checkbox (dette er for at folk uten javascript også skal kunne bruke pokerfunksjonen)
			poker.checkbox(i).style.display = "none";
		}
	},
	
	addCheckboxEvent: function(i, type, func) {
		var elm = poker.checkbox(i).parentNode;
		$(elm).addEvent(type, function(e) { eval("poker."+func+"(elm)") });
	},
	
	mouseover: function(elm) {
		poker.is_over = true;
		var id = poker.bilde_id(elm);
		
		if (poker.checkbox(id).checked) {
			elm.style.backgroundColor = poker.c_clicked_hover;
		} else {
			elm.style.backgroundColor = poker.c_normal_hover;
		}
	},
	
	mouseout: function(elm) {
		poker.is_over = false;
		var id = poker.bilde_id(elm);
		
		if (poker.checkbox(id).checked) {
			elm.style.backgroundColor = poker.c_clicked;
		} else {
			elm.style.backgroundColor = poker.c_normal;
		}
	},
	
	select: function(elm) {
		var id = poker.bilde_id(elm);
		poker.checkbox(id).checked = !poker.checkbox(id).checked;
		if (poker.checkbox(id).checked) {
			elm.style.backgroundColor = poker.is_over ? poker.c_clicked_hover : poker.c_clicked;
		} else {
			elm.style.backgroundColor = poker.is_over ? poker.c_normal_hover : poker.c_normal;
		}
	},
	
	bilde_id: function(divobj) {
		return divobj.getElementsByTagName("input")[0].name.charAt(5);
	},
	
	checkbox: function(id) {
		return document.getElementsByName("kort["+id+"]")[0];
	}
}