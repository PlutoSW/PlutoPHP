class PlutoTable extends PlutoElement {
	static get props() {
		return {
			url: { type: String },
			defaultSort: { type: String },
			rowsLength: { type: Number },
			rowsLengthOptions: { type: Array },
			selectable: { type: Boolean },
			_data: { type: Array },
			_columns: { type: Array },
			_loading: { type: Boolean },
			search: { type: String },
			_page: { type: Number },
			_sort: { type: String },
			_order: { type: String },
			_total: { type: Number },
			_selectedRows: { type: Array },
		};
	}

	styles() {
		return ["/core/style/table.css"];
	}

	constructor() {
		super();
		this.url = "";
		this._data = [];
		this._columns = [];
		this._loading = true;
		this.search = "";
		this._page = 1;
		this._sort = "id";
		this._order = "asc";
		this._total = 0;
		this.rowsLengthOptions = [10, 25, 50, 75, -1];
		this.selectable = this.hasAttribute("selectable");
		this._selectedRows = [];
		this.totalPages = 0;
	}

	onReady() {
		this._initColumns();
		this.rowsLength = parseInt(this.getAttribute("rows-length")) || 10;
		this.rowsLengthOptions = this.getAttribute("rows-length-options")
			? JSON.parse(this.getAttribute("rows-length-options"))
			: this.rowsLengthOptions;
		if (this.defaultSort) {
			const [col, order] = this.defaultSort.split(":");
			this._sort = col;
			this._order = order || "asc";
		}

		this._fetchData();
	}

	_initColumns() {
		const elements = Array.from(this.children);
		if (this.selectable) {
			const dropdown = Array.from(
				elements.find((a) => a.tagName === "PLUTO-DROPDOWN")?.children
			);
			if (!dropdown) return;
			dropdown.forEach((a) => a.addEventListener("click", (e) => this._handleActionClick(e)));
		}
		this._columns = elements
			.filter((el) => el.tagName === "PLUTO-TH")
			.map((el) => ({
				name: el.getAttribute("name"),
				type: el.getAttribute("type"),
				noSort: el.hasAttribute("no-sort"),
				label: el.textContent,
			}));
	}

	_handleActionClick(e) {
		const actionEl = e.target;
		if (!actionEl || this._selectedRows.length === 0) return;

		e.preventDefault();
		const eventName = actionEl.getAttribute("action");
		const attributes = actionEl.dataset;

		const detail = this._selectedRows.map((row) => {
			if (attributes.raw !== undefined) {
				return row;
			}
			if (Object.keys(attributes).length === 1) {
				return row[Object.keys(attributes)[0]];
			}
			const rowData = {};
			for (const key in attributes) {
				if (row.hasOwnProperty(key)) {
					rowData[key] = row[key];
				}
			}
			return rowData;
		});

		this.dispatchEvent(new CustomEvent(eventName, { detail }));
	}

	async _fetchData() {
		this._loading = true;
		const prevSelection = new Map(this._selectedRows.map((row) => [row.id, row]));
		this._selectedRows = [];

		const params = new URLSearchParams();
		params.append("page", this._page);
		params.append("rowsLength", this.rowsLength);
		if (this._sort) {
			params.append("sort", this._sort);
			params.append("order", this._order);
		}
		if (this.search) {
			params.append("search", this.search);
		}

		try {
			const result = await get(`${this.url}?${params.toString()}`);
			const fetchedData = result.data || [];
			const pagination = result.pagination || {};

			this._data = fetchedData.map((newRow) => {
				if (prevSelection.has(newRow.id)) {
					this._selectedRows.push(newRow);
					return newRow;
				}
				return newRow;
			});

			this.dispatchEvent(
				new CustomEvent("beforeRowRender", {
					detail: { data: this._data, columns: this._columns },
				})
			);

			this._total = pagination.totalRecords;
			this._page = pagination.currentPage;
			this.totalPages = pagination.totalPages;
		} catch (e) {
			console.error("PlutoJS: Error fetching data for pluto-table:", e);
			this._data = [];
			this._total = 0;
			this._page = 1;
		} finally {
			this._loading = false;
			this._dispatchSelectionChange();
		}
	}

	_dispatchSelectionChange() {
		this.dispatchEvent(
			new CustomEvent("selectionchange", {
				detail: {
					selectedRows: this._selectedRows,
				},
			})
		);
	}

	handleSelectRow(row) {
		const isSelected = this._selectedRows.some((selectedRow) => selectedRow.id === row.id);
		if (isSelected) {
			this._selectedRows = this._selectedRows.filter((r) => r.id !== row.id);
		} else {
			this._selectedRows = [...this._selectedRows, row];
		}
		this._dispatchSelectionChange();
	}

	handleSelectAll(e) {
		let input = e.target.firstElementChild;
		if (input.checked) {
			input.checked = false;
		} else {
			input.checked = true;
		}
		if (input.checked) {
			this._selectedRows = [...this._data];
		} else {
			this._selectedRows = [];
		}
		this._dispatchSelectionChange();
	}

	handleSort(colName, noSort) {
		if (noSort) return;
		if (this._sort === colName) {
			this._order = this._order === "asc" ? "desc" : "asc";
		} else {
			this._sort = colName;
			this._order = "asc";
		}
		this._fetchData();
	}

	handleSearch(e) {
		this.search = e.target.value;
		this._page = 1;
		this._fetchData();
	}

	changerowsLength(e) {
		this.rowsLength = parseInt(e.target.value);
		this._page = 1;
		this._fetchData();
	}

	onAfterRender() {
		this.dispatchEvent(
			new CustomEvent("finish", {
				detail: {
					data: this._data,
					headers: this.wrapper.querySelectorAll("thead th"),
					rows: this.wrapper.querySelectorAll("tbody tr"),
				},
			})
		);
	}

	rowTemplates(templates = {}) {
		Object.keys(templates).forEach((key) => {
			if (Object.hasOwnProperty.call(this._columns, key)) {
				this._columns[key].template = templates[key];
			}
		});
		return this;
	}

	render() {
		const areAllSelected =
			this._data.length > 0 && this._selectedRows.length === this._data.length;

		return html`
			<div class="controls">
				<select
					class="rows-per-page"
					@change="changerowsLength"
					value=${this.rowsLength}
				>
					${this.rowsLengthOptions.map(
						(length) => html`
							<option
								value=${length}
								${this.rowsLength === length ? "selected" : ""}
							>
								${length == -1 ? __("table.all") : length}
							</option>
						`
					)}
				</select>
				<div
					class="actions"
					style=${this.selectable && this._selectedRows.length > 0
						? "visibility:visible; opacity: 1"
						: "visibility:hidden; opacity: 0"}
				>
					<slot name="actions"></slot>
				</div>
				<input
					type="search"
					class="search-box"
					placeholder="Ara..."
					@input="handleSearch"
					value=${this.search}
				/>
			</div>

			<div class="wrapper">
				<table>
					<thead>
						<tr>
							${this.selectable
								? html`<th
										class="select-all"
										@click=${(e) => this.handleSelectAll(e)}
								  >
										<input
											type="checkbox"
											${areAllSelected ? "checked" : ""}
										/>
								  </th>`
								: ""}
							${this._columns.map((col) => {
								const isActive = this._sort === col.name;
								const icon = isActive ? (this._order === "asc" ? "↑" : "↓") : "";
								return html`
									<th
										class="${isActive ? "active-sort" : ""}"
										@click=${() => this.handleSort(col.name, col.noSort)}
										style="${col.noSort ? "cursor:default" : ""}"
									>
										${col.label} <span class="sort-icon">${icon}</span>
									</th>
								`;
							})}
						</tr>
					</thead>
					<tbody>
						${this._data.map(
							(row) => html`<tr
								class=${this._selectedRows.some((r) => r.id === row.id)
									? "selected"
									: ""}
							>
								${this.selectable
									? html`<td>
											<input
												type="checkbox"
												${this._selectedRows.some((r) => r.id === row.id)
													? "checked"
													: ""}
												@click=${() => this.handleSelectRow(row)}
											/>
									  </td>`
									: ""}
								${this._columns.map(
									(col) =>
										html`<td>
											${col?.template ? col.template(row) : row[col.name]}
										</td>`
								)}
							</tr> `
						)}
					</tbody>
				</table>
			</div>

			<div class="footer">
				<div
					class="loading-overlay"
					style=${this._loading ? "visibility:visible" : "visibility:hidden"}
				>
					<pluto-spinner size="24"></pluto-spinner>
				</div>
				<pluto-pagination
					.current-page=${this._page}
					.total-pages=${this.totalPages}
					.total-records=${this._total}
					@page-change=${(e) => {
						this._page = e.detail;
						this._fetchData();
					}}
				></pluto-pagination>
			</div>

			<div style="display:none">
				<slot></slot>
			</div>
		`;
	}
}
Pluto.assign("pluto-table", PlutoTable);
