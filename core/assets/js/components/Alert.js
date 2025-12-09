class Alert extends PlutoElement {
	#unRemovable = false;
	static get props() {
		return {
			type: { type: String },
			dismissible: { type: Boolean },
			show: { type: Boolean },
		};
	}

	constructor() {
		super();
		this.type = "success";
		this.dismissible = true;
		this.show = true;
	}

	onDisconnect() {
		if (this.#unRemovable) {
			this.domLocation.parent.insertBefore(
				this,
				this.domLocation.parent.children[this.domLocation.index]
			);
		}
	}

	onPropUpdate(k, v) {
		if (k == "dismissible") {
			this.#unRemovable = !v;
		}
	}

	dismiss() {
		this.remove();
	}

	styles() {
		return ["/core/style/alert.css"];
	}

	render() {
		if (!this.show) {
			return html``;
		}
		const classes = ["alert", `alert-${this.type}`];
		return html`
			<div
				class=${classes.join(" ")}
				role="alert"
			>
				<slot></slot>
				${this.dismissible
					? html`<button
							type="button"
							class="close"
							@click="dismiss"
					  >
							<span>&times;</span>
					  </button>`
					: ""}
			</div>
		`;
	}
}

Pluto.assign("pluto-alert", Alert);
