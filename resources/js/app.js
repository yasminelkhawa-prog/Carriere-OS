import './bootstrap';
import Alpine from 'alpinejs';
import $ from 'jquery';
import '../css/app.css';
import 'select2/dist/css/select2.css';

window.Alpine = Alpine;
window.$ = window.jQuery = $;

let hasShownSelect2Warning = false;
let hasAttemptedSelect2Attach = false;

const notifyToast = (message, type = 'warning') => {
    document.dispatchEvent(new CustomEvent('app:toast', { detail: { message, type } }));
};

const select2Matcher = (params, data) => {
    const term = (params.term || '').trim().toLowerCase();

    if (!term) {
        return data;
    }

    if (!data.text) {
        return null;
    }

    if (data.children && Array.isArray(data.children)) {
        const matchedChildren = data.children
            .map((child) => select2Matcher(params, child))
            .filter(Boolean);

        if (matchedChildren.length) {
            return { ...data, children: matchedChildren };
        }
    }

    return data.text.toLowerCase().includes(term) ? data : null;
};

const warnSelect2Fallback = () => {
    if (hasShownSelect2Warning) {
        return;
    }

    hasShownSelect2Warning = true;

    const fallbackMessage = document.body?.dataset?.select2Warning ?? 'Select2 unavailable.';
    notifyToast(fallbackMessage, 'warning');
};

const canUseSelect2 = () => Boolean(window.jQuery?.fn?.select2);

const ensureSelect2Loaded = async () => {
    if (canUseSelect2()) {
        return true;
    }

    if (hasAttemptedSelect2Attach) {
        return canUseSelect2();
    }

    hasAttemptedSelect2Attach = true;

    try {
        const select2Module = await import('select2');
        const attach = select2Module?.default;

        if (typeof attach === 'function') {
            attach(window, window.jQuery);
        }
    } catch (error) {
        console.warn('Unable to load Select2 module.', error);
    }

    return canUseSelect2();
};

const initSelect2 = async (root = document) => {
    const select2Ready = await ensureSelect2Loaded();
    const selects = root.querySelectorAll('select:not([data-native-select])');

    selects.forEach((select) => {
        if (select.dataset.select2Initialized === 'native' || select.dataset.select2Initialized === 'ready') {
            return;
        }

        if (!select2Ready) {
            select.dataset.select2Initialized = 'native';
            warnSelect2Fallback();
            return;
        }

        const $select = $(select);

        if ($select.hasClass('select2-hidden-accessible')) {
            select.dataset.select2Initialized = 'ready';
            return;
        }

        const placeholder = select.dataset.placeholder || '';
        const modal = select.closest('.js-modal');

        try {
            $select.select2({
                width: '100%',
                placeholder,
                allowClear: !select.required && !select.multiple,
                minimumResultsForSearch: 0,
                matcher: select2Matcher,
                dropdownParent: modal ? $(modal) : $(document.body),
            });

            // Select2 emits jQuery events; Alpine x-model listens for native DOM events.
            // Bridge both so reactive state stays in sync with Select2 selections.
            $select.on('select2:select select2:unselect select2:clear', () => {
                select.dispatchEvent(new Event('change', { bubbles: true }));
                select.dispatchEvent(new Event('input', { bubbles: true }));
            });

            select.dataset.select2Initialized = 'ready';
        } catch (error) {
            console.warn('Select2 initialization failed, falling back to native select.', error);
            select.dataset.select2Initialized = 'native';
            warnSelect2Fallback();
        }
    });
};

const portalMenuSelector = '[data-portal-menu-item]';
let activePortalMenuNode = null;

const releasePortalMenuLift = (delay = 130) => {
    const node = activePortalMenuNode;
    activePortalMenuNode = null;

    if (!(node instanceof HTMLElement)) {
        return;
    }

    window.setTimeout(() => {
        node.classList.remove('is-click-lifted');
    }, delay);
};

const initPortalMenuLiftAnimation = () => {
    if (window.__portalMenuLiftInitialized === true) {
        return;
    }

    window.__portalMenuLiftInitialized = true;

    if (window.matchMedia?.('(prefers-reduced-motion: reduce)')?.matches) {
        return;
    }

    document.addEventListener('pointerdown', (event) => {
        const target = event.target;
        if (!(target instanceof Element)) {
            return;
        }

        const item = target.closest(portalMenuSelector);
        if (!(item instanceof HTMLElement)) {
            return;
        }

        if (activePortalMenuNode instanceof HTMLElement && activePortalMenuNode !== item) {
            activePortalMenuNode.classList.remove('is-click-lifted');
        }

        activePortalMenuNode = item;
        item.classList.add('is-click-lifted');
    }, { passive: true });

    document.addEventListener('pointerup', () => {
        releasePortalMenuLift(130);
    }, { passive: true });

    document.addEventListener('pointercancel', () => {
        releasePortalMenuLift(0);
    }, { passive: true });

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter' && event.key !== ' ') {
            return;
        }

        const target = event.target;
        if (!(target instanceof Element)) {
            return;
        }

        const item = target.closest(portalMenuSelector);
        if (!(item instanceof HTMLElement)) {
            return;
        }

        activePortalMenuNode = item;
        item.classList.add('is-click-lifted');
    });

    document.addEventListener('keyup', (event) => {
        if (event.key !== 'Enter' && event.key !== ' ') {
            return;
        }

        releasePortalMenuLift(130);
    });

    window.addEventListener('blur', () => {
        releasePortalMenuLift(0);
    });
};

document.addEventListener('DOMContentLoaded', () => { void initSelect2(); });
document.addEventListener('app:select2-refresh', (event) => { void initSelect2(event.detail?.root ?? document); });

document.addEventListener('alpine:init', () => {
    Alpine.directive('select2', (el) => {
        void initSelect2(el.closest('form') ?? document);
    });
});

const observer = new MutationObserver((mutations) => {
    mutations.forEach((mutation) => {
        mutation.addedNodes.forEach((node) => {
            if (!(node instanceof HTMLElement)) {
                return;
            }

            if (node.matches('select')) {
                void initSelect2(node.parentElement ?? document);
                return;
            }

            if (node.querySelector('select')) {
                void initSelect2(node);
            }

            if (node.matches('[data-candidate-guide-bot]') || node.querySelector('[data-candidate-guide-bot]')) {
                initCandidateGuideBots(node);
            }

            if (node.matches('[data-recruiter-assistant-bot]') || node.querySelector('[data-recruiter-assistant-bot]')) {
                initRecruiterAssistantBots(node);
            }

            if (node.matches('[data-career-apply-assistant]') || node.querySelector('[data-career-apply-assistant]')) {
                initCareerApplyAssistants(node);
            }
        });
    });
});

const setCandidateGuideDisabled = (disabled) => {
    window.__candidateGuideDisabled = Boolean(disabled);

    document.querySelectorAll('[data-candidate-guide-bot]').forEach((node) => {
        if (!(node instanceof HTMLElement)) {
            return;
        }

        node.dataset.disabled = disabled ? 'true' : 'false';
        node.setAttribute('aria-hidden', disabled ? 'true' : 'false');
        node.classList.toggle('hidden', disabled);
    });
};

const appendGuideMessage = (messagesContainer, text, type = 'bot', source = '') => {
    if (!(messagesContainer instanceof HTMLElement)) {
        return;
    }

    const wrapper = document.createElement('div');
    wrapper.className = type === 'user'
        ? 'rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-xs text-slate-800'
        : 'rounded-lg border border-aura-200/60 bg-aura-50/80 px-2 py-1.5 text-xs text-slate-700';

    const body = document.createElement('p');
    body.textContent = text;
    wrapper.appendChild(body);

    if (source) {
        const meta = document.createElement('p');
        meta.className = 'mt-1 text-[11px] uppercase tracking-wide text-aura-700/90';
        meta.textContent = source;
        wrapper.appendChild(meta);
    }

    messagesContainer.appendChild(wrapper);
    messagesContainer.scrollTop = messagesContainer.scrollHeight;
};

const initCandidateGuideBot = (node) => {
    if (!(node instanceof HTMLElement) || node.dataset.guideBotInitialized === 'true') {
        return;
    }

    const endpoint = node.dataset.endpoint || '';
    const toggleButton = node.querySelector('[data-guide-toggle]');
    const panel = node.querySelector('[data-guide-panel]');
    const form = node.querySelector('[data-guide-form]');
    const messages = node.querySelector('[data-guide-messages]');

    if (
        !(toggleButton instanceof HTMLElement)
        || !(panel instanceof HTMLElement)
        || !(form instanceof HTMLFormElement)
        || !(messages instanceof HTMLElement)
    ) {
        return;
    }

    toggleButton.addEventListener('click', () => {
        panel.classList.toggle('hidden');
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        if (window.__candidateGuideDisabled === true || node.dataset.disabled === 'true') {
            appendGuideMessage(messages, 'Candidate Guide is disabled on this page.');
            return;
        }

        const messageField = form.querySelector('textarea[name="message"]');
        const submitButton = form.querySelector('button[type="submit"]');
        if (!(messageField instanceof HTMLTextAreaElement) || !(submitButton instanceof HTMLButtonElement)) {
            return;
        }

        const message = messageField.value.trim();
        if (!message || !endpoint) {
            return;
        }

        appendGuideMessage(messages, message, 'user');
        submitButton.disabled = true;

        try {
            const response = await fetch(endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '',
                },
                body: JSON.stringify({ message }),
            });

            const payload = await response.json().catch(() => ({}));

            if (!response.ok) {
                const firstValidationError = Object.values(payload?.errors || {})?.[0];
                const errorText = Array.isArray(firstValidationError) ? firstValidationError[0] : 'Unable to process your request right now.';
                appendGuideMessage(messages, String(errorText || 'Unable to process your request right now.'));
                return;
            }

            const sourceQuestion = payload?.source?.question ? String(payload.source.question) : '';
            const sourceCategory = payload?.source?.category ? String(payload.source.category) : '';
            const sourceLabel = sourceQuestion !== ''
                ? `${sourceCategory !== '' ? `${sourceCategory}: ` : ''}${sourceQuestion}`
                : '';
            appendGuideMessage(
                messages,
                String(payload?.answer || 'No answer available.'),
                'bot',
                sourceLabel
            );

            messageField.value = '';
        } catch (error) {
            appendGuideMessage(messages, 'Network error. Please try again.');
            console.warn('Candidate Guide request failed.', error);
        } finally {
            submitButton.disabled = false;
        }
    });

    node.dataset.guideBotInitialized = 'true';
};

const initCandidateGuideBots = (root = document) => {
    root.querySelectorAll?.('[data-candidate-guide-bot]').forEach((node) => {
        initCandidateGuideBot(node);
    });
};

const appendRecruiterAssistantMessage = (messagesContainer, text, type = 'bot') => {
    if (!(messagesContainer instanceof HTMLElement)) {
        return;
    }

    const wrapper = document.createElement('div');
    wrapper.className = type === 'user'
        ? 'rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-xs text-slate-800'
        : 'rounded-lg border border-success-200/60 bg-success-50/70 px-2 py-1.5 text-xs text-slate-700';

    const body = document.createElement('p');
    body.textContent = text;
    wrapper.appendChild(body);

    messagesContainer.appendChild(wrapper);
    messagesContainer.scrollTop = messagesContainer.scrollHeight;
};

const initRecruiterAssistantBot = (node) => {
    if (!(node instanceof HTMLElement) || node.dataset.assistantInitialized === 'true') {
        return;
    }

    const endpoint = node.dataset.endpoint || '';
    const processingError = node.dataset.processingError || 'Unable to process your request right now.';
    const networkError = node.dataset.networkError || 'Network error. Please try again.';
    const noAnswer = node.dataset.noAnswer || 'No answer available.';
    const toggleButton = node.querySelector('[data-assistant-toggle]');
    const panel = node.querySelector('[data-assistant-panel]');
    const form = node.querySelector('[data-assistant-form]');
    const messages = node.querySelector('[data-assistant-messages]');
    const promptButtons = Array.from(node.querySelectorAll('[data-assistant-prompt]'));
    const applicationSelect = node.querySelector('[data-assistant-application-select]');

    if (
        !(toggleButton instanceof HTMLElement)
        || !(panel instanceof HTMLElement)
        || !(form instanceof HTMLFormElement)
        || !(messages instanceof HTMLElement)
    ) {
        return;
    }

    toggleButton.addEventListener('click', () => {
        panel.classList.toggle('hidden');
    });

    promptButtons.forEach((button) => {
        if (!(button instanceof HTMLButtonElement)) {
            return;
        }

        button.addEventListener('click', () => {
            const messageField = form.querySelector('textarea[name="message"]');
            if (!(messageField instanceof HTMLTextAreaElement)) {
                return;
            }

            messageField.value = button.textContent?.trim() || '';
            form.requestSubmit();
        });
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        const messageField = form.querySelector('textarea[name="message"]');
        const submitButton = form.querySelector('button[type="submit"]');
        if (!(messageField instanceof HTMLTextAreaElement) || !(submitButton instanceof HTMLButtonElement)) {
            return;
        }

        const message = messageField.value.trim();
        if (!message || !endpoint) {
            return;
        }

        appendRecruiterAssistantMessage(messages, message, 'user');
        submitButton.disabled = true;

        try {
            const response = await fetch(endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '',
                },
                body: JSON.stringify({
                    message,
                    application_id: applicationSelect instanceof HTMLSelectElement
                        ? applicationSelect.value
                        : (node.dataset.applicationId || ''),
                }),
            });

            const payload = await response.json().catch(() => ({}));

            if (!response.ok) {
                const firstValidationError = Object.values(payload?.errors || {})?.[0];
                const errorText = Array.isArray(firstValidationError) ? firstValidationError[0] : processingError;
                appendRecruiterAssistantMessage(messages, String(errorText || processingError));
                return;
            }

            appendRecruiterAssistantMessage(messages, String(payload?.answer || noAnswer));
            messageField.value = '';
        } catch (error) {
            appendRecruiterAssistantMessage(messages, networkError);
            console.warn('Recruiter assistant request failed.', error);
        } finally {
            submitButton.disabled = false;
        }
    });

    node.dataset.assistantInitialized = 'true';
};

const initRecruiterAssistantBots = (root = document) => {
    root.querySelectorAll?.('[data-recruiter-assistant-bot]').forEach((node) => {
        initRecruiterAssistantBot(node);
    });
};

const appendCareerAssistantMessage = (messagesContainer, text, type = 'bot') => {
    if (!(messagesContainer instanceof HTMLElement)) {
        return;
    }

    const wrapper = document.createElement('div');
    wrapper.className = type === 'user'
        ? 'rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs text-slate-800'
        : 'rounded-lg border border-aura-200/60 bg-aura-50 px-3 py-2 text-xs text-slate-700';

    const body = document.createElement('p');
    body.textContent = text;
    wrapper.appendChild(body);

    messagesContainer.appendChild(wrapper);
    messagesContainer.scrollTop = messagesContainer.scrollHeight;
};

const initCareerApplyAssistant = (node) => {
    if (!(node instanceof HTMLElement) || node.dataset.careerAssistantInitialized === 'true') {
        return;
    }

    const promptsBase64 = node.dataset.promptsBase64 || '';
    const completeLabel = node.dataset.completeLabel || 'Completed';
    const startButton = node.querySelector('[data-career-assistant-start]');
    const sendButton = node.querySelector('[data-career-assistant-send]');
    const skipButton = node.querySelector('[data-career-assistant-skip]');
    const input = node.querySelector('[data-career-assistant-input]');
    const messages = node.querySelector('[data-career-assistant-messages]');
    const form = node.nextElementSibling instanceof HTMLFormElement ? node.nextElementSibling : null;
    const hiddenTranscript = form?.querySelector('input[name="assistant_answers_json"]');

    if (
        !(startButton instanceof HTMLButtonElement)
        || !(sendButton instanceof HTMLButtonElement)
        || !(skipButton instanceof HTMLButtonElement)
        || !(input instanceof HTMLInputElement)
        || !(messages instanceof HTMLElement)
        || !(hiddenTranscript instanceof HTMLInputElement)
    ) {
        return;
    }

    let prompts = [];
    try {
        const decodedPrompts = promptsBase64 !== ''
            ? window.atob(promptsBase64)
            : '[]';
        const parsed = JSON.parse(decodedPrompts);
        prompts = Array.isArray(parsed) ? parsed : [];
    } catch (error) {
        console.warn('Career apply assistant prompts failed to parse.', error);
        prompts = [];
    }

    const transcript = [];
    let currentIndex = -1;

    const syncTranscript = () => {
        hiddenTranscript.value = JSON.stringify(transcript);
    };

    const applyFieldValue = (prompt, answer) => {
        if (!form || !prompt || typeof prompt.field !== 'string' || prompt.field === '') {
            return;
        }

        const field = form.querySelector(`[name="${prompt.field}"]`);
        if (field instanceof HTMLInputElement || field instanceof HTMLTextAreaElement) {
            field.value = answer;
            field.dispatchEvent(new Event('input', { bubbles: true }));
            field.dispatchEvent(new Event('change', { bubbles: true }));
        }
    };

    const askPrompt = () => {
        currentIndex += 1;

        if (currentIndex >= prompts.length) {
            appendCareerAssistantMessage(messages, completeLabel, 'bot');
            input.value = '';
            input.disabled = true;
            sendButton.disabled = true;
            skipButton.disabled = true;
            return;
        }

        const prompt = prompts[currentIndex] || {};
        appendCareerAssistantMessage(messages, String(prompt.question || ''), 'bot');
        input.value = typeof prompt.prefill === 'string' ? prompt.prefill : '';
        input.placeholder = String(prompt.question || '');
        input.disabled = false;
        sendButton.disabled = false;
        skipButton.disabled = !Boolean(prompt.allow_skip);
        input.focus();
    };

    const recordAnswer = (answer, skipped = false) => {
        const prompt = prompts[currentIndex] || null;
        if (!prompt) {
            return;
        }

        const normalizedAnswer = skipped ? 'Skipped' : answer.trim();
        if (normalizedAnswer === '') {
            return;
        }

        transcript.push({
            question: String(prompt.question || ''),
            answer: normalizedAnswer,
        });
        syncTranscript();
        appendCareerAssistantMessage(messages, normalizedAnswer, 'user');

        if (!skipped) {
            applyFieldValue(prompt, normalizedAnswer);
        }

        askPrompt();
    };

    startButton.addEventListener('click', () => {
        if (currentIndex >= 0) {
            input.focus();
            return;
        }

        startButton.disabled = true;
        askPrompt();
    });

    sendButton.addEventListener('click', () => {
        if (currentIndex < 0) {
            startButton.click();
            return;
        }

        const answer = input.value.trim();
        if (answer === '') {
            input.focus();
            return;
        }

        recordAnswer(answer, false);
    });

    skipButton.addEventListener('click', () => {
        if (currentIndex < 0 || skipButton.disabled) {
            return;
        }

        recordAnswer('Skipped', true);
    });

    input.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter') {
            return;
        }

        event.preventDefault();
        sendButton.click();
    });

    node.dataset.careerAssistantInitialized = 'true';
};

const initCareerApplyAssistants = (root = document) => {
    root.querySelectorAll?.('[data-career-apply-assistant]').forEach((node) => {
        initCareerApplyAssistant(node);
    });
};

document.addEventListener('DOMContentLoaded', () => {
    initPortalMenuLiftAnimation();

    observer.observe(document.body, {
        childList: true,
        subtree: true,
    });

    initCandidateGuideBots(document);
    initRecruiterAssistantBots(document);
    initCareerApplyAssistants(document);

    const pageWantsGuideDisabled = document.querySelector('[data-guide-bot-disabled="true"]') !== null;
    if (pageWantsGuideDisabled) {
        setCandidateGuideDisabled(true);
    }
});

document.addEventListener('candidate-guide:disable', () => {
    setCandidateGuideDisabled(true);
});

document.addEventListener('candidate-guide:enable', () => {
    setCandidateGuideDisabled(false);
});

Alpine.start();
