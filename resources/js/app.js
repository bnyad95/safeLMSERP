import './bootstrap';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.start();

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
