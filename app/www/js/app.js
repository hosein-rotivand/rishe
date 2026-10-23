/**
 * Rishe mobile app: views, navigation, audio and lesson flow.
 * Plain JS (ES2017) so it runs on older Android WebViews.
 */
(function () {
	'use strict';

	var risheApi = window.RisheApi;
	var risheView = document.getElementById('rishe-view');
	var risheTabbar = document.getElementById('rishe-tabbar');

	/* ---------- helpers ---------- */

	var FA_DIGITS = '۰۱۲۳۴۵۶۷۸۹';
	function fa(value) { return String(value).replace(/\d/g, function (d) { return FA_DIGITS[d]; }); }
	function esc(value) {
		return String(value == null ? '' : value).replace(/[&<>"']/g, function (c) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
		});
	}
	function money(toman) { return fa(Number(toman).toLocaleString('en-US').replace(/,/g, '٬')) + ' تومان'; }
	function firstName(name) { return String(name || '').split(' ')[0]; }

	var ICONS = {
		home: '<path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V20h14V9.5"/><path d="M10 20v-6h4v6"/>',
		book: '<path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H20v15H6.5A2.5 2.5 0 0 0 4 20.5z"/><path d="M4 20.5A2.5 2.5 0 0 1 6.5 18H20v3H6.5"/>',
		award: '<circle cx="12" cy="9" r="6"/><path d="m8.5 14-1.5 7 5-3 5 3-1.5-7"/>',
		user: '<circle cx="12" cy="8" r="4"/><path d="M4 21c1.5-4 4.5-6 8-6s6.5 2 8 6"/>',
		play: '<path d="M7 4.5v15l12-7.5z" fill="currentColor"/>',
		back: '<path d="m9 6 6 6-6 6"/>',
		close: '<path d="M6 6l12 12M18 6 6 18"/>',
		chev: '<path d="m6 9 6 6 6-6"/>',
		check: '<path d="m5 12.5 4.5 4.5L19 7.5"/>',
		lock: '<rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/>',
		volume: '<path d="M4 9.5h3.5L12 5.5v13l-4.5-4H4z"/><path d="M15.5 9a4 4 0 0 1 0 6M18 6.5a7.5 7.5 0 0 1 0 11"/>',
		slow: '<circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/>',
		mic: '<rect x="9" y="3" width="6" height="11" rx="3"/><path d="M5.5 11a6.5 6.5 0 0 0 13 0M12 17.5V21"/>',
		text: '<path d="M4 6h16M4 12h10M4 18h13"/>',
		star: '<path d="m12 3.5 2.6 5.3 5.9.9-4.3 4.1 1 5.8-5.2-2.7-5.2 2.7 1-5.8-4.3-4.1 5.9-.9z"/>',
		edit: '<path d="M4 20h4L19 9l-4-4L4 16z"/>',
		moon: '<path d="M20 14.5A8 8 0 1 1 9.5 4a6.5 6.5 0 0 0 10.5 10.5z"/>',
		logout: '<path d="M15 4h4v16h-4M10 8l-4 4 4 4M6 12h10"/>',
		card: '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18"/>',
		link: '<path d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1"/><path d="M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1"/>',
		copy: '<rect x="8" y="8" width="12" height="12" rx="2"/><path d="M16 8V5a1 1 0 0 0-1-1H5a1 1 0 0 0-1 1v10a1 1 0 0 0 1 1h3"/>',
		refresh: '<path d="M20 11a8 8 0 1 0-2.3 5.7M20 4v7h-7"/>',
		root: '<path d="M12 3v8"/><path d="M12 11c0 4-4 4-4 8M12 11c0 4 4 4 4 8M12 11v10"/><circle cx="12" cy="4" r="1.6" fill="currentColor"/>'
	};
	function ic(name) { return '<svg class="rishe-i" viewBox="0 0 24 24" aria-hidden="true">' + (ICONS[name] || '') + '</svg>'; }

	var toastTimer = null;
	function toast(message) {
		var el = document.getElementById('rishe-toast');
		el.textContent = message;
		el.classList.add('is-show');
		clearTimeout(toastTimer);
		toastTimer = setTimeout(function () { el.classList.remove('is-show'); }, 2600);
	}

	function isNative() {
		return !!(window.Capacitor && window.Capacitor.isNativePlatform && window.Capacitor.isNativePlatform());
	}

	// In the Android app, navigating to an external URL opens the system browser.
	function openExternal(url) {
		if (!/^https?:\/\//i.test(url)) { return; }
		if (isNative()) { window.location.href = url; } else { window.open(url, '_blank', 'noopener'); }
	}

	function applyTheme() {
		var theme = risheApi.store.get('rishe_theme') === 'dark' ? 'dark' : 'light';
		document.documentElement.setAttribute('data-theme', theme);
		var meta = document.querySelector('meta[name="theme-color"]');
		if (meta) { meta.setAttribute('content', theme === 'dark' ? '#0C1311' : '#0D6B4E'); }
	}

	/* ---------- audio ---------- */

	var player = {
		audio: null,
		onStop: null,
		stop: function () {
			if (this.audio) { this.audio.pause(); this.audio = null; }
			try { if ('speechSynthesis' in window) { window.speechSynthesis.cancel(); } } catch (e) { /* not supported */ }
			if (this.onStop) { this.onStop(); this.onStop = null; }
		},
		/**
		 * Play an uploaded file; fall back to text-to-speech when no file exists.
		 */
		play: function (url, opts) {
			opts = opts || {};
			this.stop();
			var self = this;
			if (opts.onStop) { this.onStop = opts.onStop; }
			if (url) {
				var a = new Audio(url);
				a.playbackRate = opts.rate || 1;
				if ('preservesPitch' in a) { a.preservesPitch = true; }
				a.addEventListener('ended', function () { if (self.audio === a) { self.stop(); } });
				if (opts.onTime) { a.addEventListener('timeupdate', function () { opts.onTime(a.currentTime, a.duration || 0); }); }
				this.audio = a;
				a.play().catch(function () { self.stop(); toast('پخش صدا ممکن نشد. اتصال اینترنت را بررسی کنید.'); });
				return true;
			}
			if (opts.text && 'speechSynthesis' in window && window.SpeechSynthesisUtterance) {
				try {
					var u = new SpeechSynthesisUtterance(opts.text);
					u.lang = 'en-US';
					u.rate = (opts.rate || 1) < 1 ? 0.6 : 0.95;
					u.onend = function () { self.stop(); };
					window.speechSynthesis.speak(u);
					return true;
				} catch (e) { /* fall through */ }
			}
			this.stop();
			toast('فایل صوتی این بخش هنوز آپلود نشده است.');
			return false;
		}
	};

	/* ---------- state & navigation ---------- */

	var state = {
		config: { sms_enabled: false, payments_enabled: false, certificate_fee: 0, app_name: 'ریشه' },
		user: null,
		view: 'boot',
		params: {},
		data: null,
		navId: 0,
		auth: { mode: 'login', step: 1, error: '', mobile: '', pending: null },
		lesson: null,
		exam: null,
		openChapter: null
	};

	var depth = 0;

	function go(view, params, opts) {
		var replace = !!(opts && opts.replace);
		if (!replace) { depth++; }
		var entry = { view: view, params: params || {}, depth: depth };
		if (replace) { history.replaceState(entry, ''); } else { history.pushState(entry, ''); }
		show(entry);
	}

	function show(entry) {
		player.stop();
		state.view = entry.view;
		state.params = entry.params || {};
		state.data = null;
		render();
		risheView.scrollTop = 0;
	}

	window.addEventListener('popstate', function (e) {
		if (e.state && e.state.view) {
			depth = e.state.depth || 0;
			show(e.state);
		}
	});

	function screen(inner, opts) {
		opts = opts || {};
		return '<section class="rishe-screen' + (opts.tabs ? ' has-tabs' : '') + (opts.cls ? ' ' + opts.cls : '') + '">' + inner + '</section>';
	}
	function topbar(title, back) {
		return '<div class="rishe-topbar">' + (back !== false ? '<button class="rishe-icon-btn" data-act="back" aria-label="بازگشت">' + ic('back') + '</button>' : '') + '<h2>' + esc(title || '') + '</h2></div>';
	}
	function loadingScreen(tabs) {
		return screen('<div class="rishe-center"><span class="rishe-spinner" aria-label="در حال بارگذاری"></span></div>', { tabs: tabs });
	}
	function errorScreen(err, tabs) {
		return screen(topbar('', true) + '<div class="rishe-center"><p class="rishe-muted">' + esc(err.message) + '</p><button class="rishe-btn rishe-btn-ghost rishe-btn-sm" style="width:auto;padding:0 18px" data-act="retry">' + ic('refresh') + 'تلاش دوباره</button></div>', { tabs: tabs });
	}

	function render() {
		var view = VIEWS[state.view] || VIEWS.home;
		var navId = ++state.navId;
		paintTabs(view.tab);
		if (view.guest === false && !risheApi.hasToken()) { go('welcome', {}, { replace: true }); return; }
		if (view.guest === true && risheApi.hasToken()) { go('home', {}, { replace: true }); return; }
		if (view.load) {
			risheView.innerHTML = loadingScreen(!!view.tab);
			view.load(state.params).then(function (data) {
				if (navId !== state.navId) { return; }
				state.data = data;
				paint();
			}, function (err) {
				if (navId !== state.navId) { return; }
				if (err.status === 401) { logoutLocal(); return; }
				risheView.innerHTML = errorScreen(err, !!view.tab);
			});
		} else {
			paint();
		}
	}

	function paint() {
		var view = VIEWS[state.view];
		var top = risheView.firstElementChild ? risheView.firstElementChild.scrollTop : 0;
		risheView.innerHTML = view.html(state.data, state.params);
		if (view.keepScroll && risheView.firstElementChild) { risheView.firstElementChild.scrollTop = top; }
	}

	function paintTabs(active) {
		risheTabbar.hidden = !active;
		if (!active) { return; }
		var tabs = [['home', 'خانه', 'home'], ['courses', 'دوره‌ها', 'book'], ['certs', 'مدارک', 'award'], ['profile', 'پروفایل', 'user']];
		risheTabbar.innerHTML = tabs.map(function (t) {
			return '<button class="' + (t[0] === active ? 'is-on' : '') + '" data-tab="' + t[0] + '">' + ic(t[2]) + t[1] + '</button>';
		}).join('');
	}

	function logoutLocal() {
		risheApi.clearToken();
		state.user = null;
		go('welcome', {}, { replace: true });
	}

	function onAuthenticated(res) {
		risheApi.setToken(res.token);
		state.user = res.user;
		state.auth = { mode: 'login', step: 1, error: '', mobile: '', pending: null };
		go('home', {}, { replace: true });
		toast('خوش آمدی ' + firstName(res.user.name));
	}

	function loadMe() {
		return risheApi.get('me').then(function (res) { state.user = res.user; return res.user; });
	}

	/* ---------- views ---------- */

	function courseCover(c) {
		return '<span class="rishe-cover is-' + esc(c.color || 'brand') + '">' + esc(c.code || '') + '</span>';
	}

	function courseItem(c) {
		var status = c.progress >= 100 ? 'کامل شده' : (c.progress > 0 ? '٪' + fa(c.progress) + ' پیشرفت' : 'شروع نشده');
		var premium = c.is_premium && !c.has_access ? '<span class="rishe-pill rishe-pill-amber">' + ic('lock') + 'ویژه</span>' : '';
		return '<button class="rishe-card rishe-course-item" data-go="course" data-id="' + c.id + '">' + courseCover(c) +
			'<span class="rishe-meta"><h4>' + esc(c.title) + premium + '</h4><span class="rishe-sub"><span>' + fa(c.hours) + ' ساعت</span><span>' + fa(c.lessons) + ' مرحله</span><span>' + status + '</span></span>' +
			'<span class="rishe-bar"><span style="width:' + c.progress + '%"></span></span></span></button>';
	}

	var VIEWS = {};

	VIEWS.boot = {
		html: function () { return loadingScreen(false); }
	};

	VIEWS.welcome = {
		guest: true,
		html: function () {
			return screen(
				'<div class="rishe-welcome-art">' +
					'<div class="rishe-logo"><span class="rishe-logo-mark">' + ic('root') + '</span><b>' + esc(state.config.app_name) + '</b></div>' +
					'<div class="rishe-tree">' +
						'<svg class="rishe-roots" viewBox="0 0 342 270" aria-hidden="true" fill="none" stroke="var(--rishe-line)" stroke-width="1.5"><path d="M171 140 C171 100 90 90 70 46"/><path d="M171 140 C171 100 250 90 272 48"/><path d="M171 140 C150 180 80 190 64 228"/><path d="M171 140 C195 185 255 195 270 230"/></svg>' +
						'<div class="rishe-chip" style="left:16px;top:24px"><b>meet</b><small>ملاقات کردن</small></div>' +
						'<div class="rishe-chip" style="right:18px;top:26px;animation-delay:-2s"><b>hello</b><small>سلام</small></div>' +
						'<div class="rishe-chip is-main"><b>Nice to meet you</b><small>از آشنایی‌تان خوشوقتم</small></div>' +
						'<div class="rishe-chip" style="left:10px;bottom:14px;animation-delay:-4s"><b>/naɪs/</b><small>نایس</small></div>' +
						'<div class="rishe-chip" style="right:14px;bottom:10px;animation-delay:-1s"><b>you</b><small>شما</small></div>' +
					'</div>' +
					'<h1>انگلیسی را <em>از ریشه</em> یاد بگیر</h1>' +
					'<p class="rishe-muted">هر عبارت با صدا، تلفظ فارسی و معنی. روزی چند دقیقه، و در پایان هر دوره مدرک معتبر.</p>' +
				'</div>' +
				'<div class="rishe-welcome-actions">' +
					'<button class="rishe-btn rishe-btn-primary" data-act="auth" data-mode="register">ساخت حساب رایگان</button>' +
					'<button class="rishe-btn rishe-btn-ghost" data-act="auth" data-mode="login">حساب دارم، وارد می‌شوم</button>' +
				'</div>', { cls: 'rishe-welcome' });
		}
	};

	VIEWS.auth = {
		guest: true,
		html: function () {
			var a = state.auth;
			var sms = state.config.sms_enabled;
			var titles = {
				login: ['خوش برگشتی', 'از همان جایی که ماندی ادامه بده.'],
				register: ['ساخت حساب', 'پیشرفت و مدرک‌هایت در حسابت ذخیره می‌شود.'],
				otp: ['ورود با کد پیامکی', 'کد یک‌بارمصرف به موبایلت فرستاده می‌شود.'],
				reset: ['بازیابی رمز', 'با کد پیامکی، رمز تازه بساز.']
			};
			var t = titles[a.mode];
			var err = a.error ? '<div class="rishe-form-err">' + esc(a.error) + '</div>' : '';
			var codeField = '<div class="rishe-field"><label for="rishe-code">کد تأیید</label><input class="rishe-input is-ltr is-code" id="rishe-code" name="code" inputmode="numeric" maxlength="5" autocomplete="one-time-code" required><small>کد ۵ رقمی به <span class="rishe-en">' + esc(a.mobile) + '</span> پیامک شد. <button type="button" class="rishe-link" data-act="auth-edit-mobile">ویرایش شماره</button></small></div>';
			var mobileField = '<div class="rishe-field"><label for="rishe-mobile">شماره موبایل</label><input class="rishe-input is-ltr" id="rishe-mobile" name="mobile" inputmode="tel" autocomplete="tel" placeholder="09xx xxx xxxx" value="' + esc(a.mobile) + '" required></div>';
			var form = '';

			if (a.mode === 'login') {
				form = mobileField +
					'<div class="rishe-field"><label for="rishe-password">رمز عبور</label><input class="rishe-input is-ltr" id="rishe-password" name="password" type="password" autocomplete="current-password" required></div>' +
					(sms ? '<div class="rishe-row"><button type="button" class="rishe-link" data-act="auth" data-mode="otp">ورود با کد پیامکی</button><button type="button" class="rishe-link" data-act="auth" data-mode="reset">فراموشی رمز</button></div>' : '') +
					err + '<button class="rishe-btn rishe-btn-primary" type="submit">ورود</button>';
			} else if (a.mode === 'register' && a.step === 1) {
				var p = a.pending || {};
				form = '<div class="rishe-field"><label for="rishe-name">نام و نام خانوادگی</label><input class="rishe-input" id="rishe-name" name="name" autocomplete="name" placeholder="مثلاً علی رضایی" value="' + esc(p.name || '') + '" required></div>' +
					'<div class="rishe-field"><label for="rishe-name-en">نام به انگلیسی</label><input class="rishe-input is-ltr" id="rishe-name-en" name="name_en" placeholder="Ali Rezaei" value="' + esc(p.name_en || '') + '" required><small>این نام روی مدرک پایان دوره چاپ می‌شود.</small></div>' +
					mobileField +
					'<div class="rishe-field"><label for="rishe-password">رمز عبور</label><input class="rishe-input is-ltr" id="rishe-password" name="password" type="password" autocomplete="new-password" minlength="6" required><small>حداقل ۶ کاراکتر</small></div>' +
					err + '<button class="rishe-btn rishe-btn-primary" type="submit">' + (sms ? 'دریافت کد تأیید' : 'ساخت حساب') + '</button>';
			} else if (a.mode === 'register') {
				form = codeField + err + '<button class="rishe-btn rishe-btn-primary" type="submit">تأیید و ساخت حساب</button>';
			} else if (a.step === 1) {
				form = mobileField + err + '<button class="rishe-btn rishe-btn-primary" type="submit">ارسال کد</button>';
			} else if (a.mode === 'otp') {
				form = codeField + err + '<button class="rishe-btn rishe-btn-primary" type="submit">ورود</button>';
			} else {
				form = codeField + '<div class="rishe-field"><label for="rishe-password">رمز تازه</label><input class="rishe-input is-ltr" id="rishe-password" name="password" type="password" autocomplete="new-password" minlength="6" required></div>' +
					err + '<button class="rishe-btn rishe-btn-primary" type="submit">ذخیره‌ی رمز و ورود</button>';
			}

			var seg = (a.mode === 'login' || a.mode === 'register') && a.step === 1
				? '<div class="rishe-seg" role="tablist"><button type="button" class="' + (a.mode === 'login' ? 'is-on' : '') + '" data-act="auth" data-mode="login">ورود</button><button type="button" class="' + (a.mode === 'register' ? 'is-on' : '') + '" data-act="auth" data-mode="register">عضویت</button></div>'
				: '';
			return screen(topbar('', true) +
				'<div class="rishe-pad"><div><h1 class="rishe-h1">' + t[0] + '</h1><p class="rishe-muted">' + t[1] + '</p></div>' + seg +
				'<form class="rishe-form" data-form="auth" novalidate>' + form + '</form></div>');
		}
	};

	VIEWS.home = {
		tab: 'home',
		guest: false,
		load: function () {
			return Promise.all([loadMe(), risheApi.get('courses')]).then(function (r) { return { user: r[0], courses: r[1].courses }; });
		},
		html: function (d) {
			var u = d.user;
			var courses = d.courses;
			var current = courses.filter(function (c) { return c.started && c.next; })[0] || courses.filter(function (c) { return c.next; })[0];
			var cont = '';
			if (current) {
				cont = '<div class="rishe-continue">' +
					'<div class="rishe-continue-row"><div><small>' + (current.started ? 'ادامه‌ی دوره‌ی ' : 'شروع دوره‌ی ') + esc(current.title) + ' · ' + esc(current.next.chapter_title) + '</small><h3>' + esc(current.next.title) + '</h3></div></div>' +
					'<div class="rishe-continue-row"><div class="rishe-bar" style="flex:1"><span style="width:' + current.progress + '%"></span></div><span class="rishe-num" style="font-size:12px;font-weight:700">٪' + fa(current.progress) + '</span></div>' +
					'<div class="rishe-continue-row"><span style="font-size:12.5px;opacity:.85">حدود ' + fa(current.next.minutes) + ' دقیقه</span><button class="rishe-go" data-go="lesson" data-id="' + current.next.id + '">' + ic('play') + (current.started ? 'ادامه' : 'شروع') + '</button></div>' +
				'</div>';
			} else if (courses.length) {
				cont = '<div class="rishe-card rishe-info">' + ic('award') + '<p>همه‌ی مرحله‌های دوره‌هایت را تمام کرده‌ای. از بخش مدارک، مدرکت را درخواست بده.</p><button class="rishe-btn rishe-btn-primary rishe-btn-sm" data-tab="certs">مدارک</button></div>';
			}
			return screen('<div class="rishe-pad">' +
				'<div class="rishe-hello"><div><h2>سلام ' + esc(firstName(u.name)) + '</h2><p>امروز چند دقیقه تمرین کن.</p></div><span class="rishe-avatar">' + esc(u.name.charAt(0)) + '</span></div>' +
				'<div class="rishe-stats">' +
					'<div class="rishe-card rishe-stat"><b class="rishe-num rishe-flame">' + fa(u.stats.streak) + ' روز</b><span>زنجیره‌ی تمرین</span></div>' +
					'<div class="rishe-card rishe-stat"><b class="rishe-num">' + fa(u.stats.xp.toLocaleString('en-US').replace(/,/g, '٬')) + '</b><span>امتیاز</span></div>' +
					'<div class="rishe-card rishe-stat"><b class="rishe-num">' + fa(u.stats.words) + '</b><span>عبارت آموخته</span></div>' +
				'</div>' + cont +
				'<div class="rishe-section-h"><h3>دوره‌ها</h3><button data-tab="courses">همه</button></div>' +
				'<div class="rishe-course-list">' + (courses.length ? courses.slice(0, 4).map(courseItem).join('') : '<p class="rishe-muted">هنوز دوره‌ای منتشر نشده است.</p>') + '</div>' +
			'</div>', { tabs: true });
		}
	};

	VIEWS.courses = {
		tab: 'courses',
		guest: false,
		load: function () { return risheApi.get('courses'); },
		html: function (d) {
			return screen('<div class="rishe-pad"><div><h1 class="rishe-h1">دوره‌ها</h1><p class="rishe-muted">هر دوره از چند فصل و هر فصل از چند مرحله‌ی کوتاه ساخته شده است.</p></div>' +
				'<div class="rishe-course-list">' + (d.courses.length ? d.courses.map(courseItem).join('') : '<p class="rishe-muted">هنوز دوره‌ای منتشر نشده است.</p>') + '</div></div>', { tabs: true });
		}
	};

	VIEWS.course = {
		tab: 'courses',
		guest: false,
		keepScroll: true,
		load: function (p) {
			return risheApi.get('courses/' + p.id).then(function (res) {
				var c = res.course;
				if (state.openChapter === null || state.openChapter.course !== c.id) {
					var idx = 0;
					c.chapters.forEach(function (ch, i) {
						ch.lessons.forEach(function (l) { if (l.state === 'current') { idx = i; } });
					});
					state.openChapter = { course: c.id, index: idx };
				}
				return c;
			});
		},
		html: function (c) {
			var premiumNote = c.is_premium && !c.has_access
				? '<div class="rishe-card rishe-info is-amber">' + ic('lock') + '<p>این دوره ویژه است. مرحله‌های رایگان را ببین یا با اشتراک همه را باز کن.</p><button class="rishe-btn rishe-btn-primary rishe-btn-sm" data-go="plans">اشتراک</button></div>'
				: '';
			var chapters = c.chapters.map(function (ch, i) {
				var open = state.openChapter && state.openChapter.index === i;
				return '<div class="rishe-card rishe-chapter' + (open ? ' is-open' : '') + '">' +
					'<button class="rishe-chapter-h" data-act="chapter" data-index="' + i + '" aria-expanded="' + open + '"><span class="rishe-chapter-n rishe-num">' + fa(i + 1) + '</span><span class="rishe-t"><h4>فصل ' + fa(i + 1) + ': ' + esc(ch.title) + '</h4><small>' + fa(ch.lessons.length) + ' مرحله</small></span><span class="rishe-chev">' + ic('chev') + '</span></button>' +
					(open ? '<div class="rishe-lessons">' + ch.lessons.map(function (l) {
						var st = l.state === 'open' ? 'current' : l.state;
						var icon = l.state === 'done' ? 'check' : (l.state === 'locked' ? 'lock' : (l.can_open ? 'play' : 'lock'));
						var tag = '';
						if (l.state === 'current') { tag = '<span class="rishe-pill rishe-pill-brand">' + (c.done ? 'ادامه' : 'شروع') + '</span>'; }
						if (!l.can_open && l.state !== 'locked') { tag = '<span class="rishe-pill rishe-pill-amber">ویژه</span>'; }
						if (l.is_free && c.is_premium && !c.has_access) { tag = '<span class="rishe-pill rishe-pill-mute">رایگان</span>'; }
						return '<button class="rishe-lesson-row is-' + st + '" data-act="lesson" data-id="' + l.id + '" data-state="' + l.state + '" data-open="' + (l.can_open ? 1 : 0) + '">' +
							'<span class="rishe-st">' + ic(icon) + '</span><span class="rishe-lt"><b>' + esc(l.title) + '</b><span>' + fa(l.phrases) + ' عبارت · ' + fa(l.minutes) + ' دقیقه</span></span>' + tag + '</button>';
					}).join('') + '</div>' : '') +
				'</div>';
			}).join('');

			var exam = '';
			if (c.exam.questions > 0) {
				var score = c.exam.best_score !== null ? 'بهترین نمره: ' + fa(c.exam.best_score) + ' از ۱۰۰' : 'حد نصاب: ' + fa(c.exam.pass_score) + ' از ۱۰۰';
				exam = '<div class="rishe-card rishe-info">' + ic('star') + '<p><b>آزمون پایانی</b> · ' + fa(c.exam.questions) + ' سؤال<br>' + score + '</p>' +
					(c.exam.available ? '<button class="rishe-btn rishe-btn-primary rishe-btn-sm" data-go="exam" data-id="' + c.id + '">' + (c.exam.best_score !== null ? 'دوباره' : 'شروع') + '</button>' : '<span class="rishe-pill rishe-pill-mute">' + ic('lock') + 'بعد از مراحل</span>') + '</div>';
			}
			var certText = c.certificate.status === 'approved' ? 'مدرک این دوره صادر شده است.'
				: (c.certificate.status ? 'درخواست مدرکت ثبت شده است.' : (c.certificate.eligible ? 'می‌توانی مدرک این دوره را درخواست بدهی.' : 'با تکمیل همه‌ی مراحل' + (c.exam.questions ? ' و قبولی در آزمون پایانی' : '') + '، مدرک پایان دوره را درخواست بده.'));
			var cert = '<div class="rishe-card rishe-info is-amber">' + ic('award') + '<p>' + certText + '</p>' + (c.certificate.status || c.certificate.eligible ? '<button class="rishe-btn rishe-btn-primary rishe-btn-sm" data-tab="certs">مدارک</button>' : '') + '</div>';

			return screen(topbar('جزئیات دوره') +
				'<div class="rishe-hero">' +
					'<div style="display:flex;gap:8px;flex-wrap:wrap">' + (c.level ? '<span class="rishe-pill rishe-pill-brand">سطح <span class="rishe-en">' + esc(c.level) + '</span></span>' : '') + '<span class="rishe-pill rishe-pill-mute">' + fa(c.chapters.length) + ' فصل</span></div>' +
					'<h1 class="rishe-h1">' + esc(c.title) + '</h1>' +
					(c.description ? '<p class="rishe-muted" style="font-size:13.5px">' + esc(c.description) + '</p>' : '') +
					'<div class="rishe-facts"><div><b class="rishe-num">' + fa(c.hours) + '</b><span>ساعت آموزش</span></div><div><b class="rishe-num">' + fa(c.done) + '/' + fa(c.lessons) + '</b><span>مرحله</span></div><div><b class="rishe-num">٪' + fa(c.progress) + '</b><span>پیشرفت</span></div></div>' +
				'</div>' +
				'<div class="rishe-pad">' + premiumNote + chapters + exam + cert + '</div>', { tabs: true });
		}
	};

	/* lesson flow */

	VIEWS.lesson = {
		guest: false,
		load: function (p) {
			return risheApi.get('lessons/' + p.id).then(function (res) {
				state.lesson = { data: res, step: 0, picked: null, checked: false, correct: 0, wordSel: null, saving: false };
				return res;
			});
		},
		html: function () {
			var L = state.lesson;
			var d = L.data;
			var total = d.phrases.length + d.questions.length;
			var s = L.step;
			var pct = total ? Math.round((s / total) * 100) : 100;
			var body = '';
			var foot = '';

			if (total === 0) {
				body = '<div class="rishe-center"><p class="rishe-muted">محتوای این مرحله هنوز آماده نشده است.</p></div>';
				foot = '<div class="rishe-foot"><button class="rishe-btn rishe-btn-ghost" data-act="back">بازگشت</button></div>';
			} else if (s < d.phrases.length) {
				var p = d.phrases[s];
				var tokens = p.en.split(/\s+/);
				var meaning = L.wordSel && p.words[L.wordSel];
				body = '<div class="rishe-step-kind"><span class="rishe-pill rishe-pill-brand">' + ic('volume') + ' عبارت ' + fa(s + 1) + ' از ' + fa(d.phrases.length) + '</span>' + (Object.keys(p.words).length ? '<span class="rishe-label">روی واژه بزن تا معنی‌اش را ببینی</span>' : '') + '</div>' +
					'<div class="rishe-card rishe-phrase-card">' +
						'<div class="rishe-phrase">' + tokens.map(function (t) {
							var key = t.replace(/[.,?!;:"]/g, '');
							return p.words[key] ? '<button data-act="word" data-word="' + esc(key) + '" class="' + (L.wordSel === key ? 'is-on' : '') + '">' + esc(t) + '</button>' : '<span>' + esc(t) + '</span>';
						}).join('') + '</div>' +
						(meaning ? '<div class="rishe-word-pop"><b>' + esc(L.wordSel) + '</b><span>' + esc(meaning) + '</span></div>' : '') +
						'<div class="rishe-audio-row">' +
							'<button class="rishe-play" data-act="play" data-rate="1">' + ic('play') + 'پخش</button>' +
							'<button class="rishe-play-slow" data-act="play" data-rate="0.7">' + ic('slow') + 'آهسته</button>' +
							'<div class="rishe-wave" id="rishe-wave">' + Array.apply(null, { length: 20 }).map(function (_, i) { return '<i style="height:' + (25 + Math.round(Math.abs(Math.sin(i * 1.7 + s)) * 70)) + '%;animation-delay:' + (i * 0.05) + 's"></i>'; }).join('') + '</div>' +
						'</div>' +
						(p.ipa || p.pron_fa ? '<div class="rishe-kv"><span class="rishe-label">' + ic('mic') + ' تلفظ</span>' + (p.ipa ? '<div class="rishe-ipa">' + esc(p.ipa) + '</div>' : '') + (p.pron_fa ? '<div class="rishe-fa-pron">' + esc(p.pron_fa) + '</div>' : '') + '</div>' : '') +
						(p.fa ? '<div class="rishe-kv"><span class="rishe-label">' + ic('text') + ' معنی فارسی</span><div class="rishe-fa-mean">' + esc(p.fa) + '</div></div>' : '') +
					'</div>' +
					(p.teacher_audio_url ? '<div class="rishe-card rishe-teacher"><span class="rishe-teacher-face">م</span><div class="rishe-tt"><b>توضیح صوتی مدرس</b><div class="rishe-bar"><span id="rishe-tbar" style="width:0%"></span></div></div><time id="rishe-ttime">0:00</time><button class="rishe-play-sm" data-act="teacher" aria-label="پخش توضیح مدرس">' + ic('play') + '</button></div>' : '');
				foot = '<div class="rishe-foot"><button class="rishe-btn rishe-btn-primary" data-act="lesson-next">' + (s === d.phrases.length - 1 ? (d.questions.length ? 'برو سراغ تمرین' : 'پایان مرحله') : 'متوجه شدم، بعدی') + '</button></div>';
			} else {
				var qi = s - d.phrases.length;
				var q = d.questions[qi];
				body = '<div class="rishe-step-kind"><span class="rishe-pill rishe-pill-amber">' + ic('star') + ' تمرین ' + fa(qi + 1) + ' از ' + fa(d.questions.length) + '</span></div>' +
					questionBody(q, L.picked, L.checked);
				if (L.checked) {
					var ok = L.picked === q.answer;
					foot = '<div class="rishe-foot ' + (ok ? 'is-ok' : 'is-bad') + '"><div class="rishe-feedback">' + ic(ok ? 'check' : 'close') + '<span>' + (ok ? 'آفرین، درست بود!' : 'نه کاملاً') + (ok ? '' : '<small>پاسخ درست: ' + esc(q.options[q.answer]) + '</small>') + '</span></div><button class="rishe-btn rishe-btn-primary' + (L.saving ? ' is-busy' : '') + '" data-act="lesson-next">' + (s === total - 1 ? 'پایان مرحله' : 'ادامه') + '</button></div>';
				} else {
					foot = '<div class="rishe-foot"><button class="rishe-btn rishe-btn-primary" data-act="check"' + (L.picked === null ? ' disabled' : '') + '>بررسی پاسخ</button></div>';
				}
			}
			return screen('<div class="rishe-lesson-top"><button class="rishe-icon-btn" data-act="back" aria-label="خروج از مرحله">' + ic('close') + '</button><div class="rishe-bar"><span style="width:' + pct + '%"></span></div><span class="rishe-cnt rishe-num">' + fa(s) + '/' + fa(total) + '</span></div>' +
				'<div class="rishe-lesson-body">' + body + '</div>' + foot);
		}
	};

	function questionBody(q, picked, checked) {
		var keys = ['A', 'B', 'C', 'D'];
		var isListen = q.type === 'listen';
		var latin = isListen || q.options.every(function (o) { return /^[\x20-\x7E]+$/.test(o); });
		return '<h2 class="rishe-q-title">' + esc(q.prompt) + '</h2>' +
			(isListen ? '<div class="rishe-listen"><button data-act="q-audio" aria-label="پخش صدا">' + ic('volume') + '</button></div>' : '') +
			'<div class="rishe-options">' + q.options.map(function (o, i) {
				var cls = 'rishe-opt' + (latin ? ' is-en' : '');
				if (checked) {
					if (i === q.answer) { cls += ' is-right'; } else if (i === picked) { cls += ' is-wrong'; }
				} else if (i === picked) { cls += ' is-sel'; }
				return '<button class="' + cls + '" data-act="pick" data-index="' + i + '"' + (checked ? ' disabled' : '') + '><span class="rishe-opt-k">' + keys[i] + '</span><span>' + esc(o) + '</span></button>';
			}).join('') + '</div>';
	}

	function lessonNext() {
		var L = state.lesson;
		var d = L.data;
		var total = d.phrases.length + d.questions.length;
		if (L.step + 1 < total) {
			L.step++;
			L.picked = null;
			L.checked = false;
			L.wordSel = null;
			player.stop();
			paint();
			risheView.firstElementChild.scrollTop = 0;
			return;
		}
		if (L.saving) { return; }
		L.saving = true;
		paint();
		risheApi.post('lessons/' + d.lesson.id + '/complete', { correct: L.correct }).then(function (res) {
			go('done', { result: res, title: d.lesson.title, course: d.lesson.course_id }, { replace: true });
		}, function (err) {
			L.saving = false;
			paint();
			toast(err.message);
		});
	}

	VIEWS.done = {
		guest: false,
		html: function (_, p) {
			var r = p.result || { stats: {} };
			return screen('<div class="rishe-center">' +
				'<span class="rishe-badge-big">' + ic('check') + '</span>' +
				'<h2 class="rishe-h1">مرحله‌ی «' + esc(p.title) + '» تمام شد</h2>' +
				'<p class="rishe-muted">' + (r.next ? 'مرحله‌ی بعد، «' + esc(r.next.title) + '»، باز شد.' : (r.course_completed ? 'همه‌ی مراحل این دوره را تمام کردی!' : '')) + '</p>' +
				'<div class="rishe-stats" style="width:100%"><div class="rishe-card rishe-stat"><b class="rishe-num" style="color:var(--rishe-brand)">+' + fa(r.xp_gained || 0) + '</b><span>امتیاز</span></div><div class="rishe-card rishe-stat"><b class="rishe-num rishe-flame">' + fa(r.stats.streak || 0) + ' روز</b><span>زنجیره</span></div><div class="rishe-card rishe-stat"><b class="rishe-num">' + fa(r.correct || 0) + '/' + fa(r.total || 0) + '</b><span>پاسخ درست</span></div></div>' +
				(r.next ? '<button class="rishe-btn rishe-btn-primary" data-go="lesson" data-id="' + r.next.id + '" data-replace="1">ادامه به مرحله‌ی بعد</button>' : '<button class="rishe-btn rishe-btn-primary" data-go="course" data-id="' + p.course + '" data-replace="1">' + (r.course_completed ? 'آزمون پایانی و مدرک' : 'بازگشت به دوره') + '</button>') +
				'<button class="rishe-btn rishe-btn-ghost" data-tab="home">خانه</button>' +
			'</div>');
		}
	};

	/* final exam */

	VIEWS.exam = {
		guest: false,
		load: function (p) {
			return risheApi.get('courses/' + p.id + '/exam').then(function (res) {
				state.exam = { data: res, step: 0, answers: {}, saving: false };
				return res;
			});
		},
		html: function () {
			var E = state.exam;
			var qs = E.data.questions;
			var q = qs[E.step];
			var picked = E.answers[q.id] !== undefined ? E.answers[q.id] : null;
			var last = E.step === qs.length - 1;
			return screen('<div class="rishe-lesson-top"><button class="rishe-icon-btn" data-act="back" aria-label="خروج از آزمون">' + ic('close') + '</button><div class="rishe-bar"><span style="width:' + Math.round((E.step / qs.length) * 100) + '%"></span></div><span class="rishe-cnt rishe-num">' + fa(E.step + 1) + '/' + fa(qs.length) + '</span></div>' +
				'<div class="rishe-lesson-body"><div class="rishe-step-kind"><span class="rishe-pill rishe-pill-amber">' + ic('star') + ' آزمون پایانی · ' + esc(E.data.course.title) + '</span></div>' + questionBody(q, picked, false) + '</div>' +
				'<div class="rishe-foot"><button class="rishe-btn rishe-btn-primary' + (E.saving ? ' is-busy' : '') + '" data-act="exam-next"' + (picked === null ? ' disabled' : '') + '>' + (last ? 'پایان و ثبت آزمون' : 'سؤال بعدی') + '</button></div>');
		}
	};

	VIEWS.examResult = {
		guest: false,
		html: function (_, p) {
			var r = p.result;
			return screen('<div class="rishe-center">' +
				'<span class="rishe-badge-big' + (r.passed ? '' : ' is-bad') + '">' + ic(r.passed ? 'award' : 'close') + '</span>' +
				'<div class="rishe-score rishe-num">' + fa(r.score) + '<small style="font-size:16px;color:var(--rishe-ink-3)"> از ۱۰۰</small></div>' +
				'<h2 class="rishe-h1">' + (r.passed ? 'در آزمون قبول شدی' : 'این بار به حد نصاب نرسیدی') + '</h2>' +
				'<p class="rishe-muted">' + fa(r.correct) + ' پاسخ درست از ' + fa(r.total) + ' سؤال. حد نصاب: ' + fa(r.pass_score) + '.</p>' +
				(r.passed ? '<button class="rishe-btn rishe-btn-primary" data-tab="certs">' + ic('award') + 'درخواست مدرک</button>' : '<button class="rishe-btn rishe-btn-primary" data-go="exam" data-id="' + p.course + '" data-replace="1">تلاش دوباره</button>') +
				'<button class="rishe-btn rishe-btn-ghost" data-go="course" data-id="' + p.course + '" data-replace="1">بازگشت به دوره</button>' +
			'</div>');
		}
	};

	/* certificates */

	function tl(cls, title, sub) {
		return '<div class="rishe-tl ' + cls + '"><span class="rishe-tl-d">' + (cls === 'is-ok' ? ic('check') : '') + '</span><div><b>' + title + '</b><span>' + sub + '</span></div></div>';
	}

	VIEWS.certs = {
		tab: 'certs',
		guest: false,
		load: function () { return risheApi.get('certificates'); },
		html: function (d) {
			var cards = d.courses.map(function (c) {
				var cert = c.certificate;
				var st = cert ? cert.status : null;
				var pill = st === 'approved' ? '<span class="rishe-pill rishe-pill-brand">صادر شد</span>'
					: st === 'pending' ? '<span class="rishe-pill rishe-pill-amber">در بررسی</span>'
					: st === 'pending_payment' ? '<span class="rishe-pill rishe-pill-amber">در انتظار پرداخت</span>'
					: st === 'rejected' ? '<span class="rishe-pill rishe-pill-bad">رد شد</span>'
					: (c.eligible ? '<span class="rishe-pill rishe-pill-mute">آماده</span>' : '');
				var rows = tl(c.progress >= 100 ? 'is-ok' : 'is-wait', 'تکمیل همه‌ی مراحل', c.progress >= 100 ? 'انجام شد' : '٪' + fa(c.progress) + ' انجام شده');
				if (c.exam.required) {
					var passed = c.exam.best_score !== null && c.exam.best_score >= c.pass_score;
					rows += tl(passed ? 'is-ok' : (c.progress >= 100 ? 'is-wait' : ''), 'قبولی در آزمون پایانی', c.exam.best_score !== null ? 'بهترین نمره ' + fa(c.exam.best_score) + ' · حد نصاب ' + fa(c.pass_score) : 'حد نصاب ' + fa(c.pass_score));
				}
				if (st === 'pending_payment') {
					rows += tl('is-wait', 'پرداخت هزینه‌ی صدور', money(d.fee));
				} else {
					rows += tl(st && st !== 'rejected' ? 'is-ok' : '', 'ثبت درخواست مدرک', st && st !== 'rejected' ? cert.requested_at : (c.eligible ? 'منتظر درخواست تو' : 'بعد از مراحل بالا'));
				}
				rows += tl(st === 'approved' ? 'is-ok' : (st === 'pending' ? 'is-wait' : (st === 'rejected' ? 'is-bad' : '')), 'تأیید آموزشگاه', st === 'approved' ? 'صادر شده در ' + esc(cert.issued_at) : (st === 'pending' ? 'معمولاً کمتر از ۲۴ ساعت' : (st === 'rejected' ? 'دلیل: ' + esc(cert.reject_reason || '—') : 'پس از ثبت درخواست')));

				var action = '';
				if (st === 'approved') {
					action = '<button class="rishe-btn rishe-btn-primary" data-go="cert" data-code="' + esc(cert.code) + '">' + ic('award') + 'مشاهده‌ی مدرک</button>';
				} else if (st === 'pending_payment') {
					action = '<button class="rishe-btn rishe-btn-primary" data-act="pay" data-type="certificate" data-item="' + cert.id + '">' + ic('card') + 'پرداخت ' + money(d.fee) + '</button>';
				} else if (st === 'pending') {
					action = '<button class="rishe-btn rishe-btn-ghost" data-act="reload">' + ic('refresh') + 'به‌روزرسانی وضعیت</button>';
				} else if (c.eligible) {
					action = '<button class="rishe-btn rishe-btn-primary" data-act="cert-request" data-id="' + c.id + '">' + ic('award') + (st === 'rejected' ? 'درخواست دوباره' : 'درخواست صدور مدرک') + '</button>' +
						(d.fee > 0 ? '<p class="rishe-label" style="text-align:center;margin:0">هزینه‌ی صدور: ' + money(d.fee) + '</p>' : '');
				} else {
					action = '<button class="rishe-btn rishe-btn-ghost" data-go="course" data-id="' + c.id + '">ادامه‌ی دوره</button>';
				}
				return '<div class="rishe-card rishe-req"><div class="rishe-req-head">' + courseCover(c) + '<div class="rishe-t"><h4>' + esc(c.title) + '</h4><small>' + fa(c.hours) + ' ساعت آموزش</small></div>' + pill + '</div>' +
					'<div class="rishe-timeline">' + rows + '</div>' + action + '</div>';
			}).join('');
			return screen('<div class="rishe-pad"><div><h1 class="rishe-h1">مدارک</h1><p class="rishe-muted">بعد از پایان هر دوره، مدرک را درخواست بده تا آموزشگاه تأیید و صادر کند.</p></div>' +
				(cards || '<div class="rishe-card rishe-info">' + ic('book') + '<p>هنوز دوره‌ای را شروع نکرده‌ای. با شروع اولین دوره، وضعیت مدرکش اینجا نمایش داده می‌شود.</p><button class="rishe-btn rishe-btn-primary rishe-btn-sm" data-tab="courses">دوره‌ها</button></div>') +
				'</div>', { tabs: true });
		}
	};

	function certificateHTML(c) {
		var b = c.brand;
		var score = c.score !== null ? ' و در آزمون پایانی نمره‌ی <strong>' + fa(c.score) + ' از ۱۰۰</strong> را کسب کرده' : '';
		var name = String(b.name_en || '').toUpperCase();
		return '<div class="rishe-cert-wrap"><div class="rishe-cert"><div class="rishe-cert-dots"></div><div class="rishe-cert-frame"></div><div class="rishe-cert-in">' +
			'<div class="rishe-cert-top"><div class="rishe-cert-brand"><span class="rishe-cert-mark"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 3v8M12 11c0 4-4 4-4 8M12 11c0 4 4 4 4 8M12 11v10"/></svg></span><div><b>' + esc(b.name_en) + '</b><small>' + esc(b.name + ' · ' + b.tagline) + '</small></div></div>' +
			'<div class="rishe-cert-meta">شماره‌ی مدرک: <b>' + esc(c.code) + '</b><br>تاریخ صدور: ' + esc(c.issued_at || '—') + '</div></div>' +
			'<div class="rishe-cert-title"><div class="rishe-cert-en">Certificate of Completion</div><div class="rishe-cert-rule"></div><div class="rishe-cert-fa">گواهی پایان دوره</div></div>' +
			'<div class="rishe-cert-name"><b>' + esc(c.name_fa) + '</b><span>' + esc(c.name_en) + '</span></div>' +
			'<div class="rishe-cert-text">دوره‌ی <strong>«' + esc(c.course_title) + (c.course_code ? ' (' + esc(c.course_code) + ')' : '') + '»</strong> را به مدت <strong>' + fa(c.hours) + ' ساعت</strong> با موفقیت گذرانده' + score + ' است. این گواهی به پاس پشتکار و تعهد ایشان در طول دوره اعطا می‌شود.</div>' +
			'<div class="rishe-cert-bottom"><div class="rishe-cert-sign">' +
				'<svg class="rishe-cert-seal" viewBox="0 0 100 100"><defs><path id="rishe-seal" d="M50 50 m-36 0 a36 36 0 1 1 72 0 a36 36 0 1 1 -72 0"/></defs><circle cx="50" cy="50" r="46" fill="none" stroke="#0D6B4E" stroke-width="2" opacity=".75"/><circle cx="50" cy="50" r="28" fill="none" stroke="#0D6B4E" stroke-width="1.2" opacity=".6"/><text font-size="9.5" font-weight="700" fill="#0D6B4E" opacity=".8" letter-spacing="2.2"><textPath href="#rishe-seal">' + esc(name) + ' · VERIFIED · ' + esc(name) + ' · VERIFIED ·</textPath></text><g transform="translate(38 36)" fill="none" stroke="#0D6B4E" stroke-width="2.2" stroke-linecap="round" opacity=".85"><path d="M12 3v8M12 11c0 4-4 4-4 8M12 11c0 4 4 4 4 8M12 11v10"/></g></svg>' +
				'<div class="rishe-cert-sig"><span class="rishe-cert-line"></span><b>' + esc(b.signer_name || b.signer_title) + '</b><small>' + esc(b.signer_name ? b.signer_title : 'امضای الکترونیک') + '</small></div>' +
			'</div><div class="rishe-cert-verify"><small>استعلام اعتبار</small><b>' + esc(String(c.verify_url).replace(/^https?:\/\//, '')) + '</b></div></div>' +
		'</div></div></div>';
	}

	VIEWS.cert = {
		guest: false,
		load: function (p) { return risheApi.get('certificates/' + encodeURIComponent(p.code)).then(function (r) { return r.certificate; }); },
		html: function (c) {
			return screen(topbar('مدرک پایان دوره') + '<div class="rishe-pad">' + certificateHTML(c) +
				'<div class="rishe-card rishe-kvlist">' +
					'<div><span class="rishe-label">وضعیت</span><span class="rishe-pill rishe-pill-brand">' + ic('check') + esc(c.status_label) + '</span></div>' +
					'<div><span class="rishe-label">کد مدرک</span><b class="rishe-en rishe-num">' + esc(c.code) + '</b></div>' +
					'<div><span class="rishe-label">تاریخ صدور</span><b>' + esc(c.issued_at || '—') + '</b></div>' +
				'</div>' +
				'<div class="rishe-two"><button class="rishe-btn rishe-btn-primary" data-act="open" data-url="' + esc(c.verify_url) + '">' + ic('link') + 'صفحه‌ی استعلام</button><button class="rishe-btn rishe-btn-ghost" data-act="copy" data-text="' + esc(c.verify_url) + '">' + ic('copy') + 'کپی لینک</button></div>' +
				'<p class="rishe-label" style="text-align:center;margin:0;line-height:1.9">صفحه‌ی استعلام در مرورگر باز می‌شود و از همان‌جا می‌توانید مدرک را چاپ یا به PDF تبدیل کنید.</p>' +
			'</div>');
		}
	};

	/* plans & profile */

	VIEWS.plans = {
		guest: false,
		load: function () {
			return Promise.all([risheApi.get('plans'), loadMe()]).then(function (r) { return { plans: r[0], user: r[1] }; });
		},
		html: function (d) {
			var sub = d.user.subscription;
			var body;
			if (!d.plans.enabled) {
				body = '<div class="rishe-card rishe-info">' + ic('star') + '<p>در حال حاضر همه‌ی دوره‌ها رایگان هستند و نیازی به اشتراک ندارید.</p></div>';
			} else {
				body = (sub ? '<div class="rishe-card rishe-info">' + ic('check') + '<p>اشتراک <b>' + esc(sub.plan || 'ویژه') + '</b> فعال است تا ' + esc(sub.ends_at) + ' (' + fa(sub.days_left) + ' روز دیگر). خرید دوباره به انتهای آن اضافه می‌شود.</p></div>' : '') +
					d.plans.plans.map(function (p) {
						return '<div class="rishe-card rishe-plan"><div class="rishe-t"><h4>' + esc(p.title) + '</h4><small>' + fa(p.days) + ' روز دسترسی به همه‌ی دوره‌ها</small></div><button class="rishe-btn rishe-btn-primary rishe-btn-sm" data-act="pay" data-type="subscription" data-item="' + p.id + '">' + money(p.price) + '</button></div>';
					}).join('') +
					'<p class="rishe-label" style="text-align:center;margin:0;line-height:1.9">پرداخت در مرورگر و از طریق درگاه امن زیبال انجام می‌شود. بعد از پرداخت به اپ برگردید.</p>';
			}
			return screen(topbar('اشتراک ویژه') + '<div class="rishe-pad">' + body + '</div>');
		}
	};

	VIEWS.profile = {
		tab: 'profile',
		guest: false,
		load: function () { return loadMe(); },
		html: function (u) {
			var dark = document.documentElement.getAttribute('data-theme') === 'dark';
			return screen('<div class="rishe-pad">' +
				'<div class="rishe-profile-head"><span class="rishe-avatar">' + esc(u.name.charAt(0)) + '</span><h2>' + esc(u.name) + '</h2><span class="rishe-en rishe-num">' + esc(u.mobile) + '</span></div>' +
				'<div class="rishe-stats"><div class="rishe-card rishe-stat"><b class="rishe-num">' + fa(u.stats.xp) + '</b><span>امتیاز</span></div><div class="rishe-card rishe-stat"><b class="rishe-num">' + fa(u.stats.words) + '</b><span>عبارت</span></div><div class="rishe-card rishe-stat"><b class="rishe-num rishe-flame">' + fa(u.stats.streak) + '</b><span>روز پیاپی</span></div></div>' +
				'<div class="rishe-card rishe-menu">' +
					'<button data-go="editProfile">' + ic('edit') + '<span class="rishe-t">ویرایش اطلاعات و رمز</span></button>' +
					'<button data-go="editProfile">' + ic('award') + '<span class="rishe-t">نام انگلیسی روی مدرک</span><span class="rishe-v rishe-en">' + esc(u.name_en || '—') + '</span></button>' +
					(state.config.payments_enabled ? '<button data-go="plans">' + ic('star') + '<span class="rishe-t">اشتراک ویژه</span><span class="rishe-v">' + (u.subscription ? fa(u.subscription.days_left) + ' روز مانده' : 'غیرفعال') + '</span></button>' : '') +
					'<button data-act="theme" aria-pressed="' + dark + '">' + ic('moon') + '<span class="rishe-t">حالت تیره</span><span class="rishe-switch' + (dark ? ' is-on' : '') + '"></span></button>' +
				'</div>' +
				'<div class="rishe-card rishe-menu"><button class="is-danger" data-act="logout">' + ic('logout') + '<span class="rishe-t">خروج از حساب</span></button></div>' +
			'</div>', { tabs: true });
		}
	};

	VIEWS.editProfile = {
		guest: false,
		load: function () { return state.user ? Promise.resolve(state.user) : loadMe(); },
		html: function (u) {
			return screen(topbar('ویرایش اطلاعات') + '<div class="rishe-pad"><form class="rishe-form" data-form="profile" novalidate>' +
				'<div class="rishe-field"><label for="rishe-p-name">نام و نام خانوادگی</label><input class="rishe-input" id="rishe-p-name" name="name" value="' + esc(u.name) + '" required></div>' +
				'<div class="rishe-field"><label for="rishe-p-name-en">نام انگلیسی</label><input class="rishe-input is-ltr" id="rishe-p-name-en" name="name_en" value="' + esc(u.name_en) + '" required><small>روی مدرک‌های بعدی چاپ می‌شود. مدرک‌های صادرشده تغییر نمی‌کنند.</small></div>' +
				'<div class="rishe-field"><label for="rishe-p-new">رمز تازه</label><input class="rishe-input is-ltr" id="rishe-p-new" name="new_password" type="password" autocomplete="new-password"><small>اگر نمی‌خواهی رمز را عوض کنی، خالی بگذار.</small></div>' +
				(u.has_password ? '<div class="rishe-field"><label for="rishe-p-cur">رمز فعلی</label><input class="rishe-input is-ltr" id="rishe-p-cur" name="password" type="password" autocomplete="current-password"><small>فقط برای تغییر رمز لازم است.</small></div>' : '') +
				'<div class="rishe-form-err" id="rishe-p-err" hidden></div>' +
				'<button class="rishe-btn rishe-btn-primary" type="submit">ذخیره</button></form></div>');
		}
	};

	/* ---------- actions ---------- */

	function busy(btn, on) { if (btn) { btn.classList.toggle('is-busy', on); } }

	function startPayment(btn, type, item) {
		busy(btn, true);
		risheApi.post('payments', { type: type, item_id: Number(item) }).then(function (res) {
			busy(btn, false);
			toast('درگاه پرداخت در مرورگر باز می‌شود…');
			openExternal(res.url);
		}, function (err) { busy(btn, false); toast(err.message); });
	}

	function copyText(text) {
		var done = function () { toast('لینک کپی شد.'); };
		if (navigator.clipboard && navigator.clipboard.writeText) {
			navigator.clipboard.writeText(text).then(done, function () { toast(text); });
		} else {
			toast(text);
		}
	}

	document.addEventListener('click', function (e) {
		var el = e.target.closest('button, [data-go], [data-tab]');
		if (!el) { return; }

		if (el.dataset.tab) {
			go(el.dataset.tab, {}, { replace: ['home', 'courses', 'certs', 'profile'].indexOf(state.view) !== -1 });
			return;
		}
		if (el.dataset.go) {
			var params = {};
			if (el.dataset.id) { params.id = Number(el.dataset.id); }
			if (el.dataset.code) { params.code = el.dataset.code; }
			if (el.dataset.go === 'exam' || el.dataset.go === 'examResult') { params.course = params.id; }
			go(el.dataset.go, params, { replace: el.dataset.replace === '1' });
			return;
		}

		var L = state.lesson;
		switch (el.dataset.act) {
			case 'back':
				if (state.view === 'auth') { go('welcome', {}, { replace: true }); break; }
				if (depth > 0) { history.back(); } else { go(risheApi.hasToken() ? 'home' : 'welcome', {}, { replace: true }); }
				break;
			case 'retry':
			case 'reload':
				render();
				break;
			case 'auth':
				state.auth = { mode: el.dataset.mode, step: 1, error: '', mobile: state.auth.mobile, pending: null };
				if (state.view === 'auth') { paint(); } else { go('auth'); }
				break;
			case 'auth-edit-mobile':
				state.auth.step = 1;
				state.auth.error = '';
				paint();
				break;
			case 'chapter':
				var idx = Number(el.dataset.index);
				state.openChapter.index = state.openChapter.index === idx ? -1 : idx;
				paint();
				break;
			case 'lesson':
				if (el.dataset.state === 'locked') { toast('اول مرحله‌ی قبلی را تمام کن.'); break; }
				if (el.dataset.open !== '1') {
					toast('این مرحله مخصوص اشتراک ویژه است.');
					if (state.config.payments_enabled) { go('plans'); }
					break;
				}
				go('lesson', { id: Number(el.dataset.id) });
				break;
			case 'word':
				L.wordSel = L.wordSel === el.dataset.word ? null : el.dataset.word;
				paint();
				if (L.wordSel && 'speechSynthesis' in window) { player.play(null, { text: L.wordSel, rate: 0.9 }); }
				break;
			case 'play':
				var ph = L.data.phrases[L.step];
				var wave = document.getElementById('rishe-wave');
				if (wave) { wave.classList.add('is-playing'); }
				player.play(ph.audio_url, { text: ph.en, rate: Number(el.dataset.rate), onStop: function () { var w = document.getElementById('rishe-wave'); if (w) { w.classList.remove('is-playing'); } } });
				break;
			case 'teacher':
				var tp = L.data.phrases[L.step];
				player.play(tp.teacher_audio_url, {
					onTime: function (t, dur) {
						var bar = document.getElementById('rishe-tbar');
						var time = document.getElementById('rishe-ttime');
						if (bar && dur) { bar.style.width = Math.min(100, (t / dur) * 100) + '%'; }
						if (time) { time.textContent = Math.floor(t / 60) + ':' + String(Math.floor(t % 60)).padStart(2, '0'); }
					}
				});
				break;
			case 'q-audio':
				var q = state.view === 'exam' ? state.exam.data.questions[state.exam.step] : L.data.questions[L.step - L.data.phrases.length];
				player.play(q.audio_url, { text: q.say_text, rate: 1 });
				break;
			case 'pick':
				if (state.view === 'exam') {
					var eq = state.exam.data.questions[state.exam.step];
					state.exam.answers[eq.id] = Number(el.dataset.index);
				} else {
					L.picked = Number(el.dataset.index);
				}
				paint();
				break;
			case 'check':
				var cq = L.data.questions[L.step - L.data.phrases.length];
				L.checked = true;
				if (L.picked === cq.answer) { L.correct++; }
				paint();
				break;
			case 'lesson-next':
				lessonNext();
				break;
			case 'exam-next':
				var E = state.exam;
				if (E.step < E.data.questions.length - 1) { E.step++; player.stop(); paint(); break; }
				if (E.saving) { break; }
				E.saving = true;
				paint();
				risheApi.post('courses/' + E.data.course.id + '/exam', { answers: E.answers }).then(function (res) {
					go('examResult', { result: res, course: E.data.course.id }, { replace: true });
				}, function (err) { E.saving = false; paint(); toast(err.message); });
				break;
			case 'cert-request':
				busy(el, true);
				risheApi.post('certificates', { course_id: Number(el.dataset.id) }).then(function (res) {
					toast(res.needs_payment ? 'درخواست ثبت شد. هزینه‌ی صدور را پرداخت کنید.' : 'درخواست مدرک ثبت شد.');
					render();
				}, function (err) { busy(el, false); toast(err.message); });
				break;
			case 'pay':
				startPayment(el, el.dataset.type, el.dataset.item);
				break;
			case 'open':
				openExternal(el.dataset.url);
				break;
			case 'copy':
				copyText(el.dataset.text);
				break;
			case 'theme':
				var dark = document.documentElement.getAttribute('data-theme') !== 'dark';
				risheApi.store.set('rishe_theme', dark ? 'dark' : 'light');
				applyTheme();
				paint();
				break;
			case 'logout':
				risheApi.post('auth/logout').catch(function () { /* token is cleared locally anyway */ });
				logoutLocal();
				break;
		}
	});

	document.addEventListener('submit', function (e) {
		var form = e.target;
		if (!form.dataset.form) { return; }
		e.preventDefault();
		var btn = form.querySelector('button[type="submit"]');
		var data = {};
		Array.prototype.forEach.call(form.elements, function (f) { if (f.name) { data[f.name] = f.value.trim(); } });

		if (form.dataset.form === 'profile') {
			var errBox = document.getElementById('rishe-p-err');
			busy(btn, true);
			risheApi.post('me', data).then(function (res) {
				state.user = res.user;
				toast('ذخیره شد.');
				history.back();
			}, function (err) {
				busy(btn, false);
				errBox.textContent = err.message;
				errBox.hidden = false;
			});
			return;
		}

		// auth
		var a = state.auth;
		var fail = function (err) { a.error = err.message; paint(); };
		a.error = '';
		if (data.mobile) { a.mobile = data.mobile; }
		busy(btn, true);

		if (a.mode === 'login') {
			risheApi.post('auth/login', { mobile: data.mobile, password: data.password }).then(onAuthenticated, fail);
		} else if (a.mode === 'register' && a.step === 1) {
			a.pending = data;
			if (state.config.sms_enabled) {
				risheApi.post('auth/otp/send', { mobile: data.mobile }).then(function () { a.step = 2; paint(); }, fail);
			} else {
				risheApi.post('auth/register', data).then(onAuthenticated, fail);
			}
		} else if (a.mode === 'register') {
			var payload = Object.assign({}, a.pending, { code: data.code });
			risheApi.post('auth/register', payload).then(onAuthenticated, fail);
		} else if (a.step === 1) {
			risheApi.post('auth/otp/send', { mobile: data.mobile }).then(function (res) {
				if (a.mode === 'otp' && !res.registered) {
					state.auth = { mode: 'register', step: 1, error: 'با این شماره حسابی نیست. اطلاعاتت را وارد کن تا حساب ساخته شود.', mobile: a.mobile, pending: { mobile: a.mobile } };
					paint();
					return;
				}
				a.step = 2;
				paint();
			}, fail);
		} else if (a.mode === 'otp') {
			risheApi.post('auth/otp/verify', { mobile: a.mobile, code: data.code }).then(onAuthenticated, fail);
		} else {
			risheApi.post('auth/password/reset', { mobile: a.mobile, code: data.code, password: data.password }).then(onAuthenticated, fail);
		}
	});

	// Coming back from the payment page in the browser: refresh what may have changed
	document.addEventListener('visibilitychange', function () {
		if (document.visibilityState === 'visible' && ['certs', 'plans', 'profile', 'home', 'course'].indexOf(state.view) !== -1) {
			risheApi.get('config').then(function (c) { state.config = c; }, function () { /* keep previous config */ });
			render();
		}
	});

	/* ---------- boot ---------- */

	function boot() {
		applyTheme();
		depth = 0;
		history.replaceState({ view: 'boot', params: {}, depth: 0 }, '');
		risheView.innerHTML = loadingScreen(false);
		risheApi.get('config').then(function (c) {
			state.config = c;
			document.title = c.app_name;
			go(risheApi.hasToken() ? 'home' : 'welcome', {}, { replace: true });
		}, function (err) {
			risheView.innerHTML = screen('<div class="rishe-center"><span class="rishe-logo-mark">' + ic('root') + '</span><p class="rishe-muted">' + esc(err.message) + '</p><button class="rishe-btn rishe-btn-primary rishe-btn-sm" style="width:auto;padding:0 20px" data-act="boot">تلاش دوباره</button></div>');
		});
	}

	document.addEventListener('click', function (e) {
		if (e.target.closest('[data-act="boot"]')) { boot(); }
	});

	boot();
})();
