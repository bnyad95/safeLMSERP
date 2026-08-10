import './bootstrap';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.start();

const initializeFinanceDashboardCharts = async () => {
	const root = document.querySelector('[data-finance-charts]');
	const payloadElement = document.getElementById('finance-dashboard-chart-data');

	if (!root || !payloadElement) {
		return;
	}

	let source;
	try {
		source = JSON.parse(payloadElement.textContent);
	} catch (error) {
		return;
	}

	const { default: Chart } = await import('chart.js/auto');

	let currency = source.currencies?.[0] || 'IQD';
	let charts = [];
	const statuses = ['paid', 'partial', 'open', 'overdue'];
	const agingBuckets = ['1-30 days', '31-60 days', '61-90 days', '90+ days'];
	const colors = {
		blue: '#2563eb',
		emerald: '#059669',
		amber: '#d97706',
		red: '#dc2626',
	};
	const departmentPalette = ['#2563eb', '#059669', '#d97706', '#dc2626', '#7c3aed', '#0891b2', '#db2777', '#65a30d', '#ea580c', '#4f46e5'];

	const valueFor = (rows, matcher, field) => Number(rows.find(matcher)?.[field] || 0);
	const money = (value) => new Intl.NumberFormat('en-US', {
		maximumFractionDigits: currency === 'IQD' ? 0 : 2,
	}).format(value);
	const compactNumber = (value) => new Intl.NumberFormat('en-US', {
		notation: 'compact',
		maximumFractionDigits: 1,
	}).format(value);
	const localDate = (date) => [
		date.getFullYear(),
		String(date.getMonth() + 1).padStart(2, '0'),
		String(date.getDate()).padStart(2, '0'),
	].join('-');
	const openFiltered = (baseUrl, params) => {
		const url = new URL(baseUrl, window.location.origin);
		Object.entries(params).forEach(([key, value]) => {
			if (value !== null && value !== undefined && value !== '') {
				url.searchParams.set(key, value);
			}
		});
		window.location.href = url.toString();
	};
	const theme = () => {
		const dark = document.documentElement.classList.contains('dark');
		return {
			text: dark ? '#cbd5e1' : '#475569',
			grid: dark ? 'rgba(71, 85, 105, 0.35)' : 'rgba(203, 213, 225, 0.7)',
		};
	};
	const baseOptions = (axisFormatter = (value) => value, tooltipFormatter = axisFormatter, tooltipUnit = '') => {
		const palette = theme();
		return {
			responsive: true,
			maintainAspectRatio: false,
			interaction: { intersect: false, mode: 'index' },
			plugins: {
				legend: { display: false },
				tooltip: {
					callbacks: {
						label: (context) => `${context.dataset.label}: ${tooltipFormatter(context.raw)}${tooltipUnit ? ` ${tooltipUnit}` : ''}`,
					},
				},
			},
			scales: {
				x: { ticks: { color: palette.text }, grid: { display: false }, border: { color: palette.grid } },
				y: { beginAtZero: true, ticks: { color: palette.text, callback: axisFormatter }, grid: { color: palette.grid }, border: { display: false } },
			},
		};
	};
	const createChart = (id, config) => {
		const canvas = document.getElementById(id);
		if (!canvas) return null;
		const chart = new Chart(canvas, config);
		charts.push(chart);
		return chart;
	};

	const render = () => {
		charts.forEach((chart) => chart.destroy());
		charts = [];

		const collectionRows = source.collections.filter((row) => row.currency === currency);
		const collectionValues = source.dates.map((date) => valueFor(collectionRows, (row) => row.date === date, 'amount'));
		const collectionOptions = baseOptions(compactNumber, money, currency);
		collectionOptions.onClick = (_event, elements) => {
			if (!elements.length) return;
			const date = source.dates[elements[0].index];
			openFiltered(source.financeUrl, { type: 'payment', currency, date_from: date, date_to: date });
		};
		createChart('finance-collections-chart', {
			type: 'line',
			data: {
				labels: source.dates.map((date) => new Intl.DateTimeFormat('en', { month: 'short', day: 'numeric' }).format(new Date(`${date}T00:00:00`))),
				datasets: [{ label: 'Collected', data: collectionValues, borderColor: colors.emerald, backgroundColor: 'rgba(5, 150, 105, 0.12)', fill: true, tension: 0.3, pointRadius: 2, pointHoverRadius: 5 }],
			},
			options: collectionOptions,
		});

		const departmentRows = source.outstandingByDepartment.filter((row) => row.currency === currency).slice(0, 10);
		const departmentOptions = baseOptions(compactNumber, money, currency);
		departmentOptions.onClick = (_event, elements) => {
			if (!elements.length) return;
			openFiltered(source.financeUrl, { department_id: departmentRows[elements[0].index]?.department_id || '', currency });
		};
		createChart('finance-department-chart', {
			type: 'bar',
			data: {
				labels: departmentRows.map((row) => row.department),
				datasets: [{
					label: 'Outstanding',
					data: departmentRows.map((row) => row.balance),
					backgroundColor: departmentRows.map((_, index) => departmentPalette[index % departmentPalette.length]),
					borderRadius: 3,
					maxBarThickness: 48,
				}],
			},
			options: departmentOptions,
		});

		const statusRows = source.invoiceStatuses.filter((row) => row.currency === currency);
		const statusOptions = baseOptions((value) => Number(value).toLocaleString());
		statusOptions.onClick = (_event, elements) => {
			if (!elements.length) return;
			openFiltered(source.financeUrl, { payment_status: statuses[elements[0].index], currency });
		};
		createChart('finance-status-chart', {
			type: 'bar',
			data: {
				labels: statuses.map((status) => status.charAt(0).toUpperCase() + status.slice(1)),
				datasets: [{ label: 'Invoices', data: statuses.map((status) => valueFor(statusRows, (row) => row.status === status, 'total')), backgroundColor: [colors.emerald, colors.amber, colors.blue, colors.red], borderRadius: 3 }],
			},
			options: statusOptions,
		});

		const agingRows = source.overdueAging.filter((row) => row.currency === currency);
		const agingOptions = baseOptions(compactNumber, money, currency);
		agingOptions.onClick = (_event, elements) => {
			if (!elements.length) return;
			const index = elements[0].index;
			const today = new Date();
			const ranges = [[1, 30], [31, 60], [61, 90], [91, null]];
			const [minimum, maximum] = ranges[index];
			const dateTo = new Date(today);
			dateTo.setDate(today.getDate() - minimum);
			const dateFrom = maximum ? new Date(today) : null;
			if (dateFrom) dateFrom.setDate(today.getDate() - maximum);
			openFiltered(source.remindersUrl, {
				payment_status: 'overdue',
				currency,
				date_from: dateFrom ? localDate(dateFrom) : null,
				date_to: localDate(dateTo),
			});
		};
		createChart('finance-aging-chart', {
			type: 'bar',
			data: { labels: agingBuckets, datasets: [{ label: 'Overdue balance', data: agingBuckets.map((bucket) => valueFor(agingRows, (row) => row.bucket === bucket, 'balance')), backgroundColor: [colors.amber, '#ea580c', colors.red, '#991b1b'], borderRadius: 3 }] },
			options: agingOptions,
		});

		root.querySelectorAll('[data-chart-currency]').forEach((button) => {
			const active = button.dataset.chartCurrency === currency;
			button.setAttribute('aria-pressed', active ? 'true' : 'false');
			button.classList.toggle('bg-gray-900', active);
			button.classList.toggle('text-white', active);
			button.classList.toggle('dark:bg-blue-600', active);
			button.classList.toggle('text-gray-600', !active);
			button.classList.toggle('dark:text-gray-300', !active);
		});
	};

	root.querySelectorAll('[data-chart-currency]').forEach((button) => {
		button.addEventListener('click', () => {
			currency = button.dataset.chartCurrency;
			render();
		});
	});

	new MutationObserver(render).observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
	render();
};

initializeFinanceDashboardCharts();

document.querySelectorAll('form[data-submit-once]').forEach((form) => {
	form.addEventListener('submit', () => {
		const button = form.querySelector('button[type="submit"]');
		if (!button || button.disabled) {
			return;
		}

		button.disabled = true;
		button.classList.add('cursor-wait', 'opacity-70');
		button.textContent = button.dataset.submittingText || 'Processing...';
	});
});

const debounce = (callback, delay = 250) => {
	let timeoutId;

	return (...args) => {
		clearTimeout(timeoutId);
		timeoutId = window.setTimeout(() => callback(...args), delay);
	};
};

const renderSuggestions = (container, items) => {
	if (!container) {
		return;
	}

	if (!items.length) {
		container.innerHTML = '<div class="px-3 py-2 text-xs text-gray-500">No matches</div>';
		container.classList.remove('hidden');
		return;
	}

	container.innerHTML = items
		.map((item) => {
			const meta = item.meta ? `<p class="text-xs text-gray-500">${item.meta}</p>` : '';

			return `
				<a href="${item.url}" class="block border-b border-gray-100 px-3 py-2 last:border-b-0 hover:bg-gray-50">
					<p class="text-xs font-semibold uppercase tracking-wide text-indigo-600">${item.type}</p>
					<p class="text-sm font-medium text-gray-900">${item.title}</p>
					${meta}
				</a>
			`;
		})
		.join('');

	container.classList.remove('hidden');
};

document.querySelectorAll('[data-live-search]').forEach((form) => {
	const input = form.querySelector('[data-live-search-input]');
	const results = form.querySelector('[data-live-search-results]')
		|| form.parentElement?.querySelector('[data-live-search-results]');
	const endpoint = form.dataset.suggestionsUrl;

	if (!input || !results || !endpoint) {
		return;
	}

	const loadSuggestions = debounce(async () => {
		const q = input.value.trim();

		if (q.length < 2) {
			results.classList.add('hidden');
			results.innerHTML = '';
			return;
		}

		try {
			const response = await fetch(`${endpoint}?q=${encodeURIComponent(q)}`, {
				headers: {
					Accept: 'application/json',
					'X-Requested-With': 'XMLHttpRequest',
				},
			});

			if (!response.ok) {
				results.classList.add('hidden');
				return;
			}

			const payload = await response.json();
			renderSuggestions(results, Array.isArray(payload.items) ? payload.items : []);
		} catch (error) {
			results.classList.add('hidden');
		}
	}, 300);

	input.addEventListener('input', loadSuggestions);
	input.addEventListener('focus', loadSuggestions);

	document.addEventListener('click', (event) => {
		if (!form.contains(event.target) && !results.contains(event.target)) {
			results.classList.add('hidden');
		}
	});
});

document.querySelectorAll('[data-class-messages]').forEach((workspace) => {
	const thread = workspace.querySelector('[data-message-thread]');
	const participants = workspace.querySelector('[data-conversation-list]');
	const form = workspace.querySelector('[data-message-form]');
	const errorMessage = workspace.querySelector('[data-message-error]');
	let loading = false;

	if (!thread || !participants || !form || !workspace.dataset.threadUrl) {
		return;
	}

	const scrollToLatest = () => {
		thread.scrollTop = thread.scrollHeight;
	};

	const refreshConversation = async (forceScroll = false) => {
		if (loading || document.hidden) {
			return;
		}

		loading = true;
		const nearBottom = thread.scrollHeight - thread.scrollTop - thread.clientHeight < 120;

		try {
			const response = await fetch(workspace.dataset.threadUrl, {
				headers: {
					Accept: 'application/json',
					'X-Requested-With': 'XMLHttpRequest',
				},
			});

			if (!response.ok) {
				return;
			}

			const payload = await response.json();
			participants.innerHTML = payload.participants_html;

			if (thread.dataset.signature !== payload.signature) {
				thread.innerHTML = payload.messages_html;
				thread.dataset.signature = payload.signature;
				if (nearBottom || forceScroll) {
					requestAnimationFrame(scrollToLatest);
				}
			}
		} catch (error) {
			// A later polling cycle will retry after a temporary connection failure.
		} finally {
			loading = false;
		}
	};

	form.addEventListener('submit', async (event) => {
		event.preventDefault();
		const submitButton = form.querySelector('button[type="submit"]');
		errorMessage?.classList.add('hidden');
		submitButton.disabled = true;
		submitButton.textContent = 'Sending...';

		try {
			const response = await fetch(form.action, {
				method: 'POST',
				body: new FormData(form),
				headers: {
					Accept: 'application/json',
					'X-Requested-With': 'XMLHttpRequest',
				},
			});

			if (!response.ok) {
				const payload = await response.json().catch(() => ({}));
				const firstError = Object.values(payload.errors || {}).flat()[0] || 'The message could not be sent.';
				if (errorMessage) {
					errorMessage.textContent = firstError;
					errorMessage.classList.remove('hidden');
				}
				return;
			}

			form.querySelector('textarea[name="body"]').value = '';
			form.querySelector('input[name="attachment"]').value = '';
			await refreshConversation(true);
		} catch (error) {
			if (errorMessage) {
				errorMessage.textContent = 'Connection lost. Please try again.';
				errorMessage.classList.remove('hidden');
			}
		} finally {
			submitButton.disabled = false;
			submitButton.textContent = 'Send';
		}
	});

	requestAnimationFrame(scrollToLatest);
	window.setInterval(refreshConversation, 3000);
});

const ARCHIVE_PAGE_SELECTOR = '#archive-year-page[data-archive-year-page="1"]';

const isPrimaryNavigationClick = (event) => (
	event.button === 0
	&& !event.metaKey
	&& !event.ctrlKey
	&& !event.shiftKey
	&& !event.altKey
);

const replaceArchivePageFromHtml = (html) => {
	const parser = new DOMParser();
	const documentFragment = parser.parseFromString(html, 'text/html');
	const nextRoot = documentFragment.querySelector(ARCHIVE_PAGE_SELECTOR);
	const currentRoot = document.querySelector(ARCHIVE_PAGE_SELECTOR);

	if (!nextRoot || !currentRoot) {
		return false;
	}

	currentRoot.replaceWith(nextRoot);
	if (documentFragment.title) {
		document.title = documentFragment.title;
	}

	return true;
};

const loadArchivePage = async (url, pushState = true) => {
	const currentRoot = document.querySelector(ARCHIVE_PAGE_SELECTOR);
	if (!currentRoot) {
		window.location.href = url;
		return;
	}

	currentRoot.classList.add('opacity-60', 'pointer-events-none');

	try {
		const response = await fetch(url, {
			headers: {
				'X-Requested-With': 'XMLHttpRequest',
			},
			credentials: 'same-origin',
		});

		if (!response.ok) {
			throw new Error(`Failed with status ${response.status}`);
		}

		const html = await response.text();
		if (!replaceArchivePageFromHtml(html)) {
			throw new Error('Archive root not found in response');
		}

		if (pushState) {
			window.history.pushState({ archiveDrilldown: true }, '', url);
		}
	} catch (error) {
		window.location.href = url;
	}
};

document.addEventListener('click', (event) => {
	if (!isPrimaryNavigationClick(event)) {
		return;
	}

	const link = event.target.closest('a[data-archive-drilldown-link="1"]');
	if (!link) {
		return;
	}

	const url = link.getAttribute('href');
	if (!url) {
		return;
	}

	event.preventDefault();
	loadArchivePage(url, true);
});

window.addEventListener('popstate', () => {
	const hasArchivePage = document.querySelector(ARCHIVE_PAGE_SELECTOR);
	if (!hasArchivePage) {
		return;
	}

	if (!window.location.pathname.includes('/academic-year-archive/year')) {
		window.location.reload();
		return;
	}

	loadArchivePage(window.location.href, false);
});
