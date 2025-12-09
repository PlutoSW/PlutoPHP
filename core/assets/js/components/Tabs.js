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

			content: tab.innerHTML,
			isActive: tab.hasAttribute("active"),
		}));

		const initialActiveIndex = this._tabs.findIndex((tab) => tab.isActive);
		this.activeIndex = initialActiveIndex !== -1 ? initialActiveIndex : 0;

		while (this.firstChild) {
			this.removeChild(this.firstChild);
		}
	}

	_selectTab(index) {
		this.activeIndex = index;
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
				${unsafeHTML(this._tabs[this.activeIndex]?.content || "")}
			</div>
		`;
	}
}

Pluto.assign("pluto-tabs", Tabs);
