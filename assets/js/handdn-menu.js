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
		let leafT = null;
		let visT = null;
		let currentHoveredLink = null;
		let visKey = '';
		let front = 'a';

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
			const tex = (v && v.tex) ? v.tex : (el.dataset.tex || 'grain');
			const tone = (v && v.tone) ? v.tone : (el.dataset.tone || '#3b2416');
			const bgSwatch = `url(${swatch(tex, tone)})`;

			if (el.dataset.img || (v && v.img)) {
				const src = el.dataset.img || v.img;
				el.style.backgroundImage = 'none';
				el.style.backgroundColor = '#1a1816';
				el.innerHTML = `<img src="${src}" alt="" decoding="async" onerror="this.style.display='none'">`;
				return;
			}
			el.innerHTML = '';
			el.style.backgroundImage = bgSwatch;
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

		/* ---------- 2. Editorial Visual Pane Controller ---------- */
		const visA = vis ? vis.querySelector('a') : null;
		const visCap = vis ? vis.querySelector('.cap') : null;

		function setVisual(v, key) {
			if (!vis || !visA || !visCap || !v) return;
			visKey = key;

			// Clear both layers so old images never linger or flash
			vis.querySelectorAll('.ph').forEach((ph) => {
				ph.classList.add('out');
				ph.innerHTML = '';
				ph.style.backgroundImage = 'none';
			});

			const next = front === 'a' ? 'b' : 'a';
			const nEl = vis.querySelector(`[data-layer="${next}"]`);
			front = next;

			paint(nEl, v);
			if (nEl) nEl.classList.remove('out');

			const kEl = visCap.querySelector('.k');
			const bEl = visCap.querySelector('b');
			const goEl = visCap.querySelector('.go');
			if (kEl) kEl.textContent = v.k || '';
			if (bEl) bEl.textContent = v.t || '';
			if (goEl) goEl.textContent = v.go || 'Shop';

			visA.href = v.href || '#';
		}

		function getPromoVisual() {
			const promoEl = root.querySelector('.hm-promo');
			if (promoEl) {
				const ph = promoEl.querySelector('.ph');
				return {
					tex: ph?.dataset?.tex || 'grain',
					tone: ph?.dataset?.tone || '#8a4a26',
					img: ph?.dataset?.img || '',
					k: promoEl.querySelector('.k')?.textContent || '3D Custom',
					t: promoEl.querySelector('b')?.textContent || 'Design your strap in 3D',
					go: promoEl.querySelector('.go')?.textContent || 'Start designing',
					href: promoEl.getAttribute('href') || '#',
				};
			}
			return {
				tex: 'grain',
				tone: '#8a4a26',
				k: '3D Custom',
				t: 'Design your strap in 3D',
				go: 'Start designing',
				href: '#',
			};
		}

		function getVisualForId(id) {
			if (!id || id === 'p-root') {
				return getPromoVisual();
			}

			// 1. Trigger button or Panel element explicit data-visual
			const trigBtn = hm.querySelector(`[data-target="${id}"][data-visual]`);
			if (trigBtn && trigBtn.dataset.visual) {
				try {
					return JSON.parse(trigBtn.dataset.visual);
				} catch (e) {}
			}

			const panel = document.getElementById(id);
			if (panel && panel.dataset.visual) {
				try {
					return JSON.parse(panel.dataset.visual);
				} catch (e) {}
			}
			if (panel) {
				const feat = panel.querySelector('.feature');
				if (feat) {
					const ph = feat.querySelector('.ph');
					return {
						tex: ph?.dataset?.tex || 'smooth',
						tone: ph?.dataset?.tone || '#6b3b20',
						img: ph?.dataset?.img || '',
						k: feat.querySelector('.k')?.textContent || '',
						t: feat.querySelector('p')?.textContent || '',
						go: feat.querySelector('.go')?.textContent || 'Discover',
						href: feat.getAttribute('href') || '#',
					};
				}
			}

			// 2. Fallback to parent panel in stack if current panel has no custom visual
			const currentIdx = stack.indexOf(id);
			if (currentIdx > 0) {
				const parentId = stack[currentIdx - 1];
				const parentVis = getVisualForId(parentId);
				if (parentVis) return parentVis;
			}

			return getPromoVisual();
		}

		function hideVisualInstant() {
			clearTimeout(leafT);
			clearTimeout(visT);
			if (vis) {
				vis.classList.remove('on');
				vis.querySelectorAll('.ph').forEach((ph) => {
					ph.classList.add('out');
					ph.innerHTML = '';
					ph.style.backgroundImage = 'none';
				});
				const kEl = visCap ? visCap.querySelector('.k') : null;
				const bEl = visCap ? visCap.querySelector('b') : null;
				const goEl = visCap ? visCap.querySelector('.go') : null;
				if (kEl) kEl.textContent = '';
				if (bEl) bEl.textContent = '';
				if (goEl) goEl.textContent = '';
			}
			visKey = '';
			currentHoveredLink = null;
		}

		function updateVisualPosition() {
			if (!vis) return;
			const open = stack.length + 1;
			const panelEl = hm.querySelector('.hm-panel');
			const panelW = panelEl ? panelEl.getBoundingClientRect().width : 380;
			const left = open * panelW;
			vis.style.setProperty('--vis-left', left + 'px');
		}

		function showVisualForPanel(id) {
			if (!vis || !isOpen || mqMobile.matches) return;
			const v = getVisualForId(id);
			if (!v) return;

			const open = stack.length + 1;
			const panelEl = hm.querySelector('.hm-panel');
			const panelW = panelEl ? panelEl.getBoundingClientRect().width : 380;
			const left = open * panelW;
			const room = window.innerWidth - left;
			if (room < 220) return;

			vis.style.setProperty('--vis-left', left + 'px');
			if (visKey !== id || !vis.classList.contains('on')) {
				setVisual(v, id);
				vis.classList.add('on');
			}
		}

		function getVisualTargetForLink(link, p) {
			if (!link || !p) return null;

			if (p.id === 'p-root') {
				if (link.dataset.visual) {
					try {
						return {
							v: JSON.parse(link.dataset.visual),
							key: link.getAttribute('href') || link.textContent.trim(),
							isCustom: true,
						};
					} catch (e) {}
				}
				return null;
			}

			// Inside sub-panel (Level 1, Level 2, ...)
			if (link.dataset.visual) {
				try {
					return {
						v: JSON.parse(link.dataset.visual),
						key: link.getAttribute('href') || link.textContent.trim(),
						isCustom: true,
					};
				} catch (e) {}
			}

			// Item does NOT have custom visual: takes parent's visual!
			const parentVis = getVisualForId(p.id);
			if (parentVis) {
				return {
					v: parentVis,
					key: p.id,
					isCustom: false,
				};
			}

			return null;
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

			updateVisualPosition();

			if (stack.length === 0) {
				hideVisualInstant();
			} else if (!mqMobile.matches && isOpen) {
				// Display the topmost open panel's visual banner promptly if no custom item visual is active
				const topId = stack[stack.length - 1];
				if (!currentHoveredLink || !currentHoveredLink.dataset.visual) {
					showVisualForPanel(topId);
				}
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
			hideVisualInstant();
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
			hideVisualInstant();
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

		// Desktop hover intent: cascade panels and visual banner smoothly (160ms responsive delay, 0s instant out)
		hm.addEventListener('pointerover', (e) => {
			if (!finePointer.matches || mqMobile.matches) return;

			// 1. Branch button / panel cascading
			const b = e.target.closest('[data-target]');
			const a = e.target.closest('.hm-link, .hm-promo, .hm-foot');
			clearTimeout(hoverT);

			if (b) {
				const targetId = b.dataset.target;
				if (!stack.includes(targetId)) {
					hoverT = setTimeout(() => openPanel(targetId, false), 160);
				}
			} else if (a) {
				const p = panelOf(a);
				const curLvl = p ? (p.id === 'p-root' ? 0 : lvlOf(p.id)) : 0;
				if (stack.length > curLvl || pendingTarget) {
					hoverT = setTimeout(() => {
						clearTimeout(switchT);
						pendingTarget = null;
						stack = stack.slice(0, curLvl);
						render();
					}, 160);
				}
			}

			// 2. Visual banner hover synchronization
			const link = e.target.closest('.hm-link');
			if (!link) {
				return;
			}

			const p = panelOf(link);
			if (!p) return;

			const target = getVisualTargetForLink(link, p);
			if (!target) return;

			// If target visual has the SAME key as what is currently showing:
			// (e.g. moving between items that both use the parent's visual banner)
			if (visKey === target.key && vis && vis.classList.contains('on')) {
				// DO NOT HIDE! DO NOT RESET! DO NOT FLICKER!
				currentHoveredLink = link;
				clearTimeout(leafT);
				return;
			}

			// If switching to a DIFFERENT visual:
			currentHoveredLink = link;
			clearTimeout(leafT);

			leafT = setTimeout(() => {
				if (!isOpen || currentHoveredLink !== link) return;
				const open = stack.length + 1;
				const panelEl = hm.querySelector('.hm-panel');
				const panelW = panelEl ? panelEl.getBoundingClientRect().width : 380;
				const left = open * panelW;
				const room = window.innerWidth - left;
				if (room < 220) return;

				vis.style.setProperty('--vis-left', left + 'px');
				setVisual(target.v, target.key);
				vis.classList.add('on');
			}, 140);
		});

		hm.addEventListener('pointerout', (e) => {
			if (!finePointer.matches || mqMobile.matches) return;
			clearTimeout(hoverT);

			const toDrawer = e.relatedTarget ? e.relatedTarget.closest('.hm') : null;
			if (!toDrawer && stack.length === 0) {
				hideVisualInstant();
			}
		});

		window.addEventListener('resize', () => {
			if (isOpen) render();
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
