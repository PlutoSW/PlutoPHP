class Dropdown extends PlutoElement {
	static get props() {
		return {
			label: { type: String },
			open: { type: Boolean },
		};
	}

	styles() {
		return ["/core/style/dropdown.css"];
	}

	constructor() {
		super();
		this.label = "";
		this.open = false;
		this._boundClickOutside = this._clickOutside.bind(this);
	}

	onConnect() {
		document.addEventListener("click", this._boundClickOutside);
	}

	onDisconnect() {
		document.removeEventListener("click", this._boundClickOutside);
	}

	_clickOutside(e) {
		if (!this.contains(e.target)) {
			this.open = false;
		}
	}

	toggle() {
		this.open = !this.open;
	}

	onPropUpdate(prop, oldValue, newValue) {
		if (prop === "open") {
			if (newValue) this.classList.add("open");
			else this.classList.remove("open");
		}
	}

	onPropCreate(prop, value) {
		if (prop === "open") {
			if (value) this.classList.add("open");
			else this.classList.remove("open");
		}
	}

	onReady() {
		[...this.children].forEach((element) => {
			element.addEventListener("click", (e) => {
				e.stopPropagation();
				this.open = false;
			});

			let attrs = [...element.attributes],
				event = "click",
				callback = null,
				args = {};
			attrs.forEach((attr) => {
				if (attr.name.startsWith("@")) {
					event = attr.name.slice(1);
					callback = attr.value;
					element.removeAttribute(attr.name);
				}
				if (attr.name.startsWith("arg-")) {
					let arg = attr.name.slice(4);
					if (arg === "raw") {
						if (!args[arg]) args[arg] = [];
						args[arg].push((args[arg] = this.parentElement.props));
					} else {
						args[arg] = this.parentElement[arg];
					}
					element.removeAttribute(attr.name);
				}
			});
			element.addEventListener(event, (e) => {
				e.stopPropagation();
				if (callback) {
					let functionBody;
					let arg = Object.keys(args).map((arg) => args[arg]);
					if (callback.includes("(")) {
						functionBody = `${callback}`;
					} else {
						functionBody = `${callback}('${arg}')`;
					}
					const func = new Function(functionBody);
					func.call();
				}
			});
		});
		this.getLabelSlot();
	}

	getLabelSlot() {
		let slot = this.querySelector("[slot='label']");
		if (slot) {
			slot.addEventListener("click", (e) => {
				e.stopPropagation();
				this.open = true;
			});
		}
	}

	render() {
		return html`
			${this.label
				? html`<button
						class="dropdown-toggle"
						@click="toggle"
				  >
						${this.label}
				  </button>`
				: html`<slot name="label"></slot>`}
			${this.open
				? html`
						<div class="dropdown-menu">
							<slot></slot>
						</div>
				  `
				: ""}
		`;
	}
}

Pluto.assign("pluto-dropdown", Dropdown);
