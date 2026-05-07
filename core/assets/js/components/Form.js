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
		this.addEventListener("submit", async (e) => {
			this._loading = true;

			this.data = {};
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
					if (el.checked || el.value) this.data[el.name] = el.value || el.checked;
				} else {
					this.data[el.name] = el.value;
				}
			});


			this.handleSubmit(e);
		});
	}

	async handleSubmit(e) {
		e.preventDefault();

		try {
			const response = await window[this.method.toLowerCase()](this.action, this.data);
			if (!response.status) {
				this.dispatch(new CustomEvent("error", { detail: response }));
			} else {
				this.dispatch(new CustomEvent("success", { detail: response }));
			}
		} catch (error) {
			this.dispatch(new CustomEvent("error", { detail: error }));
		} finally {
			this._loading = false;
		}
	}

	styles() {
		return ['/core/style/layout/layout.css'];
	}

	reset(){
		this.form.reset();
		this.querySelectorAll("[name]").forEach((el) =>{
			el.value = "";
			el.checked = false;
			el.classList.remove("is-valid");
			el.classList.remove("is-invalid");
			el.error = '';
		})
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
