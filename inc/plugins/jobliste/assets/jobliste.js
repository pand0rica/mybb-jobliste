/**
 * jobliste.js - Jobliste
 *
 * 1. Rueckfrage vor destruktiven Formularen: <form data-jobliste-confirm="Frage?">
 * 2. Durchsuchbares Kombo-Feld fuer die Kategorie-Auswahl ([data-jobliste-combo], auch im
 *    Personenformular des Relatives-Plugins, dort begrenzt auf [data-jobliste-scope]):
 *    filtert die <option>s des Kategorie-Selects nach Pfad, blendet das Freitextfeld nur
 *    bei Kategorien mit erlaubtem Freitext ein. Ohne JS bleibt das normale Select.
 * 3. Gesamtuebersicht: Tabs je Wurzelkategorie, Suchfilter (ueber alle Tabs) + Alle
 *    auf/zu fuer den <details>-Kategoriebaum.
 * 4. Info-Icon bei Verwandten: Popup per Klick/Tipp umschalten (Hover/Fokus per CSS).
 * Keine externen Libraries (Projektregel).
 */
(function () {
	"use strict";

	function normalize(text) {
		text = String(text || "").toLowerCase();
		if (text.normalize) {
			text = text.normalize("NFD").replace(/[̀-ͯ]/g, "");
		}
		return text.replace(/\s+/g, " ").trim();
	}

	function childrenByClass(parent, className) {
		var result = [];
		if (!parent) { return result; }
		for (var i = 0; i < parent.children.length; i++) {
			if (parent.children[i].classList.contains(className)) {
				result.push(parent.children[i]);
			}
		}
		return result;
	}

	// --- 1. Confirm ---------------------------------------------------------------------

	document.addEventListener("submit", function (event) {
		var form = event.target;
		if (!form || !form.getAttribute) { return; }
		var question = form.getAttribute("data-jobliste-confirm");
		if (question && !window.confirm(question)) {
			event.preventDefault();
		}
	});

	// --- 2. Kombo-Feld -----------------------------------------------------------------

	function initCombo(combo) {
		var search = combo.querySelector(".jobliste_combo_search");
		var select = combo.querySelector(".jobliste_combo_select");
		var selectedInfo = combo.querySelector(".jobliste_combo_selected");
		// Bereich des Formulars: im Personenformular von Relatives steckt das Kombo-Feld in
		// einem fremden <form> -- dort begrenzt [data-jobliste-scope] die Suche nach den
		// zugehoerigen Freitext-/Ebenenfeldern.
		var form = combo.closest ? (combo.closest("[data-jobliste-scope]") || combo.closest("form")) : null;
		var customRow = form ? form.querySelector(".jobliste_custom_row") : null;
		var customInput = customRow ? customRow.querySelector("input") : null;
		var levelsRow = form ? form.querySelector(".jobliste_levels_row") : null;
		var levelGroups = form ? form.querySelectorAll(".jobliste_level_group") : [];
		if (!search || !select) { return; }

		var all = [];
		for (var i = 0; i < select.options.length; i++) {
			var opt = select.options[i];
			all.push({
				value: opt.value,
				label: opt.textContent,
				path: opt.getAttribute("data-path") || opt.textContent,
				custom: opt.getAttribute("data-custom") === "1",
				norm: normalize(opt.getAttribute("data-path") || opt.textContent)
			});
		}

		function current() {
			var value = select.value;
			for (var j = 0; j < all.length; j++) {
				if (all[j].value === value) { return all[j]; }
			}
			return null;
		}

		function updateState() {
			var item = current();
			if (selectedInfo) {
				selectedInfo.textContent = item ? item.path : "";
			}
			if (customRow && customInput) {
				var allowed = !!(item && item.custom);
				customRow.classList.toggle("jobliste_hidden", !allowed);
				customInput.disabled = !allowed;
			}
			// Ebenen (Kategorietyp "levels"): nur die Gruppe der gewaehlten Kategorie zeigen,
			// deren Felder Pflicht machen, alle anderen deaktivieren (werden nicht gesendet).
			var anyLevels = false;
			for (var g = 0; g < levelGroups.length; g++) {
				var active = !!item && levelGroups[g].getAttribute("data-category") === item.value;
				levelGroups[g].classList.toggle("jobliste_hidden", !active);
				var selects = levelGroups[g].querySelectorAll("select");
				for (var s = 0; s < selects.length; s++) {
					selects[s].disabled = !active;
					selects[s].required = active;
				}
				if (active) { anyLevels = true; }
			}
			if (levelsRow) {
				levelsRow.classList.toggle("jobliste_hidden", !anyLevels);
			}
		}

		function render(query) {
			var keep = select.value;
			var q = normalize(query);
			var words = q === "" ? [] : q.split(" ");
			while (select.options.length) { select.remove(0); }
			for (var k = 0; k < all.length; k++) {
				var item = all[k];
				var match = true;
				for (var w = 0; w < words.length; w++) {
					if (item.norm.indexOf(words[w]) === -1) { match = false; break; }
				}
				if (!match && item.value !== keep) { continue; }
				// Gefiltert: ganzer Pfad statt eingerueckter Name, damit Treffer eindeutig sind.
				var option = new Option(q === "" ? item.label : item.path, item.value);
				option.setAttribute("data-path", item.path);
				option.setAttribute("data-custom", item.custom ? "1" : "0");
				if (item.value === keep) { option.selected = true; }
				select.add(option);
			}
			updateState();
		}

		search.hidden = false;
		search.addEventListener("input", function () { render(search.value); });
		search.addEventListener("keydown", function (event) {
			// Pfeil runter springt in die Liste, Enter waehlt den ersten Treffer.
			if (event.key === "ArrowDown" && select.options.length) {
				event.preventDefault();
				select.focus();
				if (select.selectedIndex < 0) { select.selectedIndex = 0; updateState(); }
			} else if (event.key === "Enter") {
				event.preventDefault();
				if (select.options.length) {
					select.selectedIndex = 0;
					updateState();
				}
			}
		});
		select.addEventListener("change", updateState);
		updateState();
	}

	// --- 3. Gesamtuebersicht -------------------------------------------------------------

	function initTabs(bar) {
		var tree = document.getElementById(bar.getAttribute("data-jobliste-tabs"));
		if (!tree) { return null; }
		var tabs = Array.prototype.slice.call(bar.querySelectorAll("[data-jobliste-tab]"));
		if (!tabs.length) { return null; }

		function panelOf(tab) {
			return document.getElementById(tab.getAttribute("aria-controls"));
		}

		function select(tab, updateHash) {
			tabs.forEach(function (t) {
				var active = t === tab;
				t.setAttribute("aria-selected", active ? "true" : "false");
				t.tabIndex = active ? 0 : -1;
				var panel = panelOf(t);
				if (panel) { panel.hidden = !active; }
			});
			if (updateHash && window.history && window.history.replaceState) {
				window.history.replaceState(null, "", "#jobliste-" + tab.getAttribute("data-jobliste-tab"));
			}
		}

		tabs.forEach(function (tab, index) {
			tab.addEventListener("click", function () { select(tab, true); });
			tab.addEventListener("keydown", function (event) {
				var next = null;
				if (event.key === "ArrowRight") { next = tabs[(index + 1) % tabs.length]; }
				if (event.key === "ArrowLeft") { next = tabs[(index - 1 + tabs.length) % tabs.length]; }
				if (next) {
					event.preventDefault();
					next.focus();
					select(next, true);
				}
			});
		});

		// Gewaehlter Tab steht im Hash (#jobliste-<id>) und uebersteht so ein Neuladen.
		var initial = tabs[0];
		var match = /^#jobliste-(.+)$/.exec(window.location.hash || "");
		if (match) {
			tabs.forEach(function (t) {
				if (t.getAttribute("data-jobliste-tab") === match[1]) { initial = t; }
			});
		}
		bar.hidden = false;
		tree.classList.add("jobliste_tabs_ready");
		select(initial, false);

		return {
			tabs: tabs,
			panelOf: panelOf,
			select: select,
			active: function () {
				for (var i = 0; i < tabs.length; i++) {
					if (tabs[i].getAttribute("aria-selected") === "true") { return tabs[i]; }
				}
				return tabs[0];
			}
		};
	}

	function initOverview(tools) {
		var input = tools.querySelector("[data-jobliste-filter]");
		var tree = input ? document.getElementById(input.getAttribute("data-jobliste-filter")) : null;
		if (!tree) { return; }
		var emptyNote = tree.parentNode.querySelector(".jobliste_filter_empty");
		var tabBar = tree.parentNode.querySelector("[data-jobliste-tabs]");
		var tabs = tabBar ? initTabs(tabBar) : null;
		var savedOpen = null;
		var savedTab = null;
		tools.hidden = false;

		// Inhalt eines Knotens: bei <details> der .jobliste_cat_body, bei Tab-Panels das
		// Panel selbst.
		function bodyOf(node) {
			return node.tagName === "DETAILS" ? childrenByClass(node, "jobliste_cat_body")[0] : node;
		}

		function jobItems(details) {
			var body = bodyOf(details);
			var lists = childrenByClass(body, "jobliste_jobs");
			var items = [];
			for (var i = 0; i < lists.length; i++) {
				items = items.concat(childrenByClass(lists[i], "jobliste_job"));
			}
			return items;
		}

		function childCategories(details) {
			return childrenByClass(bodyOf(details), "jobliste_cat");
		}

		function nameOf(details) {
			var summary = details.tagName === "DETAILS"
				? details.querySelector("summary .jobliste_cat_name")
				: details.querySelector(".jobliste_panel_title .jobliste_cat_name");
			return normalize(summary ? summary.textContent : "");
		}

		function apply(details, words, ancestorMatch) {
			var name = nameOf(details);
			var nameMatch = words.every(function (w) { return name.indexOf(w) !== -1; });
			var inherited = ancestorMatch || nameMatch;
			var anyVisible = false;

			jobItems(details).forEach(function (li) {
				var text = normalize(li.getAttribute("data-jobliste-text"));
				var visible = inherited || words.every(function (w) { return text.indexOf(w) !== -1; });
				li.classList.toggle("jobliste_filtered_out", !visible);
				if (visible) { anyVisible = true; }
			});
			childCategories(details).forEach(function (child) {
				if (apply(child, words, inherited)) { anyVisible = true; }
			});

			var show = anyVisible || nameMatch || ancestorMatch;
			if (details.tagName === "DETAILS") {
				details.classList.toggle("jobliste_filtered_out", !show);
				details.open = anyVisible;
			}
			return show;
		}

		// Oberste Knoten: Tab-Panels, falls vorhanden, sonst Wurzel-<details>.
		function rootCategories() {
			var panels = childrenByClass(tree, "jobliste_panel");
			return panels.length ? panels : childrenByClass(tree, "jobliste_cat");
		}

		// Tabs ohne Treffer abblenden; steht der aktive Tab ohne Treffer da, zum ersten
		// Tab mit Treffern wechseln. hits = null hebt die Markierung auf.
		function markTabs(hits) {
			if (!tabs) { return; }
			var active = tabs.active();
			var firstHit = null;
			tabs.tabs.forEach(function (tab) {
				var panel = tabs.panelOf(tab);
				var hit = !hits || (!!panel && hits.indexOf(panel) !== -1);
				tab.classList.toggle("jobliste_tab_nohits", !hit);
				if (hit && !firstHit) { firstHit = tab; }
			});
			if (hits && firstHit && active.classList.contains("jobliste_tab_nohits")) {
				tabs.select(firstHit, false);
			}
		}

		function allCategories() {
			return Array.prototype.slice.call(tree.querySelectorAll("details.jobliste_cat"));
		}

		function filter() {
			var q = normalize(input.value);
			if (q === "") {
				tree.querySelectorAll(".jobliste_filtered_out").forEach(function (el) {
					el.classList.remove("jobliste_filtered_out");
				});
				if (savedOpen) {
					allCategories().forEach(function (d, i) { d.open = !!savedOpen[i]; });
					savedOpen = null;
				}
				if (tabs && savedTab) {
					tabs.select(savedTab, false);
					savedTab = null;
				}
				markTabs(null);
				if (emptyNote) { emptyNote.hidden = true; }
				return;
			}
			if (!savedOpen) {
				savedOpen = allCategories().map(function (d) { return d.open; });
				savedTab = tabs ? tabs.active() : null;
			}
			var words = q.split(" ");
			var hits = [];
			rootCategories().forEach(function (d) {
				if (apply(d, words, false)) { hits.push(d); }
			});
			markTabs(hits);
			if (emptyNote) { emptyNote.hidden = hits.length > 0; }
		}

		input.addEventListener("input", filter);
		tools.querySelectorAll("[data-jobliste-expand]").forEach(function (button) {
			button.addEventListener("click", function () {
				var open = button.getAttribute("data-jobliste-expand") === "1";
				allCategories().forEach(function (d) {
					if (!d.classList.contains("jobliste_filtered_out")) { d.open = open; }
				});
			});
		});
	}

	// --- 4. Info-Icon bei Verwandten -----------------------------------------------------

	function setRelinfo(box, open) {
		box.classList.toggle("jobliste_relinfo_open", open);
		// Nach bewusstem Schliessen nicht per Hover/Fokus sofort wieder aufgehen lassen.
		box.classList.toggle("jobliste_relinfo_closed", !open);
		var toggle = box.querySelector(".jobliste_relinfo_toggle");
		if (toggle) { toggle.setAttribute("aria-expanded", open ? "true" : "false"); }
	}

	document.addEventListener("click", function (event) {
		var target = event.target;
		var toggle = target && target.closest ? target.closest(".jobliste_relinfo_toggle") : null;
		var current = toggle ? toggle.closest("[data-jobliste-relinfo]") : null;
		document.querySelectorAll(".jobliste_relinfo_open").forEach(function (box) {
			if (box !== current && !box.contains(target)) { setRelinfo(box, false); }
		});
		if (current) {
			event.preventDefault();
			setRelinfo(current, !current.classList.contains("jobliste_relinfo_open"));
		}
	});

	document.addEventListener("keydown", function (event) {
		if (event.key !== "Escape") { return; }
		document.querySelectorAll(".jobliste_relinfo_open").forEach(function (box) {
			setRelinfo(box, false);
		});
	});

	// Maus verlaesst ein bewusst geschlossenes Icon: Hover darf wieder oeffnen.
	document.addEventListener("mouseout", function (event) {
		var box = event.target && event.target.closest ? event.target.closest(".jobliste_relinfo_closed") : null;
		if (box && !box.contains(event.relatedTarget)) {
			box.classList.remove("jobliste_relinfo_closed");
			if (document.activeElement && box.contains(document.activeElement)) {
				document.activeElement.blur();
			}
		}
	});

	function init() {
		document.querySelectorAll("[data-jobliste-combo]").forEach(initCombo);
		document.querySelectorAll("[data-jobliste-tools]").forEach(initOverview);
	}

	if (document.readyState === "loading") {
		document.addEventListener("DOMContentLoaded", init);
	} else {
		init();
	}
})();
