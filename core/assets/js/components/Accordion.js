class Accordion extends PlutoElement {
	onConnect() {
		const items = Array.from(this.children);
		items.forEach((item) => {
			if (item.tagName.toLowerCase() === "pluto-accordion-item") {
				item.addEventListener("toggle", (e) => {
					items.forEach((otherItem) => {
						if (otherItem !== e.target && otherItem.open) {
							otherItem.open = false;
						}
					});
				});
			}
		});
	}
	render() {
		return html`<div class="accordion"><slot></slot></div>`;
	}
}

class AccordionItem extends PlutoElement {
	static get props() {
		return {
			header: { type: String },
			open: { type: Boolean },
		};
	}

	constructor() {
		super();
		this.header = "";
		this.open = false;
	}

	onReady() {
		setTimeout(() => {
			this.setHeight(this.wrapper.querySelector(".accordion-collapse"));
		}, 400);
	}

	setHeight(element) {
		element.setAttribute("style", "--max-height:" + element.scrollHeight + "px");
	}

	_toggle() {
		this.open = !this.open;
		this.dispatchEvent(new CustomEvent("toggle", { bubbles: true, composed: true }));
	}

	onPropUpdate(prop, old, val) {
		if (prop === "open") {
			let ch = this.wrapper.querySelector(".accordion-collapse");
			if (!ch) return;
			if (val) {
				this.setHeight(ch);
			} else {
				ch.removeAttribute("style");
			}
		}
	}

	styles() {
		return ["/core/style/accordion.css"];
	}

	render() {
		return html`
			<div class="accordion-item">
				<button
					class=${"accordion-button " + (this.open ? "" : "collapsed")}
					@click="_toggle"
				>
					${this.header}
				</button>
				<div class=${"accordion-collapse " + (this.open ? "show" : "")}>
					<div class="accordion-body">
						<slot></slot>
					</div>
				</div>
			</div>
		`;
	}
}

Pluto.assign("pluto-accordion", Accordion);
Pluto.assign("pluto-accordion-item", AccordionItem);
