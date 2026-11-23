function opticaPrintPopup(url, title) {
	if (!url || url === '#') return false;
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
		window.open(url, 'optica_ticket_' + Date.now(), 'width=440,height=680,scrollbars=yes,resizable=yes');
	}
	return false;
}

if (typeof jQuery !== 'undefined') {
	jQuery(document).on('click', 'a.optica-popup-link', function (e) {
		e.preventDefault();
		e.stopPropagation();
		var url = this.getAttribute('href');
		var title = this.getAttribute('data-title') || this.getAttribute('title') || jQuery(this).text() || 'Imprimir Ticket';
		return opticaPrintPopup(url, title);
	});
}
