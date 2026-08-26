/**
 * Зорилтот системийн нэвтрэх маягтыг олж, хадгалсан мэдээллээр бөглөөд илгээнэ.
 *
 * SPA (Nuxt, React, Ant Design) дээр талбарын утгыг зүгээр л el.value = "..." гэж
 * өөрчлөхөд framework мэдэхгүй тул native setter ашиглаж, input/change үйлдлийг
 * гараар дуудаж өгөх шаардлагатай.
 */

(() => {
    const MAX_WAIT_MS = 20_000;
    const POLL_MS = 400;

    let credential = null;
    let done = false;

    const isVisible = (el) => {
        if (!el || el.disabled || el.readOnly) return false;
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

        if (candidates.length === 0) return null;

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

    const attempt = () => {
        if (done || !credential) return false;

        const passwordField = findPasswordField();
        if (!passwordField) return false;

        const usernameField = findUsernameField(passwordField);
        if (!usernameField) return false;

        done = true;

        setValue(usernameField, credential.username);
        setValue(passwordField, credential.password);

        // Framework-т төлөвөө шинэчлэх зав өгнө.
        setTimeout(() => submit(passwordField), 250);

        credential = null;

        return true;
    };

    chrome.runtime.sendMessage({ type: 'take', host: location.hostname, mode: 'password' }, (entry) => {
        if (chrome.runtime.lastError || !entry) return;

        credential = entry;

        if (attempt()) return;

        // SPA дээр маягт хожуу зурагддаг тул хэсэг хугацаанд хүлээнэ.
        const started = Date.now();
        const observer = new MutationObserver(() => attempt());

        observer.observe(document.documentElement, { childList: true, subtree: true });

        const timer = setInterval(() => {
            if (done || Date.now() - started > MAX_WAIT_MS) {
                clearInterval(timer);
                observer.disconnect();
                credential = null;
            } else {
                attempt();
            }
        }, POLL_MS);
    });
})();
