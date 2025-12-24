class PlutoInput extends PlutoElement {
	static get props() {
		return {
			label: { type: String },
			name: { type: String },
			type: { type: String },
			required: { type: Boolean },
			error: { type: String },
			pattern: { type: String },
			minlength: { type: Number },
			maxlength: { type: Number },
			"error-required": { type: String },
			placeholder: { type: String },
			formatter: { type: Function },
			step: { type: Number },
			min: { type: String },
			max: { type: String },
		};
	}

	constructor() {
		super();
		this.type = "text";
	}

	get value() {
		return this.wrapper?.querySelector("input")?.value || "";
	}

	set value(newValue) {
		const input = this.input || this.wrapper?.querySelector("input");
		if (input && input.value !== newValue) {
			input.value = newValue;

			this.dispatch(new Event("input", { bubbles: true, composed: true }));
		}
	}

	onReady() {
		this.input = this.wrapper.querySelector("input");
		if (this.step) {
			this.input.step = this.step;
		}
		if (this.min) {
			this.input.min = this.min;
		}
		if (this.max) {
			this.input.max = this.max;
		}
	}

	validate() {
		const input = this.wrapper.querySelector("input");

		if (!input) return true;

		const isValid = input.checkValidity();

		if (isValid) {
			this.error = "";
			this.classList.remove("is-invalid");
		} else {
			if (input.validity.valueMissing) {
				this.error = this.getAttribute("error-required") || __("validation.required");
			} else if (input.validity.typeMismatch) {
				this.error = __("validation.type_mismatch", { type: this.type });
			} else if (input.validity.tooShort) {
				this.error = __("validation.minlength", { min: this.minlength });
			} else if (input.validity.patternMismatch) {
				this.error =
					this.getAttribute("error-pattern") || __("validation.pattern_mismatch");
			} else {
				this.error = input.validationMessage;
			}
			this.classList.add("is-invalid");
		}

		return isValid;
	}

	styles() {
		return ["core/style/layout/layout.css"];
	}

	render() {
		return html`
			<div class="form-group">
				${this.label ? html`<label for=${this.name}>${this.label}</label>` : ""}
				<input
					@input=${(e) => {
						const input = e.target;
						const originalValue = input.value;

						if (this.formatter && typeof this.formatter === "function") {
							const originalSelectionStart = input.selectionStart;
							const formattedValue = this.formatter(originalValue);

							this.value = formattedValue;

							Promise.resolve().then(() => {
								const diff = formattedValue.length - originalValue.length;
								input.selectionStart = originalSelectionStart + diff;
								input.selectionEnd = originalSelectionStart + diff;
							});
						} else {
							this.value = originalValue;
						}
						this.validate();
					}}
					?required=${this.required}
					type=${this.type}
					name=${this.name}
					id=${this.name}
					class="input"
					value=${this.getAttribute("value") || ""}
					pattern=${this.pattern || null}
					minlength=${this.minlength}
					maxlength=${this.maxlength}
					placeholder=${this.placeholder || ""}
					step=${this.step}
					min=${this.min}
					max=${this.max}
				/>
				${this.error ? html`<div class="error-message">${this.error}</div>` : ""}
			</div>
		`;
	}
}

class PlutoTextarea extends PlutoElement {
	static get props() {
		return {
			label: { type: String },
			name: { type: String },
			type: { type: String },
			required: { type: Boolean },
			error: { type: String },
			rows: { type: Number },
			cols: { type: Number },
			"error-required": { type: String },
			placeholder: { type: String },
		};
	}

	constructor() {
		super();
		this.type = "text";
	}

	get value() {
		return this.wrapper?.querySelector("textarea")?.value || "";
	}

	set value(newValue) {
		const input = this.wrapper?.querySelector("textarea");
		if (input && input.value !== newValue) {
			input.value = newValue;

			this.dispatch(new Event("input", { bubbles: true, composed: true }));
		}
	}

	validate() {
		const input = this.wrapper.querySelector("textarea");

		if (!input) return true;

		const isValid = input.checkValidity();

		if (isValid) {
			this.error = "";
			this.classList.remove("is-invalid");
		} else {
			if (input.validity.valueMissing) {
				this.error = this.getAttribute("error-required") || __("validation.required");
			}
			this.classList.add("is-invalid");
		}

		return isValid;
	}

	styles() {
		return ["core/style/layout/layout.css"];
	}

	render() {
		return html`
			<div class="form-group">
				${this.label ? html`<label for=${this.name}>${this.label}</label>` : ""}
				<textarea
					?required=${this.required}
					type=${this.type}
					name=${this.name}
					id=${this.name}
					class="input"
					value=${this.getAttribute("value") || ""}
					rows=${this.rows}
					cols=${this.cols}
					placeholder=${this.placeholder || ""}
				/></textarea>
				${this.error ? html`<div class="error-message">${this.error}</div>` : ""}
			</div>
		`;
	}
}

class PlutoSelect extends PlutoElement {
	static get props() {
		return {
			label: { type: String },
			name: { type: String },
			_value: { type: String },
			error: { type: String },
			options: { type: Array },
			"error-required": { type: String },
			required: { type: Boolean },
		};
	}

	constructor() {
		super();
		this.options = [];
		this.selected = null;
	}

	styles() {
		return ["core/style/layout/layout.css"];
	}

	set value(data) {
		if (this._value === data) return;
		this.wrapper.querySelector("select").value = data;
		this._value = data;
		this.dispatch(new Event("change", { bubbles: true, composed: true }));
	}

	get value() {
		return this._value;
	}

	validate() {
		const isValid = !this.required || this.value !== "";

		if (isValid) {
			this.error = "";
			this.classList.remove("is-invalid");
		} else {
			this.error = this.getAttribute("error-required") || __("validation.required");
			this.classList.add("is-invalid");
		}
		return isValid;
	}

	onReady() {
		if (!this.selected) {
			let selected = this.options.find((option) => option.selected);
			if (selected) {
				this.value = selected.value;
			} else {
				this.value = this.attr("value");
			}
		}
	}

	onAfterRender() {
		let options = [...this.wrapper.querySelector("select").options];
		if (options.length) {
			let selecteds = options.filter((a) => a.hasAttribute("selected"));
			if (selecteds.length) {
				selecteds.forEach((a) => {
					a.selected = true;
				});
			}
		}
	}

	render() {
		return html`
			<div class="form-group">
				<label for=${this.name}>${this.label}</label>
				<select
					@change=${(e) => {
						if (!e.target.value) return;
						this.value = e.target.value;
						this.selected = e.target.selectOption;
					}}
					required=${this.required}
					name=${this.name}
					id=${this.name}
					class="select ${this.error ? "is-invalid" : ""}"
				>
					${this.options.map(
						(option) => html`
							<option
								value="${option.value}"
								${option.disabled ? `disabled ` : ""}
								${this.value === option.value ? "selected " : ""}
							>
								${option.text}
							</option>
						`
					)}
				</select>
				${this.error ? html`<div class="error-message">${this.error}</div>` : ""}
			</div>
		`;
	}
}

class PlutoCheckbox extends PlutoElement {
	static get props() {
		return {
			label: { type: String },
			name: { type: String },
			required: { type: Boolean },
			error: { type: String },
			variant: { type: String },
			"error-required": { type: String },
		};
	}

	constructor() {
		super();
		this.variant = "checkbox";
	}

	styles() {
		return ["core/style/layout/layout.css"];
	}

	get checked() {
		return this.wrapper?.querySelector("input")?.checked || false;
	}

	set checked(newValue) {
		const input = this.wrapper?.querySelector("input");
		if (input && input.checked !== !!newValue) {
			input.checked = !!newValue;
			this.dispatchEvent(new Event("change", { bubbles: true, composed: true }));
		}
	}

	validate() {
		const isValid = !this.required || this.checked;

		if (isValid) {
			this.error = "";
			this.classList.remove("is-invalid");
		} else {
			this.error = this.getAttribute("error-required") || __("validation.accepted");
			this.classList.add("is-invalid");
		}
		return isValid;
	}

	onReady() {
		this.variant = this.hasAttribute("variant") ? this.getAttribute("variant") : "checkbox";
		this.wrapper.querySelector(".form-group").classList.add(this.variant);
	}

	render() {
		return html`
			<div class="form-group">
				<label>
					<input
						@change=${(e) => {
							e.stopPropagation();
							this.validate();
						}}
						?required=${this.required}
						type="checkbox"
						name=${this.name}
						?checked=${this.checked}
					/>
					<i></i>
					<span>${this.label}</span>
				</label>
				${this.error ? html`<div class="error-message">${this.error}</div>` : ""}
			</div>
		`;
	}
}

class PlutoRadio extends PlutoElement {
	static get props() {
		return {
			label: { type: String },
			name: { type: String },
			error: { type: String },
			required: { type: Boolean },
			"error-required": { type: String },
			direction: { type: String },
			_checked: { type: Boolean },
			_value: { type: String },
		};
	}

	styles() {
		return ["core/style/layout/layout.css"];
	}

	constructor() {
		super();
		this._options = [];
		this.initialized = false;
	}

	get value() {
		return this._value;
	}

	set value(newValue) {
		if (this._value !== newValue) {
			this._value = newValue;
			if (!newValue) {
				this._checked = false;
				this._value = null;
				[...this.wrapper.querySelectorAll("input")].map((input) => (input.checked = false));
				this.change();
			} else {
				this._checked = true;
				let radio = this.wrapper.querySelector(`input[value="${newValue}"]`);
				if (radio) {
					radio.checked = true;
					this._value = radio.value;
					this.change({ target: radio });
				}
			}
		}
	}

	onConnect() {
		const items = Array.from(this.querySelectorAll("item"));
		this._options = items.map((item) => ({
			label: item.getAttribute("label"),
			value: item.getAttribute("value"),
			checked: item.hasAttribute("checked"),
		}));

		const checkedOption = this._options.find((opt) => opt.checked);
		if (checkedOption) {
			this.value = checkedOption.value;
		}
	}

	onPropUpdate(prop, oldValue, newValue) {
		if (prop === "value") {
			this._options = this._options.map((opt) => ({
				...opt,
				checked: opt.value === newValue,
			}));
		}
	}

	validate() {
		const isValid =
			!this.required ||
			(this.value !== undefined && this.value !== null && this.value !== "");
		if (isValid) {
			this.error = "";
			this.classList.remove("is-invalid");
		} else {
			this.error = this.getAttribute("error-required") || __("validation.selection_required");
			this.classList.add("is-invalid");
		}

		return isValid;
	}

	get checked() {
		return this._checked;
	}

	set checked(newValue) {
		if (!newValue && this._checked) {
			this.value = null;
			return;
		}
	}

	change(event) {
		if (event) {
			let input = event.target;
			if (input.checked) {
				this._value = input.value;
				this._checked = true;
			} else {
				this._value = null;
				this._checked = false;
			}
		}
		this.dispatch(new CustomEvent("change", { detail: this.value }));
		this.validate();
	}

	render() {
		if (this.initialized) return;
		this.initialized = true;

		return html`
			<div class="form-group">
				${this.label ? html`<label class="form-label">${this.label}</label>` : ""}
				<div
					class=${this.direction
						? "radio-group " + this.direction
						: "radio-group horizontal"}
				>
					${this._options.map(
						(option) => html`
							<label class="radio-label">
								<input
									type="radio"
									name="${this.name}"
									value="${option.value}"
									${option.checked ? "checked" : ""}
									?required="${this.required}"
									@change="change"
								/>
								${option.label}
							</label>
						`
					)}
				</div>
				${this.error ? html`<div class="error-message">${this.error}</div>` : ""}
			</div>
		`;
	}
}

class PlutoAdvancedSelect extends PlutoElement {
	static get props() {
		return {
			value: { type: String },
			label: { type: String },
			options: { type: Array },
			error: { type: String },
			"error-required": { type: String },
			name: { type: String },
			required: { type: Boolean },
			multiple: { type: Boolean },
			search: { type: Boolean },
			searchPlaceholder: { type: String },
			searchError: { type: String },
			searchLoading: { type: Boolean },
			open: { type: Boolean },
			searchValue: { type: String },
			_filtered: { type: Array },
			sort: { type: String },
			length: { type: Number },
			url: { type: String },
			split: { type: String },
		};
	}

	styles() {
		return ["core/style/layout/layout.css"];
	}

	onReady() {}
	async onConnect() {
		if (this.sort) {
			const [col, order] = this.sort.split(":");
			this._sort = col || "id";
			this._order = order || "asc";
		}
		if (!this.split) {
			this.split = "name:id";
		}
		if (!this.length) {
			this.length = 10;
		}

		this._filtered = this.options || (await this.fetchData());
		this.open = false;
		if (this.attr("value")) {
			this.value = this.attr("value");
		} else {
			this.value = this.multiple ? [] : null;
		}

		if (this.search) {
			this.searchValue = "";
			this.searchError = "";
			this.searchLoading = false;
			this.searchPlaceholder = __("table.search");
			let searchInput = document.createElement("pluto-input");
			searchInput.setAttribute("slot", "search");
			searchInput.setAttribute("placeholder", this.searchPlaceholder);
			this.appendChild(searchInput);
			searchInput.addEventListener("input", this.searchHandler.bind(this));
			searchInput.addEventListener("click", (e) => e.stopPropagation());
		}
	}

	async fetchData() {
		const params = new URLSearchParams();
		params.append("length", this.length);
		if (this._sort) {
			params.append("sort", this._sort);
			params.append("order", this._order);
		}
		if (this.searchValue) {
			params.append("search", this.searchValue);
		}
		if (this.split) {
			params.append("split", this.split);
		}

		try {
			this.searchLoading = true;
			this.searchError = "";
			const data = await get(`${this.url}?${params.toString()}`);
			this.searchLoading = false;
			this.options = data;
			return data;
		} catch (error) {
			this.searchLoading = false;
			this.searchError = error.message;
			return [];
		}
	}

	onPropUpdate(prop, oldValue, newValue) {
		if (prop === "options") {
			this._filtered = newValue || [];
		}
		if (prop === "open") {
			if (!newValue) {
				this.parentNode.removeEventListener("click", this._boundClickOutside);
				document.removeEventListener("click", this._boundClickOutside);
			} else {
				this._boundClickOutside = this._clickOutside.bind(this);
				this.parentNode.addEventListener("click", this._boundClickOutside);
				document.addEventListener("click", this._boundClickOutside);
			}
		}
	}

	_clickOutside(e) {
		if (!this.contains(e.target)) {
			this.open = false;
		}
	}

	toggle() {
		this.open = !this.open;
	}
	validate() {
		let isValid = true;

		if (this.required) {
			if (this.multiple) {
				isValid = this.value.length > 0;
			} else {
				isValid = this.value !== null && this.value !== undefined && this.value !== "";
			}
		}
		if (!isValid) {
			this.error = this.getAttribute("error-required") || __("validation.required");
			this.classList.add("is-invalid");
		} else {
			this.error = "";
			this.classList.remove("is-invalid");
		}
		return isValid;
	}

	selectOption(option) {
		if (this.multiple) {
			const isSelected = this.value.some((v) => v == option.value);
			if (isSelected) {
				this.value = this.value.filter((v) => v != option.value);
			} else {
				this.value = [...this.value, option.value];
			}
		} else {
			this.value = option.value;
			this.open = false;
		}
		this.dispatch(new CustomEvent("change", { detail: this.value }));
		this.validate();
	}

	searchHandler(e) {
		this.searchValue = e.target.value;
		if (this.url) {
			this.fetchData();
			return;
		}
		if (this.options) {
			this._filtered = this.options.filter((option) => {
				return option.text.toLowerCase().includes(this.searchValue.toLowerCase());
			});
		}
	}

	_getSelectedText() {
		if (!this.options) return this.label;
		if (this.multiple) {
			if (!this.value || this.value.length === 0) return this.label;
			return this.value
				.map((v) => this.options.find((opt) => opt.value == v)?.text)
				.join(", ");
		} else {
			const selected = this.options.find((opt) => opt.value == this.value);
			return selected ? selected.text : this.label;
		}
	}

	render() {
		return html`
			<div class="form-group">
				<label for=${this.name}>${this.label}</label>
				<div class="adv-select">
					<button
						class="adv-select-display"
						@click=${this.toggle}
					>
						${this._getSelectedText()}
					</button>
					${this.open
						? html`
								<div class="adv-select-dropdown">
									<slot name="search"></slot>
									<ul class="adv-select-options">
										${this._filtered && this._filtered.length > 0
											? this._filtered.map((option) => {
													const isSelected = this.multiple
														? this.value.includes(option.value)
														: this.value === option.value;
													return html`<li
														class="adv-select-option ${isSelected
															? "selected"
															: ""}"
														@click=${() => this.selectOption(option)}
													>
														${option.text}
													</li>`;
											  })
											: html`<li class="adv-select-option-none">
													${this.searchError || __("No options found")}
											  </li>`}
									</ul>
								</div>
						  `
						: ""}
				</div>
				${this.error ? html`<div class="error-message">${this.error}</div>` : ""}
			</div>
		`;
	}
}
Pluto.assign("pluto-adv-select", PlutoAdvancedSelect);
Pluto.assign("pluto-input", PlutoInput);
Pluto.assign("pluto-textarea", PlutoTextarea);
Pluto.assign("pluto-select", PlutoSelect);
Pluto.assign("pluto-checkbox", PlutoCheckbox);
Pluto.assign("pluto-radio", PlutoRadio);
