/*
 * Password visibility toggle.
 * Wraps every <input type="password"> with an eye icon that reveals the value.
 */
(function () {
	'use strict';

	function toggle(input, btn) {
		var showing = input.getAttribute('type') === 'text';
		input.setAttribute('type', showing ? 'password' : 'text');
		var icon = btn.querySelector('i');
		if (icon) {
			icon.classList.toggle('fa-eye', showing);
			icon.classList.toggle('fa-eye-slash', !showing);
		}
		btn.setAttribute('aria-label', showing ? 'Show password' : 'Hide password');
	}

	function attach(input) {
		if (input.dataset.pwToggleAttached === '1') return;
		if (input.type !== 'password') return;
		input.dataset.pwToggleAttached = '1';

		// Wrap the input in a positioned container without disturbing layout.
		var wrap = document.createElement('span');
		wrap.className = 'pw-toggle-wrap';
		wrap.style.position = 'relative';
		wrap.style.display  = 'block';
		input.parentNode.insertBefore(wrap, input);
		wrap.appendChild(input);
		input.style.paddingRight = '2.5rem';

		var btn = document.createElement('button');
		btn.type = 'button';
		btn.className = 'pw-toggle-btn';
		btn.setAttribute('aria-label', 'Show password');
		btn.setAttribute('tabindex', '-1');
		btn.style.position = 'absolute';
		btn.style.top = '50%';
		btn.style.right = '0.5rem';
		btn.style.transform = 'translateY(-50%)';
		btn.style.background = 'transparent';
		btn.style.border = '0';
		btn.style.padding = '0.25rem 0.5rem';
		btn.style.color = '#6c757d';
		btn.style.cursor = 'pointer';
		btn.style.lineHeight = '1';
		btn.innerHTML = '<i class="fas fa-eye" aria-hidden="true"></i>';
		btn.addEventListener('click', function () { toggle(input, btn); });
		wrap.appendChild(btn);
	}

	function scan(root) {
		var inputs = (root || document).querySelectorAll('input[type="password"]');
		for (var i = 0; i < inputs.length; i++) attach(inputs[i]);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', function () { scan(document); });
	} else {
		scan(document);
	}

	// Re-scan when new inputs appear (e.g. modals opened via JS).
	if (typeof MutationObserver !== 'undefined') {
		new MutationObserver(function (mutations) {
			for (var m = 0; m < mutations.length; m++) {
				var added = mutations[m].addedNodes;
				for (var n = 0; n < added.length; n++) {
					var node = added[n];
					if (node.nodeType !== 1) continue;
					if (node.matches && node.matches('input[type="password"]')) attach(node);
					if (node.querySelectorAll) scan(node);
				}
			}
		}).observe(document.documentElement, { childList: true, subtree: true });
	}
})();
