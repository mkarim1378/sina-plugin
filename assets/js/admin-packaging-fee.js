(function () {
	'use strict';

	var list = document.getElementById('sina-packaging-tiers');
	var addButton = document.getElementById('sina-packaging-tiers-add');
	var template = document.getElementById('sina-packaging-tier-template');
	var switchInput = document.querySelector('.sina-packaging__switch input');
	var switchLabel = document.querySelector('.sina-packaging__switch-label');

	if (!list || !addButton || !template) {
		return;
	}

	function reindexRows() {
		Array.prototype.forEach.call(list.querySelectorAll('[data-tier-row]'), function (row, index) {
			var badge = row.querySelector('.sina-packaging__tier-index');
			if (badge) {
				badge.textContent = String(index + 1);
			}

			Array.prototype.forEach.call(row.querySelectorAll('input[name]'), function (input) {
				input.name = input.name.replace(/\[\d+\]|\[__INDEX__\]/, '[' + index + ']');
			});
		});
	}

	addButton.addEventListener('click', function () {
		var html = template.innerHTML.replace(/__INDEX__/g, String(list.querySelectorAll('[data-tier-row]').length));
		list.insertAdjacentHTML('beforeend', html);
		reindexRows();
	});

	list.addEventListener('click', function (event) {
		var target = event.target;
		if (!target || !target.closest) {
			return;
		}

		var removeButton = target.closest('[data-remove-tier]');
		if (!removeButton) {
			return;
		}

		var row = removeButton.closest('[data-tier-row]');
		if (!row) {
			return;
		}

		if (list.querySelectorAll('[data-tier-row]').length === 1) {
			Array.prototype.forEach.call(row.querySelectorAll('input'), function (input) {
				input.value = '';
			});
			return;
		}

		row.remove();
		reindexRows();
	});

	if (switchInput && switchLabel) {
		switchInput.addEventListener('change', function () {
			switchLabel.textContent = switchInput.checked ? 'فعال' : 'غیرفعال';
		});
	}

	reindexRows();
})();
