/**
 * Зорилтот системийн нэвтрэх маягтыг олж, өгөгдсөн мэдээллээр бөглөнө.
 *
 * SPA (Nuxt, React, Ant Design) дээр талбарын утгыг зүгээр л el.value = "…"
 * гэж өөрчлөхөд framework мэдэхгүй тул native setter ашиглаж, input/change
 * үйлдлийг гараар дуудна.
 *
 * Энэ файл нь хөтчийн өргөтгөлөөс биш, ширээний програмын preload дотроос
 * дуудагдана — chrome.* API-гүй, цэвэр DOM дээр ажиллана.
 */

const MAX_WAIT_MS = 20_000;
const POLL_MS = 400;

/** Нэвтрэлт давтахаас сэргийлэх түлхүүр. */
const ATTEMPT_KEY = 'md_autofill_attempt';
const ATTEMPT_TTL_MS = 10 * 60 * 1000;

const isVisible = (el) => {
    if (! el || el.disabled || el.readOnly) {
        return false;
    }

    const rect = el.getBoundingClientRect();

    return rect.width > 0 && rect.height > 0;
};

const setValue = (el, value) => {
    const proto = el instanceof HTMLTextAreaElement
        ? HTMLTextAreaElement.prototype
        : HTMLInputElement.prototype;

    Object.getOwnPropertyDescriptor(proto, 'value').set.call(el, value);

    el.dispatchEvent(new Event('input', { bubbles: true }));
    el.dispatchEvent(new Event('change', { bubbles: true }));
};

const findPasswordField = () =>
    [...document.querySelectorAll('input[type="password"]')].find(isVisible) ?? null;

const findUsernameField = (passwordField) => {
    const scope = passwordField.form ?? document;
    const candidates = [...scope.querySelectorAll('input')].filter(
        (el) => ['text', 'email', 'tel', ''].includes(el.type) && isVisible(el),
    );

    if (candidates.length === 0) {
        return null;
    }

    // Нууц үгийн талбарын өмнөх сүүлчийн талбарыг сонгоно.
    const before = candidates.filter(
        (el) => el.compareDocumentPosition(passwordField) & Node.DOCUMENT_POSITION_FOLLOWING,
    );

    return before.at(-1) ?? candidates[0];
};

const submit = (passwordField) => {
    const form = passwordField.form;

    if (form) {
        const button = form.querySelector(
            'button[type="submit"], input[type="submit"], button:not([type])',
        );

        if (button && isVisible(button)) {
            button.click();

            return;
        }

        if (typeof form.requestSubmit === 'function') {
            form.requestSubmit();

            return;
        }

        form.submit();

        return;
    }

    // Маягтгүй SPA: текстээр нь товчийг олохыг оролдоно.
    const button = [...document.querySelectorAll('button, [role="button"]')].find(
        (el) => isVisible(el) && /нэвтрэх|log\s?in|sign\s?in/i.test(el.textContent ?? ''),
    );

    if (button) {
        button.click();

        return;
    }

    passwordField.dispatchEvent(
        new KeyboardEvent('keydown', { key: 'Enter', code: 'Enter', keyCode: 13, bubbles: true }),
    );
};

const alreadySubmitted = () => {
    try {
        return Date.now() - Number(sessionStorage.getItem(ATTEMPT_KEY) || 0) < ATTEMPT_TTL_MS;
    } catch {
        return false;
    }
};

const markSubmitted = () => {
    try {
        sessionStorage.setItem(ATTEMPT_KEY, String(Date.now()));
    } catch {
        // Хадгалах боломжгүй бол алгасна.
    }
};

/**
 * Маягтыг бөглөж, боломжтой бол илгээнэ.
 *
 * @param {{username: string, password: string, source?: string}} credential
 */
function runAutofill(credential, { onFilled = () => {} } = {}) {
    if (! credential?.username) {
        return;
    }

    let done = false;

    const attempt = () => {
        if (done) {
            return false;
        }

        const passwordField = findPasswordField();

        if (! passwordField) {
            return false;
        }

        const usernameField = findUsernameField(passwordField);

        if (! usernameField) {
            return false;
        }

        done = true;

        setValue(usernameField, credential.username);
        setValue(passwordField, credential.password);

        try {
            onFilled();
        } catch {
            // Мэдэгдэж чадсангүй — бөглөлт нь хэвийн үргэлжилнэ.
        }

        /*
         * Платформоос шинээр дарсан бол заавал илгээнэ; санасан мэдээллээр
         * бол session-д нэг л удаа — эс бөгөөс амжилтгүй нэвтрэлт давтагдана.
         */
        const maySubmit = credential.source === 'pending' || ! alreadySubmitted();

        if (maySubmit) {
            markSubmitted();

            // Framework-т төлвөө шинэчлэх зав өгнө.
            setTimeout(() => submit(passwordField), 250);
        }

        return true;
    };

    if (attempt()) {
        return;
    }

    // SPA дээр маягт хожуу зурагддаг тул хэсэг хугацаанд хүлээнэ.
    const started = Date.now();
    const observer = new MutationObserver(() => attempt());

    observer.observe(document.documentElement, { childList: true, subtree: true });

    const timer = setInterval(() => {
        if (done || Date.now() - started > MAX_WAIT_MS) {
            clearInterval(timer);
            observer.disconnect();
        } else {
            attempt();
        }
    }, POLL_MS);
}

module.exports = { runAutofill };
