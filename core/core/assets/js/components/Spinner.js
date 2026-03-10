class Spinner extends PlutoElement {
	static get props() {
		return {
			size: { type: String },
		};
	}

	constructor() {
		super();
		this.size = "";
	}

	onPropUpdate() {
		this.setAttribute("style", `--size:${this.size}px`);
	}

	render() {
		return html`<div class="spinner"></div>`;
	}
	styles() {
		return ["/core/style/spinner.css"];
	}
}

Pluto.assign("pluto-spinner", Spinner);
