(function () {
	"use strict";

	function ready(fn) {
		if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", fn);
		else fn();
	}

	ready(function () {
		/* ---------- Header: menus ---------- */
		var userMenu = document.querySelector(".user-menu");

		function closeUserMenu() {
			if (!userMenu) return;
			userMenu.classList.remove("open");
			var t = userMenu.querySelector(".user-toggle");
			if (t) t.setAttribute("aria-expanded", "false");
		}

		document.querySelectorAll(".user-toggle").forEach(function (button) {
			button.addEventListener("click", function (e) {
				e.stopPropagation();
				var open = button.closest(".user-menu").classList.toggle("open");
				button.setAttribute("aria-expanded", open ? "true" : "false");
			});
		});

		document.addEventListener("click", function (e) {
			if (userMenu && !userMenu.contains(e.target)) closeUserMenu();
		});

		document.addEventListener("keydown", function (e) {
			if (e.key === "Escape") closeUserMenu();

			// "/" focuses search
			var tag = (e.target.tagName || "").toLowerCase();
			if (e.key === "/" && !e.ctrlKey && !e.metaKey && !e.altKey && tag !== "input" && tag !== "textarea" && tag !== "select" && !e.target.isContentEditable) {
				var input = document.querySelector(".header-search input") || document.querySelector(".search-form input");
				if (input) {
					e.preventDefault();
					input.focus();
				}
			}
		});

		/* ---------- Edit toggles ---------- */
		document.querySelectorAll(".edit-toggle").forEach(function (button) {
			button.addEventListener("click", function () {
				var target = document.getElementById(button.dataset.target);
				if (!target) return;
				var post = target.closest(".post");
				var opened = target.classList.toggle("open");
				if (post) post.classList.toggle("editing", opened);
				if (opened) {
					var ta = target.querySelector("textarea");
					if (ta) {
						ta.focus();
						ta.setSelectionRange(ta.value.length, ta.value.length);
					}
				}
			});
		});

		/* ---------- Confirm dialogs ---------- */
		document.querySelectorAll("[data-confirm]").forEach(function (el) {
			var message = el.dataset.confirm;
			if (el.tagName === "FORM") {
				el.addEventListener("submit", function (e) {
					if (!confirm(message)) e.preventDefault();
				});
			} else {
				el.addEventListener("click", function (e) {
					if (!confirm(message)) e.preventDefault();
				});
			}
		});

		/* ---------- Markdown editor ---------- */
		function wrap(ta, before, after, placeholder) {
			var s = ta.selectionStart,
				e = ta.selectionEnd;
			var sel = ta.value.slice(s, e) || placeholder;
			ta.setRangeText(before + sel + after, s, e, "end");
			ta.focus();
			ta.setSelectionRange(s + before.length, s + before.length + sel.length);
			ta.dispatchEvent(new Event("input", { bubbles: true }));
		}

		function prefixLines(ta, fn) {
			var s = ta.selectionStart,
				e = ta.selectionEnd,
				v = ta.value;
			var start = v.lastIndexOf("\n", s - 1) + 1;
			var endIdx = v.indexOf("\n", e);
			if (endIdx === -1) endIdx = v.length;
			var block = v.slice(start, endIdx).split("\n").map(fn).join("\n");
			ta.setRangeText(block, start, endIdx, "select");
			ta.focus();
			ta.dispatchEvent(new Event("input", { bubbles: true }));
		}

		var actions = {
			bold: function (ta) {
				wrap(ta, "**", "**", "bold text");
			},
			italic: function (ta) {
				wrap(ta, "*", "*", "italic text");
			},
			strike: function (ta) {
				wrap(ta, "~~", "~~", "text");
			},
			code: function (ta) {
				var sel = ta.value.slice(ta.selectionStart, ta.selectionEnd);
				if (sel.indexOf("\n") !== -1) wrap(ta, "```\n", "\n```", "code");
				else wrap(ta, "`", "`", "code");
			},
			link: function (ta) {
				var s = ta.selectionStart,
					e = ta.selectionEnd;
				var sel = ta.value.slice(s, e) || "link text";
				ta.setRangeText("[" + sel + "](https://)", s, e, "end");
				ta.focus();
				var urlStart = s + sel.length + 3;
				ta.setSelectionRange(urlStart, urlStart + 8);
			},
			quote: function (ta) {
				prefixLines(ta, function (l) {
					return "> " + l;
				});
			},
			ul: function (ta) {
				prefixLines(ta, function (l) {
					return "- " + l;
				});
			},
			ol: function (ta) {
				var i = 0;
				prefixLines(ta, function (l) {
					i++;
					return i + ". " + l;
				});
			},
		};

		document.querySelectorAll("[data-editor]").forEach(function (editor) {
			var ta = editor.querySelector("textarea");
			var counter = editor.querySelector("[data-count]");

			editor.querySelectorAll("[data-md]").forEach(function (btn) {
				btn.addEventListener("click", function () {
					var fn = actions[btn.dataset.md];
					if (fn && ta) fn(ta);
				});
			});

			if (counter && ta) {
				var max = parseInt(counter.dataset.max, 10) || 10000;
				var update = function () {
					var n = ta.value.length;
					counter.textContent = n + " / " + max;
					counter.classList.toggle("warn", n > max * 0.9);
				};
				ta.addEventListener("input", update);
				update();
			}

			if (ta) {
				ta.addEventListener("keydown", function (e) {
					if ((e.ctrlKey || e.metaKey) && e.key === "Enter") {
						var form = ta.closest("form");
						if (form) {
							e.preventDefault();
							if (form.requestSubmit) form.requestSubmit();
							else form.submit();
						}
					} else if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === "b") {
						e.preventDefault();
						actions.bold(ta);
					} else if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === "i") {
						e.preventDefault();
						actions.italic(ta);
					}
				});
			}
		});

		/* ---------- Quote reply ---------- */
		document.querySelectorAll(".quote-btn").forEach(function (btn) {
			btn.addEventListener("click", function () {
				var src = document.getElementById(btn.dataset.source);
				var ta = document.getElementById("reply-body");
				if (!src || !ta) return;

				var selection = window.getSelection ? String(window.getSelection()) : "";
				var text = (selection && src.contains(window.getSelection().anchorNode) ? selection : src.innerText).trim();
				if (text.length > 600) text = text.slice(0, 600).trim() + "…";

				var quoted =
					"> **" +
					btn.dataset.author +
					"** wrote:\n" +
					text
						.split("\n")
						.map(function (l) {
							return "> " + l;
						})
						.join("\n") +
					"\n\n";

				ta.value = (ta.value ? ta.value.replace(/\s*$/, "\n\n") : "") + quoted;
				ta.dispatchEvent(new Event("input", { bubbles: true }));
				ta.scrollIntoView({ behavior: "smooth", block: "center" });
				ta.focus();
				ta.setSelectionRange(ta.value.length, ta.value.length);
			});
		});

		/* ---------- Code blocks: highlight + copy ---------- */
		var KEYWORDS = new Set("abstract as async await break case catch class const continue default def del do echo elif else elseif enum except export extends false final finally fn for foreach from function global if implements import in include interface is lambda let match namespace new nil none not null or pass print private protected public raise require return self static struct super switch this throw trait true try type typeof use var void while with yield and select insert update delete where join group order by limit create table alter drop into values set on null".split(" "));

		var HASH_COMMENT = new Set(["", "py", "python", "sh", "bash", "shell", "zsh", "yaml", "yml", "rb", "ruby", "php", "pl", "toml", "ini", "conf", "dockerfile", "makefile", "r"]);
		var DASH_COMMENT = new Set(["sql", "lua", "hs", "haskell"]);

		function highlight(code, lang) {
			var parts = ["(\\/\\*[\\s\\S]*?\\*\\/|\\/\\/[^\\n]*"];
			if (HASH_COMMENT.has(lang)) parts[0] += "|#[^\\n]*";
			if (DASH_COMMENT.has(lang)) parts[0] += "|--[^\\n]*";
			parts[0] += ")";
			parts.push("(\"(?:[^\"\\\\\\n]|\\\\.)*\"|'(?:[^'\\\\\\n]|\\\\.)*'|`(?:[^`\\\\]|\\\\.)*`)");
			parts.push("(\\$[A-Za-z_]\\w*|@[A-Za-z_]\\w*)");
			parts.push("(\\b\\d[\\d_]*(?:\\.\\d+)?\\b)");
			parts.push("(\\b[A-Za-z_]\\w*\\b)");
			var re = new RegExp(parts.join("|"), "g");

			var frag = document.createDocumentFragment();
			var last = 0,
				m;
			var add = function (text, cls) {
				if (!text) return;
				if (!cls) {
					frag.appendChild(document.createTextNode(text));
					return;
				}
				var s = document.createElement("span");
				s.className = cls;
				s.textContent = text;
				frag.appendChild(s);
			};

			while ((m = re.exec(code)) !== null) {
				add(code.slice(last, m.index));
				if (m[1]) add(m[0], "tok-c");
				else if (m[2]) add(m[0], "tok-s");
				else if (m[3]) add(m[0], "tok-v");
				else if (m[4]) add(m[0], "tok-n");
				else if (KEYWORDS.has(m[0].toLowerCase())) add(m[0], "tok-k");
				else add(m[0]);
				last = re.lastIndex;
			}
			add(code.slice(last));
			return frag;
		}

		document.querySelectorAll(".post-body pre").forEach(function (pre) {
			var codeEl = pre.querySelector("code");
			if (!codeEl) return;
			var raw = codeEl.textContent;

			if (raw.length < 20000) {
				var frag = highlight(raw, (pre.dataset.lang || "").toLowerCase());
				codeEl.textContent = "";
				codeEl.appendChild(frag);
			}

			var btn = document.createElement("button");
			btn.type = "button";
			btn.className = "code-copy";
			btn.textContent = "Copy";
			btn.addEventListener("click", function () {
				var done = function () {
					btn.textContent = "Copied";
					setTimeout(function () {
						btn.textContent = "Copy";
					}, 1500);
				};
				if (navigator.clipboard && navigator.clipboard.writeText) {
					navigator.clipboard.writeText(raw).then(done, function () {});
				}
			});
			pre.appendChild(btn);
		});

		/* ---------- Announcement banner: dismiss (remembered per message) ---------- */
		document.querySelectorAll("[data-announcement]").forEach(function (banner) {
			var key = "announcement-dismissed-" + banner.dataset.announcement;
			try {
				if (localStorage.getItem(key) === "1") banner.hidden = true;
			} catch (err) {}
			var close = banner.querySelector(".announcement-close");
			if (close) {
				close.addEventListener("click", function () {
					banner.hidden = true;
					try {
						localStorage.setItem(key, "1");
					} catch (err) {}
				});
			}
		});

		/* ---------- Icons (en sonda: DOM değişiklikleri tamamlandı) ---------- */
		if (window.lucide) window.lucide.createIcons();
	});
})();
