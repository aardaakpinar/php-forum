document.addEventListener("DOMContentLoaded", () => {
	lucide.createIcons();
});

document.querySelectorAll(".user-toggle").forEach((button) => {
	button.addEventListener("click", () => {
		button.closest(".user-menu").classList.toggle("open");
	});
});

document.querySelectorAll(".edit-toggle").forEach((button) => {
	button.addEventListener("click", () => {
		const target = document.getElementById(button.dataset.target);
		if (!target) return;

		const post = button.closest(".post");

		const opened = target.classList.toggle("open");

		if (opened) {
			if (post) post.classList.add("editing");
			target.classList.add("open");
			const textarea = target.querySelector("textarea");
			if (textarea) textarea.focus();
		} else {
			if (post) post.classList.remove("editing");
			target.classList.remove("open");
		}
	});
});

document.querySelectorAll("[data-confirm]").forEach((el) => {
	const message = el.dataset.confirm;

	if (el.tagName === "FORM") {
		el.addEventListener("submit", (e) => {
			if (!confirm(message)) e.preventDefault();
		});
	} else {
		el.addEventListener("click", (e) => {
			if (!confirm(message)) e.preventDefault();
		});
	}
});
