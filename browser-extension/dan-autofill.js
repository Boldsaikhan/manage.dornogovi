/**
 * ДАН (Үндэсний танилт, нэвтрэлтийн систем) — автомат бөглөлт.
 *
 * Урсгал:
 *   1. «Нэг удаагийн код» таб руу шилжинэ.
 *   2. «Регистрийн дугаар» дэд табыг сонгоно.
 *   3. Регистрийн 2 үсгийг эхний хоёр талбарт, 8 оронг дугаарын талбарт бөглөнө.
 *   4. ДАН нууц үгийг бөглөнө.
 *   5. «Нэвтрэх» дарж баталгаажуулах кодыг утас руу илгээнэ.
 *
 * Баталгаажуулах кодыг ЗӨВХӨН хэрэглэгч өөрөө оруулна — ДАН-ы OTP-г тойрох
 * боломжгүй бөгөөд оролдохгүй. Бид зөвхөн код илгээгдэх хүртэлх алхмуудыг
 * гүйцэтгээд, кодын талбарт фокус тавьж өгнө.
 */

(() => {
    const MAX_WAIT_MS = 25_000;
    const POLL_MS = 400;

    let credential = null;
    let filled = false;
    let submitted = false;

    const visible = (el) => {
        if (! el || el.disabled) return false;

        const rect = el.getBoundingClientRect();

        return rect.width > 0 && rect.height > 0;
    };

    const text = (el) => (el.textContent ?? '').replace(/\s+/g, ' ').trim();

    /** Native setter — Nuxt/Vue/React төлөвөө мэдэрнэ. */
    const setValue = (el, value) => {
        const proto = el instanceof HTMLTextAreaElement
            ? HTMLTextAreaElement.prototype
            : HTMLInputElement.prototype;

        Object.getOwnPropertyDescriptor(proto, 'value').set.call(el, value);

        el.dispatchEvent(new Event('input', { bubbles: true }));
        el.dispatchEvent(new Event('change', { bubbles: true }));
    };

    /** Текстээр нь товч/таб олж дарна. Аль хэдийн идэвхтэй бол дарахгүй. */
    const clickByText = (pattern) => {
        const el = [...document.querySelectorAll('button, a, [role="tab"], [role="button"], li, div')]
            .filter(visible)
            .filter((node) => node.children.length <= 2)
            .find((node) => pattern.test(text(node)));

        if (! el) return false;

        const active = el.getAttribute('aria-selected') === 'true'
            || /\b(active|selected)\b/.test(el.className ?? '');

        if (! active) el.click();

        return true;
    };

    const passwordField = () => [...document.querySelectorAll('input[type="password"]')].find(visible) ?? null;

    /**
     * Регистрийн дугаарын талбар — placeholder/name/aria-аар танина.
     */
    const registerField = () => {
        const inputs = [...document.querySelectorAll('input')].filter(visible);

        return inputs.find((el) => {
            const hay = [el.placeholder, el.name, el.id, el.getAttribute('aria-label')]
                .filter(Boolean)
                .join(' ')
                .toLowerCase();

            return /регистр|register|rd|regnum/.test(hay) && el.type !== 'password';
        }) ?? null;
    };

    /**
     * Регистрийн 2 үсгийн талбарууд — дугаарын талбарын өмнөх богино input/select.
     */
    const letterFields = (numberField) => {
        const all = [...document.querySelectorAll('input, select')].filter(visible);
        const index = all.indexOf(numberField);

        if (index < 1) return [];

        return all
            .slice(0, index)
            .filter((el) => el.tagName === 'SELECT' || (el.maxLength === 1 || el.maxLength === -1))
            .slice(-2);
    };

    const setLetter = (el, letter) => {
        if (el.tagName === 'SELECT') {
            const option = [...el.options].find((o) => (o.value || o.textContent).trim().toUpperCase() === letter);

            if (option) {
                el.value = option.value;
                el.dispatchEvent(new Event('change', { bubbles: true }));
            }

            return;
        }

        setValue(el, letter);
    };

    const submit = () => {
        const button = [...document.querySelectorAll('button, input[type="submit"], [role="button"]')]
            .filter(visible)
            .find((el) => /^нэвтрэх$|log\s?in|sign\s?in/i.test(text(el) || el.value || ''));

        if (button) {
            button.click();

            return true;
        }

        const field = passwordField();

        if (field) {
            field.dispatchEvent(
                new KeyboardEvent('keydown', { key: 'Enter', code: 'Enter', keyCode: 13, bubbles: true }),
            );

            return true;
        }

        return false;
    };

    /** Код илгээгдсэний дараа — кодын талбарт фокус тавьж, зөвлөмж харуулна. */
    const focusOtpField = () => {
        const field = [...document.querySelectorAll('input')]
            .filter(visible)
            .find((el) => {
                const hay = [el.placeholder, el.name, el.id, el.getAttribute('aria-label')]
                    .filter(Boolean)
                    .join(' ')
                    .toLowerCase();

                return /код|code|otp|sms/.test(hay);
            });

        if (! field) return false;

        field.focus();

        return true;
    };

    /** Богино мэдэгдэл — юу болсныг хэрэглэгч харна. */
    const toast = (message) => {
        const box = document.createElement('div');

        box.textContent = message;
        box.style.cssText = [
            'position:fixed', 'z-index:2147483647', 'right:16px', 'bottom:16px',
            'max-width:22rem', 'padding:10px 14px', 'border-radius:10px',
            'background:#15335d', 'color:#fff', 'font:500 13px/1.45 system-ui,Arial,sans-serif',
            'box-shadow:0 8px 24px rgba(15,23,42,.25)',
        ].join(';');

        document.body.appendChild(box);
        setTimeout(() => box.remove(), 6000);
    };

    const fill = () => {
        if (filled || ! credential) return false;

        // 1-2. Табуудыг сонгоно (байхгүй бол алгасна).
        clickByText(/нэг удаагийн код|one[- ]time/i);
        clickByText(/^регистрийн дугаар$/i);

        const numberField = registerField();
        const pwField = passwordField();

        if (! numberField || ! pwField) return false;

        const register = (credential.username ?? '').trim().toUpperCase();
        const letters = register.slice(0, 2);
        const digits = register.slice(2);

        // 3. Үсэг + дугаар.
        const boxes = letterFields(numberField);

        if (boxes.length === 2) {
            setLetter(boxes[0], letters[0]);
            setLetter(boxes[1], letters[1]);
            setValue(numberField, digits);
        } else {
            // Нэг талбарт бүтнээр нь хүлээж авдаг хувилбар.
            setValue(numberField, register);
        }

        // 4. Нууц үг.
        setValue(pwField, credential.password);

        filled = true;

        toast('Manage: ДАН-ы мэдээллийг бөглөлөө. Баталгаажуулах кодоо оруулна уу.');

        // 5. Код илгээх — маягт төлөвөө шинэчлэх зав өгөөд илгээнэ.
        setTimeout(() => {
            submitted = submit();

            // Код ирэх хүртэл хүлээгээд, талбарт нь фокус тавина.
            let tries = 0;
            const otpTimer = setInterval(() => {
                if (focusOtpField() || ++tries > 25) clearInterval(otpTimer);
            }, 400);
        }, 350);

        credential = null;

        return true;
    };

    const start = (entry) => {
        if (! entry) return;

        credential = entry;

        if (fill()) return;

        const started = Date.now();
        const observer = new MutationObserver(() => fill());

        observer.observe(document.documentElement, { childList: true, subtree: true });

        const timer = setInterval(() => {
            if (filled || Date.now() - started > MAX_WAIT_MS) {
                clearInterval(timer);
                observer.disconnect();
                credential = null;
            } else {
                fill();
            }
        }, POLL_MS);
    };

    chrome.runtime.sendMessage({ type: 'take', host: location.hostname, mode: 'dan' }, (entry) => {
        if (chrome.runtime.lastError) return;

        start(entry);
    });
})();
