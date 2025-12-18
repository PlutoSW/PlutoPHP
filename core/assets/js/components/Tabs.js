class Tabs extends PlutoElement {
	static get props() {
		return {
			activeIndex: { type: Number },
			_tabs: { type: Array, private: true },
		};
	}

	styles() {
		return ["/core/style/tabs.css"];
	}

	constructor() {
		super();
		this.activeIndex = 0;
		this._tabs = [];
	}

	onConnect() {
		const tabElements = Array.from(this.querySelectorAll("pluto-tab"));
		this._tabs = tabElements.map((tab, index) => ({
			label: tab.getAttribute("label"),
			element: tab,
		}));

		const initialActiveIndex = this._tabs.findIndex((tab) =>
			tab.element.hasAttribute("active")
		);
		this.activeIndex = initialActiveIndex !== -1 ? initialActiveIndex : 0;
		this._selectTab(this.activeIndex);
	}

	_selectTab(index) {
		this.activeIndex = index;
		this._tabs.forEach((tab, i) => {
			if (i === index) {
				tab.element.setAttribute("active", "");
			} else {
				tab.element.removeAttribute("active");
			}
		});
	}

	render() {
		return html`
			<div class="tab-header">
				${this._tabs.map(
					(tab, index) => html`
						<button
							class="tab-button ${this.activeIndex === index ? "active" : "passive"}"
							@click=${() => this._selectTab(index)}
						>
							${tab.label}
						</button>
					`
				)}
			</div>
			<div class="tab-content">
				<slot></slot>
			</div>
		`;
	}
}

Pluto.assign("pluto-tabs", Tabs);
