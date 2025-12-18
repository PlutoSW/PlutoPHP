class PlutoButton extends PlutoElement {
	constructor() {
		super();
	}

	styles() {
		return ["/core/style/layout/layout.css"];
	}

	render() {
		return html`<slot></slot>`;
	}
}

class PlutoSubmit extends PlutoElement {
	constructor() {
		super();
		this.addEventListener("click", (e) => {
			e.preventDefault();
			const form = this.closest("pluto-form");
			if (!form) return;
			form.dispatchEvent(new Event("submit"));
		});
	}

	styles() {
		return ["/core/style/layout/layout.css"];
	}

	render() {
		return html`<slot></slot>`;
	}
}

Pluto.assign("pluto-button", PlutoButton);
Pluto.assign("pluto-submit", PlutoSubmit);
