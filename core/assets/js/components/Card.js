class Card extends PlutoElement {
	static get props() {
		return {
			header: { type: String },
			footer: { type: String },
		};
	}

	constructor() {
		super();
		this._headerContent = null;
		this._footerContent = null;
	}

	onConnect() {
		if (this.hasAttribute("header")) {
			this._headerContent = this.header;
		} else {
			const slottedHeader = this.querySelector("header:not([slot])");
			if (slottedHeader) {
				this._headerContent = unsafeHTML(slottedHeader.innerHTML);
				slottedHeader.remove();
			}
		}

		if (this.hasAttribute("footer")) {
			this._footerContent = this.footer;
		} else {
			const slottedFooter = this.querySelector("footer:not([slot])");
			if (slottedFooter) {
				this._footerContent = unsafeHTML(slottedFooter.innerHTML);
				slottedFooter.remove();
			}
		}
	}

	render() {
		const headerToRender =
			typeof this._headerContent === "string"
				? html`<h4>${this._headerContent}</h4>`
				: this._headerContent;
		const footerToRender =
			typeof this._footerContent === "string"
				? html`<span>${this._footerContent}</span>`
				: this._footerContent;
		return html`
			<div class="card">
				${this._headerContent ? html`<div class="card-header">${headerToRender}</div>` : ""}
				<div class="card-body">
					<slot></slot>
				</div>
				${this._footerContent ? html`<div class="card-footer">${footerToRender}</div>` : ""}
			</div>
		`;
	}
	styles() {
		return ["/core/style/card.css"];
	}
}

Pluto.assign("pluto-card", Card);
