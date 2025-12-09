class ChartComponent extends PlutoElement {
	static get props() {
		return {
			type: { type: String },
			data: { type: Object },
			chartInstance: { type: Object, private: true },
		};
	}

	constructor() {
		super();
		this.chartInstance = null;
        
	}

	styles() {
		return ["/core/style/chart.css"];
	}
	loadChartJsLibrary() {
		return new Promise((resolve) => {
			if (typeof Chart !== "undefined") return resolve();

			let chartLib = document.getElementById("chart-library");
			if (chartLib) {
				chartLib.addEventListener("load", resolve);
			} else {
				const script = document.createElement("script");
				script.id = "chart-library";
				script.src = "https:\/\/cdn.jsdelivr.net/npm/chart.js";
				script.onload = resolve;
				document.head.appendChild(script);
			}
		});
	}
	initChart() {
		if (typeof Chart === "undefined") {
			console.error("Chart.js is not loaded. Please include it in your page.");
			return;
		}

		if (this.chartInstance) {
			this.chartInstance.destroy();
		}

		const canvas = this.wrapper.querySelector("canvas");
		if (!canvas) {
			return;
		}
		const ctx = canvas.getContext("2d");

		if (this.data && this.type) {
			this.chartInstance = new Chart(ctx, {
				type: this.type,
				data: this.data,
				options: {
					responsive: true,
					maintainAspectRatio: false,
				},
			});
		}
	}

	render() {
		this.loadChartJsLibrary().then(this.initChart.bind(this));

		return html`<canvas></canvas>`;
	}
}

Pluto.assign("pluto-chart", ChartComponent);
