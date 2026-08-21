(function () {
	'use strict';

	var table = document.getElementById('sina-packaging-tiers');
	var addButton = document.getElementById('sina-packaging-tiers-add');
	var template = document.getElementById('sina-packaging-tier-template');

	if (!table || !addButton || !template) {
		return;
	}

	var tbody = table.querySelector('tbody');

	function reindexRows() {
		Array.prototype.forEach.call(tbody.querySelectorAll('.sina-packaging-tiers__row'), function (row, index) {
			Array.prototype.forEach.call(row.querySelectorAll('input[name]'), function (input) {
				input.name = input.name.replace(/\[\d+\]|\[__INDEX__\]/, '[' + index + ']');
			});
		});
	}

	addButton.addEventListener('click', function () {
		var html = template.innerHTML.replace(/__INDEX__/g, String(tbody.children.length));
		tbody.insertAdjacentHTML('beforeend', html);
		reindexRows();
	});

	tbody.addEventListener('click', function (event) {
		var target = event.target;
		if (!target || !target.classList.contains('sina-packaging-tiers__remove')) {
			return;
		}

		var row = target.closest('.sina-packaging-tiers__row');
		if (!row) {
			return;
		}

		if (tbody.querySelectorAll('.sina-packaging-tiers__row').length === 1) {
			row.querySelectorAll('input').forEach(function (input) {
				input.value = '';
			});
			return;
		}

		row.remove();
		reindexRows();
	});
})();
