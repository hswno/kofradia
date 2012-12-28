window.addEvent("domready", function() {
	// julekalender
	(function()
	{
		var elm = $("julekalender");
		if (!elm) return;

		elm.getElements(".jul_cell").each(function(x) {
			var t = false; // timer
			x.getElements("input").each(function(y) {
				y.addEvent("focus", function() {
					clearTimeout(t);
					x.addClass("hover");
				}).addEvent("blur", function() {
					t = x.removeClass.delay(300, x, "hover");
				});
			});
			if (x.hasClass("today")) {
				x.addEvent("click", function() {
					x.addClass("hover");
					x.getElement("input[type=text]").focus();
				})
			}
		});
	})();

	// sette fokus?
	var elm = $("julekalender").getElement(".hover");
	if (elm) {
		elm.getElement("input[type=text]").focus();
	}
});