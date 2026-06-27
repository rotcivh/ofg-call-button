jQuery(function ($) {
	'use strict';

	if ($.fn.wpColorPicker) {
		$('.ofogh-call-btn-color').wpColorPicker();
	}
});

(function () {
	'use strict';

	var config = window.ofoghCallBtnAdmin || {};

	if (!config.jalaliDates) {
		return;
	}

	var monthNames = [
		'فروردین',
		'اردیبهشت',
		'خرداد',
		'تیر',
		'مرداد',
		'شهریور',
		'مهر',
		'آبان',
		'آذر',
		'دی',
		'بهمن',
		'اسفند'
	];
	var weekDays = ['ش', 'ی', 'د', 'س', 'چ', 'پ', 'ج'];
	var activePicker = null;

	function div(a, b) {
		return ~~(a / b);
	}

	function gregorianToJalali(gy, gm, gd) {
		var gDMonth = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
		var jy;
		if (gy > 1600) {
			jy = 979;
			gy -= 1600;
		} else {
			jy = 0;
			gy -= 621;
		}
		var gy2 = gm > 2 ? gy + 1 : gy;
		var days = (365 * gy) + div(gy2 + 3, 4) - div(gy2 + 99, 100) + div(gy2 + 399, 400) - 80 + gd + gDMonth[gm - 1];
		jy += 33 * div(days, 12053);
		days %= 12053;
		jy += 4 * div(days, 1461);
		days %= 1461;
		if (days > 365) {
			jy += div(days - 1, 365);
			days = (days - 1) % 365;
		}
		var jm = days < 186 ? 1 + div(days, 31) : 7 + div(days - 186, 30);
		var jd = 1 + (days < 186 ? days % 31 : (days - 186) % 30);
		return [jy, jm, jd];
	}

	function jalaliToGregorian(jy, jm, jd) {
		var gy;
		if (jy > 979) {
			gy = 1600;
			jy -= 979;
		} else {
			gy = 621;
		}
		var days = (365 * jy) + div(jy, 33) * 8 + div((jy % 33) + 3, 4) + 78 + jd + (jm < 7 ? (jm - 1) * 31 : ((jm - 7) * 30) + 186);
		gy += 400 * div(days, 146097);
		days %= 146097;
		if (days > 36524) {
			gy += 100 * div(--days, 36524);
			days %= 36524;
			if (days >= 365) {
				days++;
			}
		}
		gy += 4 * div(days, 1461);
		days %= 1461;
		if (days > 365) {
			gy += div(days - 1, 365);
			days = (days - 1) % 365;
		}
		var gd = days + 1;
		var salA = [0, 31, ((gy % 4 === 0 && gy % 100 !== 0) || (gy % 400 === 0)) ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
		var gm;
		for (gm = 1; gm <= 12 && gd > salA[gm]; gm++) {
			gd -= salA[gm];
		}
		return [gy, gm, gd];
	}

	function pad(value) {
		return String(value).padStart(2, '0');
	}

	function formatJalali(parts) {
		return parts[0] + '/' + pad(parts[1]) + '/' + pad(parts[2]);
	}

	function formatGregorian(parts) {
		return parts[0] + '-' + pad(parts[1]) + '-' + pad(parts[2]);
	}

	function parseJalali(value) {
		var match = String(value || '').replace(/[۰-۹]/g, function (char) {
			return '۰۱۲۳۴۵۶۷۸۹'.indexOf(char);
		}).replace(/[٠-٩]/g, function (char) {
			return '٠١٢٣٤٥٦٧٨٩'.indexOf(char);
		}).match(/^(\d{4})[\/\-.](\d{1,2})[\/\-.](\d{1,2})$/);

		if (!match) {
			return null;
		}

		return [parseInt(match[1], 10), parseInt(match[2], 10), parseInt(match[3], 10)];
	}

	function hiddenFor(input) {
		var field = input.closest('.ofogh-call-btn-jalali-field');
		return field ? field.querySelector('[data-ofogh-gregorian-date]') : null;
	}

	function syncFromVisible(input) {
		var parts = parseJalali(input.value);
		var hidden = hiddenFor(input);
		if (!parts || !hidden || parts[1] < 1 || parts[1] > 12 || parts[2] < 1 || parts[2] > 31) {
			return;
		}
		hidden.value = formatGregorian(jalaliToGregorian(parts[0], parts[1], parts[2]));
	}

	function initInput(input) {
		var gregorian = input.getAttribute('data-gregorian') || '';
		var match = gregorian.match(/^(\d{4})-(\d{2})-(\d{2})$/);
		if (!match) {
			return;
		}
		input.value = formatJalali(gregorianToJalali(parseInt(match[1], 10), parseInt(match[2], 10), parseInt(match[3], 10)));
	}

	function monthLength(jy, jm) {
		if (jm <= 6) {
			return 31;
		}
		if (jm <= 11) {
			return 30;
		}
		return jalaliToGregorian(jy + 1, 1, 1)[2] === 20 ? 30 : 29;
	}

	function firstWeekday(jy, jm) {
		var gregorian = jalaliToGregorian(jy, jm, 1);
		var date = new Date(gregorian[0], gregorian[1] - 1, gregorian[2]);
		return (date.getDay() + 1) % 7;
	}

	function closePicker() {
		if (activePicker) {
			activePicker.remove();
			activePicker = null;
		}
	}

	function buildPicker(input, jy, jm) {
		closePicker();

		var picker = document.createElement('div');
		picker.className = 'ofogh-call-btn-jalali-picker';
		picker.dir = 'rtl';
		picker.innerHTML = '<div class="ofogh-call-btn-jalali-picker__head"><button type="button" data-prev>‹</button><strong></strong><button type="button" data-next>›</button></div><div class="ofogh-call-btn-jalali-picker__week"></div><div class="ofogh-call-btn-jalali-picker__days"></div>';

		var title = picker.querySelector('strong');
		var week = picker.querySelector('.ofogh-call-btn-jalali-picker__week');
		var days = picker.querySelector('.ofogh-call-btn-jalali-picker__days');
		var state = { year: jy, month: jm };

		weekDays.forEach(function (day) {
			var span = document.createElement('span');
			span.textContent = day;
			week.appendChild(span);
		});

		function render() {
			title.textContent = monthNames[state.month - 1] + ' ' + state.year;
			days.innerHTML = '';

			for (var blank = 0; blank < firstWeekday(state.year, state.month); blank++) {
				days.appendChild(document.createElement('span'));
			}

			for (var day = 1; day <= monthLength(state.year, state.month); day++) {
				var button = document.createElement('button');
				button.type = 'button';
				button.textContent = day;
				button.setAttribute('data-day', String(day));
				days.appendChild(button);
			}
		}

		picker.querySelector('[data-prev]').addEventListener('click', function () {
			state.month--;
			if (state.month < 1) {
				state.month = 12;
				state.year--;
			}
			render();
		});

		picker.querySelector('[data-next]').addEventListener('click', function () {
			state.month++;
			if (state.month > 12) {
				state.month = 1;
				state.year++;
			}
			render();
		});

		days.addEventListener('click', function (event) {
			var button = event.target.closest('[data-day]');
			if (!button) {
				return;
			}
			input.value = formatJalali([state.year, state.month, parseInt(button.getAttribute('data-day'), 10)]);
			syncFromVisible(input);
			closePicker();
		});

		document.body.appendChild(picker);
		var rect = input.getBoundingClientRect();
		picker.style.top = (window.scrollY + rect.bottom + 8) + 'px';
		picker.style.left = (window.scrollX + rect.left) + 'px';
		activePicker = picker;
		render();
	}

	document.querySelectorAll('[data-ofogh-jalali-date]').forEach(function (input) {
		initInput(input);
		input.addEventListener('change', function () {
			syncFromVisible(input);
		});
		input.addEventListener('focus', function () {
			var parts = parseJalali(input.value) || gregorianToJalali(new Date().getFullYear(), new Date().getMonth() + 1, new Date().getDate());
			buildPicker(input, parts[0], parts[1]);
		});

		var trigger = input.parentNode ? input.parentNode.querySelector('[data-ofogh-jalali-trigger]') : null;
		if (trigger) {
			trigger.addEventListener('click', function () {
				var parts = parseJalali(input.value) || gregorianToJalali(new Date().getFullYear(), new Date().getMonth() + 1, new Date().getDate());
				buildPicker(input, parts[0], parts[1]);
				input.focus();
			});
		}
	});

	document.addEventListener('click', function (event) {
		if (!activePicker) {
			return;
		}
		if (event.target.closest('.ofogh-call-btn-jalali-picker') || event.target.closest('.ofogh-call-btn-jalali-field')) {
			return;
		}
		closePicker();
	});
}());
