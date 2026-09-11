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

	function bindCostType(container) {
		var radios = container.querySelectorAll('[data-cost-type]');
		var amountField = container.querySelector('[data-amount-field]');

		if (!radios.length || !amountField) {
			return;
		}

		function sync() {
			var selected = null;

			Array.prototype.forEach.call(radios, function (radio) {
				var label = radio.closest('.sina-packaging__cost-type');
				var checked = radio.checked;

				if (label) {
					label.classList.toggle('is-selected', checked);
				}

				if (checked) {
					selected = radio;
				}
			});

			amountField.classList.toggle('is-hidden', !(selected && selected.value === 'amount'));
		}

		Array.prototype.forEach.call(radios, function (radio) {
			radio.addEventListener('change', sync);
		});
		sync();
	}

	Array.prototype.forEach.call(document.querySelectorAll('[data-shipping-method]'), function (container) {
		bindSwitch(container);
		bindCostType(container);
	});
})();
