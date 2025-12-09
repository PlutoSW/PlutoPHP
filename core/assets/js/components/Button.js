class PlutoButton extends PlutoElement {
	static noShadow = true;


	constructor() {
		super();
	}

    render() {
        return html`${this.innerHTML}`;
    }
}

class PlutoSubmit extends PlutoElement {
	static noShadow = true;

	constructor() {
		super();
        this.addEventListener("click", (e) => {
            e.preventDefault();
            const form = this.closest("pluto-form");
            if (!form) return;
            form.dispatchEvent(new Event("submit"));
        });
	}

    render() {
        return html`${this.innerHTML}`;
    }
}

Pluto.assign("pluto-button", PlutoButton);
Pluto.assign("pluto-submit", PlutoSubmit);
