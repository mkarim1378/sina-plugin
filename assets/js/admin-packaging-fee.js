(function () {
	'use strict';

	function bindSwitch(container) {
		var switchInput = container.querySelector('.sina-packaging__switch input');
		var switchLabel = container.querySelector('.sina-packaging__switch-label');

		if (!switchInput || !switchLabel) {
			return;
		}

		function sync() {
			switchLabel.textContent = switchInput.checked ? 'فعال' : 'غیرفعال';
			container.classList.toggle('is-disabled', !switchInput.checked);
		}

		switchInput.addEventListener('change', sync);
		sync();
	}

	Array.prototype.forEach.call(document.querySelectorAll('[data-shipping-method]'), bindSwitch);
})();
