class Card extends PlutoElement {
	static get props() {
		return {
			header: { type: String },
			footer: { type: String },
		};
	}

	constructor() {
		super();

	}

	render() {
		return html`
			<div class="card">
				${this.header
					? html`<div class="card-header">
							<h4>${this.header}</h4>
					  </div>`
					: ""}
				<div class="card-body">
					<slot></slot>
				</div>
				${this.footer ? html`<div class="card-footer">${this.footer}</div>` : ""}
			</div>
		`;
	}
	styles() {
		return ["/core/style/card.css"];
	}
}

Pluto.assign("pluto-card", Card);
