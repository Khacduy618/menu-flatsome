/**
 * Handdn Menu & Header Controller
 *
 * Interactive engine powering desktop cascading panels, mobile drill-down,
 * procedural canvas leather swatches, and search modal sheet.
 * Strict parity with Handdn Menu Prototype specification.
 *
 * @package Device_Menu_Manager
 */

(() => {
	'use strict';

	document.addEventListener('DOMContentLoaded', () => {
		const hm = document.getElementById('hm');
		const scrim = document.getElementById('scrim');
		const vis = document.getElementById('hmVis');
		const srch = document.getElementById('srch');
		const root = document.getElementById('p-root');

		if (!hm || !scrim || !root) {
			return;
		}

		const mqMobile = window.matchMedia('(max-width: 767px)');
		const finePointer = window.matchMedia('(hover: hover) and (pointer: fine)');

		let stack = []; // Array of open panel IDs below root
		let isOpen = false;
		let lastFocus = null;
		let hoverT = null;
		let switchT = null;
		let pendingTarget = null;
		let currentHoveredLink = null;
		let hoverEl = null;

		const trig = (id) => hm.querySelector(`[data-target="${id}"]`);
		const panelOf = (el) => el.closest('.hm-panel');
		const lvlOf = (id) => {
			const el = document.getElementById(id);
			return el && el.dataset.lvl ? parseInt(el.dataset.lvl, 10) : 0;
		};

		/* ---------- 1. Canvas Procedural Swatches ---------- */
		const texCache = new Map();

		function rng(seed) {
			let x = seed || 1;
			return () => (x = (x * 16807) % 2147483647) / 2147483647;
		}

		function shade(hex, f) {
			const n = parseInt(hex.slice(1), 16);
			const c = [n >> 16, (n >> 8) & 255, n & 255].map((v) =>
				Math.max(0, Math.min(255, Math.round(f >= 0 ? v + (255 - v) * f : v * (1 + f))))
			);
			return `rgb(${c[0]},${c[1]},${c[2]})`;
		}

		function swatch(tex, tone, w = 720, h = 900) {
			const key = `${tex}_${tone}_${w}x${h}`;
			if (texCache.has(key)) {
				return texCache.get(key);
			}

			const c = document.createElement('canvas');
			c.width = w;
			c.height = h;
			const g = c.getContext('2d');
			const toneSafe = (tone && tone.startsWith('#') && (tone.length === 7 || tone.length === 4)) ? tone : '#3b2416';
			const r = rng(toneSafe.length * 991 + (tex || '').length * 7 + parseInt(toneSafe.slice(1), 16) % 9973);

			g.fillStyle = toneSafe;
			g.fillRect(0, 0, w, h);

			if (tex === 'scales') {
				let y = -10;
				while (y < h) {
					const rh = 16 + r() * 22;
					let x = -r() * 30;
					while (x < w) {
						const rw = 18 + r() * 34;
						g.fillStyle = shade(toneSafe, (r() - 0.5) * 0.1);
						g.beginPath();
						g.roundRect(x + 1.5, y + 1.5, rw - 3, rh - 3, Math.min(rw, rh) * 0.24);
						g.fill();
						g.strokeStyle = 'rgba(0,0,0,.34)';
						g.lineWidth = 1.4;
						g.stroke();
						g.strokeStyle = 'rgba(255,255,255,.07)';
						g.lineWidth = 1;
						g.beginPath();
						g.moveTo(x + 8, y + 6);
						g.lineTo(x + rw - 10, y + 6);
						g.stroke();
						x += rw;
					}
					y += rh;
				}
			} else if (tex === 'dots') {
				for (let i = 0; i < (w * h) / 40; i++) {
					const x = r() * w;
					const y = r() * h;
					const rad = 1.2 + r() * 1.4;
					g.fillStyle = shade(toneSafe, 0.05 + r() * 0.1);
					g.beginPath();
					g.arc(x, y, rad, 0, 7);
					g.fill();
				}
			} else if (tex === 'quill') {
				for (let i = 0; i < (w * h) / 2600; i++) {
					const x = r() * w;
					const y = r() * h;
					const rad = 4 + r() * 6;
					const gr = g.createRadialGradient(x - 2, y - 2, 0, x, y, rad);
					gr.addColorStop(0, shade(toneSafe, 0.25));
					gr.addColorStop(1, shade(toneSafe, -0.25));
					g.fillStyle = gr;
					g.beginPath();
					g.arc(x, y, rad, 0, 7);
					g.fill();
				}
			} else if (tex === 'weave') {
				for (let y = 0; y < h; y += 6) {
					for (let x = 0; x < w; x += 6) {
						g.fillStyle = ((x + y) / 6) % 2 ? shade(toneSafe, 0.06) : shade(toneSafe, -0.08);
						g.fillRect(x, y, 6, 3);
						g.fillRect(x + (((y / 6) % 2) ? 3 : 0), y + 3, 3, 3);
					}
				}
			} else if (tex === 'cross') {
				g.strokeStyle = 'rgba(0,0,0,.22)';
				g.lineWidth = 1;
				for (let i = -h; i < w; i += 5) {
					g.beginPath();
					g.moveTo(i, 0);
					g.lineTo(i + h, h);
					g.stroke();
					g.beginPath();
					g.moveTo(i + h, 0);
					g.lineTo(i, h);
					g.stroke();
				}
			}

			if (tex !== 'weave') {
				const amt = tex === 'smooth' ? (w * h) / 60 : (w * h) / 18;
				for (let i = 0; i < amt; i++) {
					g.fillStyle = r() > 0.5 ? 'rgba(255,255,255,.035)' : 'rgba(0,0,0,.07)';
					g.fillRect(r() * w, r() * h, 1 + r() * 2, 1 + r() * 2);
				}
			}

			// Soft studio gradient light and saddle-stitched edge
			const lg = g.createLinearGradient(0, 0, w, h);
			lg.addColorStop(0, 'rgba(255,240,220,.16)');
			lg.addColorStop(0.55, 'rgba(0,0,0,0)');
			lg.addColorStop(1, 'rgba(0,0,0,.38)');
			g.fillStyle = lg;
			g.fillRect(0, 0, w, h);
			g.fillStyle = 'rgba(0,0,0,.28)';
			g.fillRect(w - 46, 0, 46, h);
			g.strokeStyle = 'rgba(246,236,220,.75)';
			g.lineWidth = 2.4;
			g.setLineDash([9, 7]);
			g.beginPath();
			g.moveTo(w - 66, 0);
			g.lineTo(w - 66, h);
			g.stroke();
			g.setLineDash([]);

			const url = c.toDataURL('image/jpeg', 0.82);
			texCache.set(key, url);
			return url;
		}

		function paint(el, v) {
			if (!el) return;
			if (el.dataset.img || (v && v.img)) {
				const src = el.dataset.img || v.img;
				el.style.backgroundImage = 'none';
				el.style.backgroundColor = '#1a1816';
				el.innerHTML = `<img src="${src}" alt="" decoding="async" onerror="this.style.display='none'">`;
				return;
			}
			const tex = (v && v.tex) ? v.tex : (el.dataset.tex || 'grain');
			const tone = (v && v.tone) ? v.tone : (el.dataset.tone || '#3b2416');
			el.innerHTML = '';
			el.style.backgroundImage = `url(${swatch(tex, tone)})`;
		}

		function paintAll(scope) {
			scope.querySelectorAll('.ph[data-tex], .ph[data-img]').forEach((el) => {
				if (!el.dataset.done) {
					paint(el, {
						tex: el.dataset.tex,
						tone: el.dataset.tone,
						img: el.dataset.img,
					});
					el.dataset.done = '1';
				}
			});
		}

		// Initial paint of promo banner and feature cards
		paintAll(document);

		/* ---------- 2. Visual Banner (desktop backdrop) ----------
		 * The banner sits fixed behind the opaque panels (CSS: left: var(--dmm-panel-w)).
		 * Panels slide over it; only the caption follows them through --depth.
		 *
		 * Pane:    HIDDEN -> OPENING -> VISIBLE -> CLOSING -> HIDDEN   (class .on, clip-path reveal in CSS)
		 * Content: the two PHP layers (.ph a/b). .is-front is visible; the other layer is prepared
		 *          and crossfades in as .is-incoming only after its <img> has decoded, so the old
		 *          visual stays on screen while the next one loads. Content is never cleared, so
		 *          reopening the same visual costs no DOM work.
		 * Every async step carries a generation number; a newer target turns older results into no-ops.
		 */
		const visA = vis ? vis.querySelector('a') : null;
		const visCap = vis ? vis.querySelector('.cap') : null;
		const layers = vis ? [...vis.querySelectorAll('.ph')] : [];
		const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
		const FADE_MS = 240;
		const ban = { front: null, frontKey: '', frontV: null, wantKey: '', incoming: null, gen: 0, paneWanted: false };
		let panelW = 0;

		layers.forEach((ph) => {
			ph.classList.remove('out');
			if (!ph.querySelector('img')) {
				const img = document.createElement('img');
				img.alt = '';
				img.decoding = 'async';
				ph.appendChild(img);
			}
		});

		// Identity is the visual's content, not the menu item that carries it.
		function visualKey(v) {
			return JSON.stringify([v.img || '', v.k || '', v.t || '', v.go || '', v.href || '']);
		}

		/* Photo slots, measured from the panels actually open. The banner is fixed behind the panels, so only
		 * the strip right of the top panel is visible. Reference viewport 1512x828 (panel 392px):
		 *   no child panel open  (L0 item without a child menu)               -> "wide"   1120 x 828
		 *   one panel open       (L0 with a child menu, L1 without L2 panel)  -> "medium"  728 x 828
		 *   two or more open     (L1 with an L2 panel, L2 items)              -> "narrow"  336 x 828
		 * Each slot is right-anchored and as wide as its strip; CSS caps the photo box at the reference size
		 * and centres it, so larger screens do not enlarge photos past their 2x source. A layer keeps the
		 * slot it was prepared with, so a photo never changes size while on screen. object-fit: contain:
		 * never cropped or stretched. Upload specs (2x reference): wide 2240x1656, medium 1456x1656,
		 * narrow 672x1656. */
		const SLOT_ASPECT = { wide: 2240 / 1656, medium: 1456 / 1656, narrow: 672 / 1656 };
		const aspects = new Map(); // photo url -> naturalWidth / naturalHeight, once known
		const badImg = new Set(); // photo urls that failed to load or decode
		const slotPx = { wide: 0, medium: 0, narrow: 0 };

		function measurePanel() {
			panelW = root.getBoundingClientRect().width || 380;
			const next = {
				wide: Math.max(0, Math.round(window.innerWidth - panelW)),
				medium: Math.max(0, Math.round(window.innerWidth - 2 * panelW)),
				narrow: Math.max(0, Math.round(window.innerWidth - 3 * panelW)),
			};
			if (!vis) return;
			Object.keys(next).forEach((k) => {
				if (next[k] !== slotPx[k]) {
					slotPx[k] = next[k];
					vis.style.setProperty(`--vis-slot-${k}`, `${next[k]}px`);
				}
			});
		}

		function hasRoom() {
			return window.innerWidth - (stack.length + 1) * panelW >= 220;
		}

		function slotForDepth(depth) {
			if (depth >= 2) return 'narrow';
			return depth === 1 ? 'medium' : 'wide';
		}

		function slotClass() {
			return slotForDepth(stack.length);
		}

		// The slot an item's photo is designed for: panels open while it is hovered = its panel level,
		// plus one if it opens a panel itself.
		function designSlot(link) {
			const p = panelOf(link);
			const lvl = !p || p.id === 'p-root' ? 0 : parseInt(p.dataset.lvl, 10) || 0;
			return slotForDepth(lvl + (link.dataset.target ? 1 : 0));
		}

		// A photo designed for another slot may stand in only if its shape is within 35% of this slot's
		// shape; otherwise it is not forced in.
		function fitsSlot(aspect, slot) {
			return Math.abs(Math.log(aspect / SLOT_ASPECT[slot])) <= Math.log(1.35);
		}

		function loadInto(ph, url) {
			const img = ph.querySelector('img');
			if (img.getAttribute('src') !== url) {
				img.src = url;
			}
			if (typeof img.decode === 'function') {
				return img.decode();
			}
			if (img.complete && img.naturalWidth) {
				return Promise.resolve();
			}
			return new Promise((resolve, reject) => {
				img.onload = resolve;
				img.onerror = reject;
			});
		}

		function setCaption(v) {
			if (!visCap || !visA) return;
			const kEl = visCap.querySelector('.k');
			const bEl = visCap.querySelector('b');
			const goEl = visCap.querySelector('.go');
			if (kEl) kEl.textContent = v.k || '';
			if (bEl) bEl.textContent = v.t || '';
			if (goEl) goEl.textContent = v.go || 'Shop';
			visA.href = v.href || '#';
		}

		// No-image state: the shown layer has no photo. CSS turns that layer into the admin-chosen
		// background (original background + black overlay, or original background only), hides the
		// caption and lets the pointer through. Written only on change, to keep moves at 0 mutations.
		function setBare(on) {
			if (vis && vis.classList.contains('is-bare') !== on) vis.classList.toggle('is-bare', on);
		}

		function isEmptyLayer(ph) {
			return !!ph && ph.classList.contains('is-empty');
		}

		function promote(inc) {
			if (inc.frontAnim) inc.frontAnim.cancel();
			setBare(isEmptyLayer(inc.ph));
			if (ban.front && ban.front !== inc.ph) {
				ban.front.classList.remove('is-front');
			}
			inc.ph.classList.remove('is-incoming');
			inc.ph.classList.add('is-front');
			ban.front = inc.ph;
			ban.frontKey = inc.key;
			ban.frontV = inc.v;
		}

		// Nearest-commit: a crossfade at >= 50% is finished instantly, below 50% it is undone.
		// Either way the visible jump is at most half the opacity range, and one layer is free again.
		function settleIncoming() {
			const inc = ban.incoming;
			if (!inc) return;
			ban.incoming = null;
			const timing = inc.anim.effect ? inc.anim.effect.getComputedTiming() : null;
			const progress = timing && timing.progress !== null ? timing.progress : 1;
			if (progress >= 0.5) {
				promote(inc);
			} else {
				inc.ph.classList.remove('is-incoming');
				if (inc.frontAnim) inc.frontAnim.cancel();
				setBare(isEmptyLayer(ban.front));
				if (ban.frontV) setCaption(ban.frontV); // caption belongs to the layer that stays
			}
			inc.anim.cancel();
		}

		function openPane() {
			if (!vis || !ban.paneWanted) return;
			// classList writes the attribute even when nothing changes; skip to keep same-visual moves at 0 mutations.
			if (vis.classList.contains('on') && !vis.classList.contains('is-dismiss')) return;
			vis.classList.remove('is-dismiss');
			vis.classList.add('on');
		}

		// dismiss = full menu close: fade out instead of wiping beside a drawer that is leaving.
		function closePane(dismiss = false) {
			if (!vis) return;
			ban.paneWanted = false;
			if (ban.wantKey !== ban.frontKey || ban.incoming) {
				ban.gen++;
				settleIncoming();
				ban.wantKey = ban.frontKey;
			}
			if (!vis.classList.contains('on')) return;
			vis.classList.toggle('is-dismiss', dismiss);
			vis.classList.remove('on');
		}

		function commit(ph, v, key) {
			const empty = isEmptyLayer(ph);
			// The no-image layer shows no caption: keep the old text while it fades out with the photo.
			if (!empty) setCaption(v);
			// A pane that is closing is still on screen (visibility flips to hidden only after the wipe),
			// so it must crossfade too; only a fully hidden pane may swap content instantly.
			const onScreen = vis.classList.contains('on') || getComputedStyle(vis).visibility === 'visible';
			const fade = ban.front && onScreen && !reduceMotion.matches && typeof ph.animate === 'function';
			if (!fade) {
				promote({ ph, key, v });
				openPane();
				return;
			}
			openPane();
			setBare(empty);
			ph.classList.add('is-incoming');
			const anim = ph.animate([{ opacity: 0 }, { opacity: 1 }], { duration: FADE_MS, easing: 'ease' });
			// A see-through layer cannot cover the old one, so the old photo fades out underneath it
			// (held at 0 until promote() swaps the classes in the same task).
			const frontAnim = empty
				? ban.front.animate([{ opacity: 1 }, { opacity: 0 }], { duration: FADE_MS, easing: 'ease', fill: 'forwards' })
				: null;
			const inc = { ph, key, v, anim, frontAnim };
			ban.incoming = inc;
			anim.finished.then(() => {
				if (ban.incoming === inc) {
					ban.incoming = null;
					promote(inc);
				}
			}).catch(() => {});
			if (!empty && visCap && typeof visCap.animate === 'function') {
				visCap.animate([{ opacity: 0 }, { opacity: 1 }], { duration: FADE_MS, easing: 'ease' });
			}
		}

		// r = { v: visual for the caption, img: photo url or '' (neutral), slot, check: verify shape after load }
		function showContent(r) {
			// Every no-image state looks the same (no photo, no caption), so they share one key.
			const key = r.img ? `${r.slot}|${visualKey({ ...r.v, img: r.img })}` : 'empty';
			if (key === ban.wantKey) return; // already visible or on its way
			ban.wantKey = key;
			const gen = ++ban.gen;
			settleIncoming();
			if (key === ban.frontKey) return;

			const ph = layers.find((l) => l !== ban.front);
			if (!ph) return;
			const current = () => gen === ban.gen;
			const ready = r.img ? loadInto(ph, r.img) : Promise.resolve();
			ready
				.then(() => {
					if (!current()) return;
					if (r.img) {
						const im = ph.querySelector('img');
						aspects.set(r.img, im.naturalWidth / im.naturalHeight);
						if (r.check && !fitsSlot(aspects.get(r.img), r.slot)) {
							ban.wantKey = ban.frontKey; // parent photo does not suit this slot: resolve again
							syncBanner();
							return;
						}
					}
					// Prepare the hidden layer, then fade it in.
					if (ph.dataset.slot !== r.slot) ph.dataset.slot = r.slot;
					if (ph.classList.contains('is-empty') !== !r.img) ph.classList.toggle('is-empty', !r.img);
					commit(ph, r.v, key);
				})
				.catch(() => {
					if (!current()) return;
					// 404 or undecodable: never retry it; fall back to the next candidate or the neutral banner.
					badImg.add(r.img);
					ban.wantKey = ban.frontKey;
					syncBanner();
				});
		}

		function linkVisual(link) {
			if (!link || !link.dataset.visual) return null;
			const p = panelOf(link);
			if (!p || (p.id !== 'p-root' && !stack.includes(p.id))) return null;
			try {
				return JSON.parse(link.dataset.visual);
			} catch (e) {
				return null;
			}
		}

		function panelVisual(id) {
			const trig = hm.querySelector(`[data-target="${id}"][data-visual]`);
			const panel = document.getElementById(id);
			for (const el of [trig, panel]) {
				if (el && el.dataset.visual) {
					try {
						return JSON.parse(el.dataset.visual);
					} catch (e) {}
				}
			}
			return null;
		}

		/* The one decision for what the banner shows.
		 * 1. The hovered item's own visual, else the visual of the item that opened the top panel:
		 *    both are configured for the slot on screen, so their photo is used as is (strict).
		 * 2. Visuals of panels further up: their photo only if its shape suits the slot.
		 * 3. No usable photo (none configured, failed, or wrong shape): the no-image layer, styled by the
		 *    admin setting "Background when no banner image is available". */
		function resolveBanner() {
			const slot = slotClass();
			const list = [];
			const own = linkVisual(currentHoveredLink);
			// Strict only in the slot it was designed for (e.g. hovering an open L0 item while its L2 is open
			// keeps two panels open, so its medium photo is checked for shape instead of forced into narrow).
			if (own) list.push({ v: own, strict: designSlot(currentHoveredLink) === slot });
			for (let i = stack.length - 1; i >= 0; i--) {
				const pv = panelVisual(stack[i]);
				if (pv) list.push({ v: pv, strict: !own && i === stack.length - 1 });
			}
			for (const c of list) {
				const img = typeof c.v.img === 'string' ? c.v.img : '';
				if (!img || badImg.has(img)) continue;
				if (c.strict) return { v: c.v, img, slot };
				const aspect = aspects.get(img);
				if (aspect === undefined) return { v: c.v, img, slot, check: true };
				if (fitsSlot(aspect, slot)) return { v: c.v, img, slot };
			}
			return { v: {}, img: '', slot };
		}

		function syncBanner() {
			if (!vis) return;
			const r = isOpen && !mqMobile.matches ? resolveBanner() : null;
			if (!r || !hasRoom()) {
				closePane();
				return;
			}
			ban.paneWanted = true;
			showContent(r);
			if (ban.frontKey && (ban.frontKey === ban.wantKey || ban.incoming)) {
				openPane();
			}
		}

		/* Image prewarm through the browser cache: the hovered item's photo starts loading on pointerover
		 * (before the 160ms intent timer), and the open panel's photos load one per idle slice.
		 * Pointer activity in the last 250ms or a running crossfade postpones the next slice. */
		const ric = window.requestIdleCallback
			? (fn) => window.requestIdleCallback(fn, { timeout: 2000 })
			: (fn) => setTimeout(fn, 200);
		const warmed = new Map(); // url -> Promise settled when the photo is fetched and decoded
		let warmQueue = [];
		let warmScheduled = false;
		let warmPanelId = '';
		let lastPointerAt = 0;

		function warmImage(url) {
			if (!url || warmed.has(url) || badImg.has(url)) return;
			const im = new Image();
			im.decoding = 'async';
			im.src = url;
			const done = typeof im.decode === 'function'
				? im.decode()
				: new Promise((resolve, reject) => {
					im.onload = resolve;
					im.onerror = reject;
				});
			warmed.set(url, done.then(
				() => aspects.set(url, im.naturalWidth / im.naturalHeight),
				() => badImg.add(url)
			));
		}

		function warmPanel(id) {
			if (!vis || !id || id === warmPanelId || mqMobile.matches || !finePointer.matches) return;
			warmPanelId = id;
			const p = document.getElementById(id);
			if (!p) return;
			const urls = [];
			const own = id === 'p-root' ? null : panelVisual(id);
			if (own && own.img) urls.push(own.img);
			p.querySelectorAll('.hm-link[data-visual]').forEach((el) => {
				try {
					const v = JSON.parse(el.dataset.visual);
					if (v.img) urls.push(v.img);
				} catch (e) {}
			});
			warmQueue = urls.filter((u, i) => urls.indexOf(u) === i && !warmed.has(u));
			scheduleWarm();
		}

		function scheduleWarm() {
			if (warmScheduled || !warmQueue.length) return;
			warmScheduled = true;
			ric(warmStep);
		}

		function warmStep() {
			warmScheduled = false;
			if (!isOpen || !warmQueue.length) return;
			if (performance.now() - lastPointerAt < 250 || ban.incoming) {
				warmScheduled = true;
				setTimeout(() => ric(warmStep), 250);
				return;
			}
			const url = warmQueue.shift();
			warmImage(url);
			(warmed.get(url) || Promise.resolve()).then(scheduleWarm);
		}

		/* ---------- 3. Drawer State Machine & Rendering ---------- */
		function render() {
			hm.querySelectorAll('.hm-panel:not(#p-root)').forEach((p) => {
				const k = stack.indexOf(p.id);
				p.classList.toggle('open', k > -1);
				p.classList.toggle('behind', k > -1 && k < stack.length - 1);
				p.toggleAttribute('inert', k === -1 || (mqMobile.matches && k < stack.length - 1));
			});

			root.classList.toggle('behind', stack.length > 0);
			root.toggleAttribute('inert', mqMobile.matches && stack.length > 0);

			hm.querySelectorAll('[data-target]').forEach((b) => {
				b.setAttribute('aria-expanded', stack.includes(b.dataset.target) || pendingTarget === b.dataset.target);
			});

			if (vis && !mqMobile.matches) {
				// Caption offset follows the panels; written only when the depth changes.
				const depth = String(stack.length);
				if (vis.style.getPropertyValue('--depth') !== depth) {
					vis.style.setProperty('--depth', depth);
				}
			}
			syncBanner();
			if (isOpen && !mqMobile.matches) {
				warmPanel(stack.length ? stack[stack.length - 1] : 'p-root');
			}
		}

		function firstLink(p) {
			return p ? p.querySelector('.hm-scroll a, .hm-scroll button') : null;
		}

		function focusLater(el) {
			if (el) {
				setTimeout(() => el.focus({ preventScroll: true }), mqMobile.matches ? 60 : 0);
			}
		}

		function applyOpenPanel(id, moveFocus) {
			pendingTarget = null;
			const lvl = lvlOf(id);
			if (isNaN(lvl) || lvl < 1) return;

			// Truncate stack to (lvl - 1) and append this id
			stack = stack.slice(0, lvl - 1).concat(id);

			const p = document.getElementById(id);
			if (p) {
				const scroll = p.querySelector('.hm-scroll');
				if (scroll) scroll.scrollTop = 0;
				paintAll(p);
			}

			render();

			if (moveFocus && p) {
				focusLater(firstLink(p));
			}
		}

		function openPanel(id, moveFocus) {
			const lvl = lvlOf(id);
			if (isNaN(lvl) || lvl < 1) return;

			clearTimeout(switchT);
			pendingTarget = null;
			applyOpenPanel(id, moveFocus);
		}

		function back(focus = true) {
			clearTimeout(switchT);
			pendingTarget = null;
			const id = stack.pop();
			render();
			if (focus && id) {
				focusLater(trig(id));
			}
		}

		function openMenu(target) {
			if (isOpen) {
				return target && openPanel(target, true);
			}
			isOpen = true;
			lastFocus = document.activeElement;
			closeSearch(false);

			hm.classList.add('on');
			scrim.classList.add('on');
			document.body.classList.add('hm-lock');

			paintAll(hm);
			stack = [];
			measurePanel();
			render();

			if (target) {
				openPanel(target, true);
			} else {
				focusLater(root.querySelector('[data-close]'));
			}
		}

		function closeMenu() {
			if (!isOpen) return;
			clearTimeout(switchT);
			clearTimeout(hoverT);
			currentHoveredLink = null;
			hoverEl = null;
			closePane(true);
			pendingTarget = null;
			isOpen = false;
			stack = [];
			render();

			hm.classList.remove('on');
			scrim.classList.remove('on');
			document.body.classList.remove('hm-lock');

			if (lastFocus && document.contains(lastFocus) && typeof lastFocus.focus === 'function') {
				lastFocus.focus({ preventScroll: true });
			}
		}

		/* ---------- 4. Event Delegation in Drawer ---------- */
		hm.addEventListener('click', (e) => {
			const t = e.target.closest('[data-target],[data-back],[data-close]');
			if (!t) return;

			if (t.matches('[data-close]')) {
				return closeMenu();
			}

			if (t.matches('[data-back]')) {
				return back();
			}

			const id = t.dataset.target;
			const already = stack.includes(id);

			// Desktop: a click on a branch hover already opened keeps it open (no flicker), and moves keyboard focus in.
			if (already && !mqMobile.matches) {
				if (e.detail === 0) {
					focusLater(firstLink(document.getElementById(id)));
				}
				return;
			}

			openPanel(id, mqMobile.matches || e.detail === 0);
		});

		// Desktop hover intent: one 160ms timer drives both the panel cascade and the banner,
		// so the banner reveal starts in the same frame as the panel it belongs to.
		hm.addEventListener('pointerover', (e) => {
			if (!finePointer.matches || mqMobile.matches) return;
			const el = e.target.closest('[data-target], .hm-link, .hm-promo, .hm-foot');
			if (!el || el === hoverEl) return;
			hoverEl = el;
			lastPointerAt = performance.now();
			const link = el.classList.contains('hm-link') ? el : null;
			if (link && link.dataset.visual) {
				try {
					warmImage(JSON.parse(link.dataset.visual).img);
				} catch (e) {}
			}
			clearTimeout(hoverT);
			hoverT = setTimeout(() => {
				currentHoveredLink = link;
				if (el.matches('[data-target]')) {
					const id = el.dataset.target;
					if (!stack.includes(id)) {
						openPanel(id, false);
						return;
					}
				} else {
					const p = panelOf(el);
					const curLvl = p ? (p.id === 'p-root' ? 0 : lvlOf(p.id)) : 0;
					if (stack.length > curLvl || pendingTarget) {
						clearTimeout(switchT);
						pendingTarget = null;
						stack = stack.slice(0, curLvl);
					}
				}
				render();
			}, 160);
		});

		hm.addEventListener('pointerout', (e) => {
			if (!finePointer.matches || mqMobile.matches) return;
			const to = e.relatedTarget;
			if (hoverEl && to && hoverEl.contains(to)) return; // still inside the same item
			clearTimeout(hoverT);
			hoverEl = null;
			const toDrawer = to && to.closest ? to.closest('.hm') : null;
			if (!toDrawer && stack.length === 0) {
				currentHoveredLink = null;
				render();
			}
		});

		let resizeT = null;
		window.addEventListener('resize', () => {
			if (isOpen) {
				measurePanel();
				render();
			}
			clearTimeout(resizeT);
			resizeT = setTimeout(() => {
				if (isOpen) measurePanel();
			}, 300);
		});

		mqMobile.addEventListener('change', () => {
			if (isOpen) render();
		});

		/* ---------- 5. Header Triggers & Scrim Click ---------- */
		scrim.addEventListener('click', () => {
			closeMenu();
			closeSearch();
		});

		function bindHamburgerTriggers() {
			const triggers = document.querySelectorAll(
				'.dmm-menu-trigger, [data-dmm-trigger], .dmm-hamburger-btn, [data-open="#main-menu"], [data-open="#menu"], .menu-icon a, .nav-icon a, a[href="#menu"], a[href="#main-menu"]'
			);
			triggers.forEach((btn) => {
				btn.removeAttribute('data-open');
				btn.setAttribute('data-dmm-open', '1');
				btn.addEventListener('click', (e) => {
					e.preventDefault();
					e.stopPropagation();
					if (isOpen) {
						closeMenu();
					} else {
						openMenu();
					}
				});
			});
		}
		bindHamburgerTriggers();

		/* ---------- 6. Search Sheet Modal Controller ---------- */
		function openSearch() {
			closeMenu();
			if (!srch) return;
			srch.classList.add('on');
			scrim.classList.add('on');
			document.body.classList.add('hm-lock');
			const input = srch.querySelector('input[type="search"]');
			if (input) {
				setTimeout(() => input.focus(), 150);
			}
		}

		function closeSearch(restore = true) {
			if (!srch) return;
			srch.classList.remove('on');
			if (!isOpen) {
				scrim.classList.remove('on');
				document.body.classList.remove('hm-lock');
			}
		}

		const searchTriggers = document.querySelectorAll('.dmm-search-trigger, .header-search a');
		searchTriggers.forEach((btn) => {
			btn.addEventListener('click', (e) => {
				e.preventDefault();
				e.stopPropagation();
				openSearch();
			});
		});

		const srchClose = document.getElementById('srchClose');
		if (srchClose) {
			srchClose.addEventListener('click', () => closeSearch());
		}

		/* ---------- 7. Keyboard Navigation (Esc, Arrows, Tab Trap) ---------- */
		hm.addEventListener('keydown', (e) => {
			const cur = document.activeElement;
			const p = panelOf(cur);

			if (e.key === 'Escape') {
				e.preventDefault();
				return stack.length ? back() : closeMenu();
			}

			if (e.key === 'Tab') {
				const f = [...hm.querySelectorAll('a,button')].filter(
					(el) => !el.closest('[inert]') && el.offsetParent !== null
				);
				if (!f.length) return;
				if (e.shiftKey && cur === f[0]) {
					e.preventDefault();
					f[f.length - 1].focus();
				} else if (!e.shiftKey && cur === f[f.length - 1]) {
					e.preventDefault();
					f[0].focus();
				}
				return;
			}

			if (!p || !cur.classList.contains('hm-link')) return;
			const links = [...p.querySelectorAll('.hm-link')];
			const k = links.indexOf(cur);

			if (e.key === 'ArrowDown') {
				e.preventDefault();
				links[(k + 1) % links.length].focus();
			}
			if (e.key === 'ArrowUp') {
				e.preventDefault();
				links[(k - 1 + links.length) % links.length].focus();
			}
			if (e.key === 'Home') {
				e.preventDefault();
				links[0].focus();
			}
			if (e.key === 'End') {
				e.preventDefault();
				links[links.length - 1].focus();
			}
			if (e.key === 'ArrowRight' && cur.dataset.target) {
				e.preventDefault();
				openPanel(cur.dataset.target, true);
			}
			if (e.key === 'ArrowLeft' && p.id !== 'p-root') {
				e.preventDefault();
				const id = p.id;
				stack = stack.slice(0, stack.indexOf(id));
				render();
				focusLater(trig(id));
			}
		});

		window.addEventListener('keydown', (e) => {
			if (e.key === 'Escape') {
				if (srch && srch.classList.contains('on')) {
					closeSearch();
					return;
				}
				if (isOpen) {
					if (mqMobile.matches && stack.length > 0) {
						back();
					} else {
						closeMenu();
					}
				}
			}
		});

		window.DMM_Drawer = {
			open: openMenu,
			close: closeMenu,
			openPanel: openPanel,
			isOpen: () => isOpen,
		};
	});
})();
