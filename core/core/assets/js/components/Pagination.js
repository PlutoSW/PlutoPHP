class PlutoPagination extends PlutoElement {
	static get props() {
		return {
			currentPage: { type: Number },
			totalPages: { type: Number },
			totalRecords: { type: Number },
		};
	}

	styles() {
		return ["/core/style/pagination.css"];
	}
	_dispatch(page) {
		if (page !== this.currentPage && page > 0 && page <= this.totalPages) {
			this.dispatchEvent(new CustomEvent("pageChange", { detail: page }));
		}
	}

	render() {
		const pages = [];
		const delta = 2;
		for (let i = 1; i <= this.totalPages; i++) {
			if (
				i === 1 ||
				i === this.totalPages ||
				(i >= this.currentPage - delta && i <= this.currentPage + delta)
			) {
				pages.push(i);
			} else if (pages[pages.length - 1] !== "...") {
				pages.push("...");
			}
		}

		return html`
			<div class="info">
				${__("pagination.page_info", {
					totalRecords: this.totalRecords,
					currentPage: this.currentPage,
					totalPages: this.totalPages,
				})}
			</div>

			<button
				class="page-btn"
				?disabled=${this.currentPage === 1}
				@click=${() => this._dispatch(this.currentPage - 1)}
			>
				&lt;
			</button>

			${pages.map((p) =>
				p === "..."
					? html`<span class="dots">...</span>`
					: html`
							<button
								class="page-btn ${p === this.currentPage ? "active" : ""}"
								@click="_dispatch(${p})"
							>
								${p}
							</button>
					  `
			)}

			<button
				class="page-btn"
				?disabled=${this.currentPage === this.totalPages}
				@click=${() => this._dispatch(this.currentPage + 1)}
			>
				&gt;
			</button>
		`;
	}
}
Pluto.assign("pluto-pagination", PlutoPagination);
