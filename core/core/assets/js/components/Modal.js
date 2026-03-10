class Modal extends PlutoElement {
	static get props() {
		return {
			header: { type: String },
			open: { type: Boolean },
			size: { type: String },
			dismissable: { type: Boolean },
		};
	}

	constructor() {
		super();
		this.header = "";
		this.open = false;
		this.size = "sm";
		this.dismissable = true;
	}

	onPropUpdate(name, old, newVal) {
		if (name === "open") {
			if (newVal) {
				this.dispatch("open");
			} else {
				this.dispatch("close");
			}
		}
	}

	show() {
		this.open = true;
	}

	hide() {
		this.open = false;
	}

	_handleOverlayClick(e) {
		if (this.dismissable) {
			this.hide();
		}
	}

	stopPropagation(e) {
		e.stopPropagation();
	}

	styles() {
		return ["/core/style/modal.css"];
	}

	async render() {
		if (!this.open) {
			const modalElement = this.wrapper.querySelector(".modal");
			if (!modalElement) return html``;
			await new Promise((resolve) => {
				modalElement.style.animation = "slideDown 0.3s ease";
				const animationEndHandler = () => {
					modalElement.removeEventListener("animationend", animationEndHandler);

					resolve();
				};
				modalElement.addEventListener("animationend", animationEndHandler);
			});
			this.removeAttr("open");
			this.open = false;
			return html``;
		}
		this.setAttribute("open", "");
		if (this.size) {
			this.setAttribute(this.size, "");
		}

		return html`
			<div
				class="modal-overlay"
				@click="_handleOverlayClick"
			>
				<div
					class="modal"
					@click="stopPropagation"
				>
					<div class="modal-header">
						<h3 class="modal-title">${this.header}</h3>
						<button
							type="button"
							class="close"
							@click="hide"
						>
							<span>&times;</span>
						</button>
					</div>
					<div class="modal-body">
						<slot></slot>
					</div>
					<div class="modal-footer">
						<slot name="footer"></slot>
					</div>
				</div>
			</div>
		`;
	}
}

Pluto.assign("pluto-modal", Modal);
