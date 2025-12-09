class PlutoForm extends PlutoElement {
	static get props() {
		return {
			action: { type: String },
			method: { type: String },
			_loading: { type: Boolean, private: true },
		};
	}

	constructor() {
		super();
		this.method = "POST";
		this._loading = false;
	}

	onReady() {
		this.form = this.wrapper.querySelector("form");
		if (!this.form) return;
		this.addEventListener("submit", (e) => {
			this.handleSubmit(e);
		});
	}

	async handleSubmit(e) {
		e.preventDefault();
		this._loading = true;

		const formProps = {};
		const elements = [...this.querySelectorAll("[name]")];
		let isFormValid = true;

		const validationResults = await Promise.all(
			elements.map((el) => (typeof el.validate === "function" ? el.validate() : true))
		);

		isFormValid = validationResults.every((isValid) => isValid);

		if (!isFormValid) {
			this._loading = false;
			console.warn("PlutoForm: Validation failed.");
			return;
		}

		elements.forEach((el) => {
			if (!el.name) return;
			if (
				el.tagName.toLowerCase() === "pluto-checkbox" ||
				el.tagName.toLowerCase() === "pluto-radio"
			) {
				if (el.checked || el.value) formProps[el.name] = el.value || el.checked;
			} else {
				formProps[el.name] = el.value;
			}
		});

		try {
			const response = await post(this.action, formProps);

			if (!response.status) {
				this.dispatchEvent(new CustomEvent("error", { detail: result }));
			} else {
				this.dispatchEvent(new CustomEvent("success", { detail: result }));
			}
		} catch (error) {
			this.dispatchEvent(new CustomEvent("error", { detail: error }));
		} finally {
			this._loading = false;
		}
	}

	styles() {
		return `form{display:flex; flex-direction:column;}`;
	}

	render() {
		return html`
			<form
				action=${this.action}
				method=${this.method}
			>
				<slot></slot>
			</form>
		`;
	}
}

Pluto.assign("pluto-form", PlutoForm);
