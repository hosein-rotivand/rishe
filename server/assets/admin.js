/* Rishe admin panel interactions */
(function () {
	'use strict';

	// Confirm before destructive form submits
	document.addEventListener('submit', function (e) {
		var form = e.target;
		var msg = form.getAttribute('data-confirm');
		if (msg && !window.confirm(msg)) {
			e.preventDefault();
		}
	});

	document.addEventListener('click', function (e) {
		var toggle = e.target.closest('[data-toggle-side]');
		if (toggle) {
			document.getElementById('rishe-side').classList.toggle('is-open');
			return;
		}
		var reject = e.target.closest('[data-reject]');
		if (reject) {
			var box = document.getElementById('rishe-reject-' + reject.getAttribute('data-reject'));
			if (box) {
				box.classList.toggle('is-open');
				var input = box.querySelector('input[name="reason"]');
				if (input) { input.focus(); }
			}
		}
	});

	// Show/hide fields that depend on the question type
	var typeSelect = document.getElementById('rishe-question-type');
	if (typeSelect) {
		var sync = function () {
			document.querySelectorAll('[data-listen-only]').forEach(function (el) {
				el.hidden = typeSelect.value !== 'listen';
			});
		};
		typeSelect.addEventListener('change', sync);
		sync();
	}
})();
