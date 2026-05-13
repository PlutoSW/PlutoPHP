class PlutoForm extends PlutoElement {
	static get props() {
		return {
			action: { type: String },
			method: { type: String },
			enctype: { type: String },
			_loading: { type: Boolean, private: true },
		};
	}

	constructor() {
		super();
		this.method = "POST";
		this.files = {};
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
				elements.map((el) => (typeof el.validate === "function" ? el.validate() : true)),
			);

			isFormValid = validationResults.every((isValid) => isValid);

			if (!isFormValid) {
				this._loading = false;
				return;
			}

			elements.forEach((el) => {
				if (!el.name) return;
				if (
					el.tagName.toLowerCase() === "pluto-checkbox" ||
					el.tagName.toLowerCase() === "pluto-radio"
				) {
					if (el.checked || el.value) this.data[el.name] = el.value || el.checked;
				} else if (el.tagName.toLowerCase() === "pluto-file-input") {
					if (el.files.length > 0) {
						this.files[el.name] = el.files;
					}
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

			if (this.beforeSubmit) {
				const result = await this.beforeSubmit();

				if (result === false) {
					this._loading = false;
					return;
				}
			}
			var formData;
			if (Object.keys(this.files).length > 0) {
				formData = new FormData();
				Object.entries(this.data).forEach(([key, value]) => {
					formData.append(key, value);
				});
				Object.keys(this.files).forEach((key) => {
					const fileList = this.files[key];
					if (!fileList || fileList.length === 0) return;
					if (fileList.length === 1) {
						formData.append(key, fileList[0]);
						return;
					} else {
						Array.from(fileList).forEach((file, index) => {
							formData.append(`${key}[${index}]`, file);
						});
					}
				});
			} else {
				formData = this.data;
			}
			const response = await window[this.method.toLowerCase()](this.action, formData);
			if (!response.status) {
				this.dispatch(new CustomEvent("error", { detail: response }));
			} else {
				this.dispatch(new CustomEvent("success", { detail: response }));
			}
		} catch (error) {
			console.error("PlutoForm submission error:", error);
			this.dispatch(new CustomEvent("error", { detail: error }));
		} finally {
			this._loading = false;
		}
	}

	styles() {
		return ["/core/style/layout/layout.css"];
	}

	reset() {
		this.form.reset();
		this.querySelectorAll("[name]").forEach((el) => {
			el.value = "";
			el.checked = false;
			el.classList.remove("is-valid");
			el.classList.remove("is-invalid");
			el.error = "";
		});
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
