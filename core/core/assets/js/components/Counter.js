class PlutoCounter extends PlutoElement {
	static get props() {
		return {
			count: { type: Number },
			min: { type: Number },
			max: { type: Number },
			step: { type: Number },
		};
	}
	styles() {
		return css`
			.box {
				background: #334155;
				padding: 1rem;
				border-radius: 8px;
				color: white;
				display: flex;
				gap: 3px;
				align-items: center;
				flex-direction: column;
			}
			button {
				background: #3b82f6;
				border: none;
				padding: 5px 12px;
				border-radius: 4px;
				color: white;
				cursor: pointer;
			}
			span {
				font-size: 1.2rem;
				font-weight: bold;
				width: 30px;
				text-align: center;
				padding: 5px;
			}
			.buttons {
				display: flex;
				width: 100%;
				justify-content: space-around;
				max-width: 170px;
			}
		`;
	}
	constructor() {
		super();
		this.count = 0;
	}
	fixed() {
		let stos = String(this.step);
		if (stos.indexOf(".") > -1) {
			let afterDot = stos.split(".")[1];
			return afterDot.length;
		} else {
			return 0;
		}
	}
	increment() {
		if (this.count >= this.max) return;
		this.count = Number((this.count + this.step).toFixed(this.fixed()));
	}
	decrement() {
		if (this.count <= this.min) return;
		this.count = Number((this.count - this.step).toFixed(this.fixed()));
	}
	render() {
		return html`
			<div class="box">
				<span>${this.count}</span>
				<div class="buttons">
					<button
						part="button danger"
						@click="decrement"
					>
						-
					</button>
					<button
						part="button success"
						@click="increment"
					>
						+
					</button>
				</div>
			</div>
		`;
	}
}
Pluto.assign("pluto-counter", PlutoCounter);
