function opticaPrintPopup(url, title) {
	if (typeof jQuery !== 'undefined' && typeof jQuery.colorbox === 'function') {
		jQuery.colorbox({
			href: url,
			width: "40%",
			height: "90%",
			transition: "none",
			iframe: "true",
			title: title || "Imprimir Ticket"
		});
	} else {
		window.open(url, 'optica_ticket', 'width=440,height=680,scrollbars=yes,resizable=yes');
	}
	return false;
}
