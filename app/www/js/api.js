/**
 * Rishe API client.
 */
(function () {
	'use strict';

	var TOKEN_KEY = 'rishe_token';

	var risheStore = {
		get: function (key) { try { return window.localStorage.getItem(key); } catch (e) { return null; } },
		set: function (key, value) { try { window.localStorage.setItem(key, value); } catch (e) { /* storage unavailable */ } },
		remove: function (key) { try { window.localStorage.removeItem(key); } catch (e) { /* storage unavailable */ } }
	};

	function RisheApiError(message, status, code) {
		this.message = message;
		this.status = status;
		this.code = code;
	}

	function risheBase() {
		var base = (window.RISHE_CONFIG && window.RISHE_CONFIG.apiBase) || '';
		return base.replace(/\/+$/, '') + '/';
	}

	function risheRequest(method, path, body) {
		var headers = { 'Accept': 'application/json' };
		var token = risheStore.get(TOKEN_KEY);
		if (token) { headers.Authorization = 'Bearer ' + token; }
		if (body !== undefined) { headers['Content-Type'] = 'application/json'; }

		var controller = typeof AbortController !== 'undefined' ? new AbortController() : null;
		var timer = controller ? setTimeout(function () { controller.abort(); }, 20000) : null;

		return fetch(risheBase() + path.replace(/^\/+/, ''), {
			method: method,
			headers: headers,
			body: body !== undefined ? JSON.stringify(body) : undefined,
			signal: controller ? controller.signal : undefined
		}).then(function (res) {
			return res.json().catch(function () { return {}; }).then(function (data) {
				if (timer) { clearTimeout(timer); }
				if (!res.ok || data.ok === false) {
					if (res.status === 401) { risheStore.remove(TOKEN_KEY); }
					throw new RisheApiError(data.message || 'خطا در ارتباط با سرور.', res.status, data.code || 'error');
				}
				return data;
			});
		}, function () {
			if (timer) { clearTimeout(timer); }
			throw new RisheApiError('اتصال به اینترنت یا سرور برقرار نیست.', 0, 'network');
		});
	}

	window.RisheApi = {
		store: risheStore,
		hasToken: function () { return !!risheStore.get(TOKEN_KEY); },
		setToken: function (token) { risheStore.set(TOKEN_KEY, token); },
		clearToken: function () { risheStore.remove(TOKEN_KEY); },
		get: function (path) { return risheRequest('GET', path); },
		post: function (path, body) { return risheRequest('POST', path, body || {}); }
	};
})();
