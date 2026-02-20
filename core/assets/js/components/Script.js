class Script extends PlutoElement {
	static noShadow = true;

	constructor() {
		super();
		this.src = this.attr("src");
		this.async = this.attr("async");
		this.type = this.attr("type");
		this.when = this.attr("when");

		this.code = "";
		this.getScript();
	}

	async getScript() {
		if (this.src) {
			this.code = await get(
				this.src,
				{ "Content-Type": "text/javascript; charset=UTF-8;" },
				"text"
			);
		} else {
			this.code = this.innerHTML;
		}
		this.initJS();
	}

	initJS() {
		let script = document.createElement("script");
		if (this.type) {
			script.type = this.type;
		}
		if (this.async) {
			script.async = this.async;
		}
		if (this.when && this.code.search(this.when)<0) {
			this.code = `document.addEventListener('${this.when}',()=>{${this.code}});`;
		}
		script.innerHTML = this.code;
		this.appendChild(script);
	}

	render() {
		return;
	}
}

Pluto.assign("pluto-script", Script);
