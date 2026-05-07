class Toast extends PlutoElement {
	constructor() {
		super();
		this.onclick = this.hide;
	}

	onConnect() {
		let autoHide = this.attr("auto-hide");
		if (autoHide !== "false") {
			this.autoHide = autoHide || 3;
		}
	}

	show() {
		this.attr("active", "");
		if (this.autoHide) {
			setTimeout(() => {
				this.hide();
			}, parseInt(this.autoHide) * 1000);
		}
	}

	hide() {
		this.removeAttr("active");
	}

	render() {
		return html`<slot></slot>`;
	}
}

Pluto.assign("pluto-toast", Toast);
