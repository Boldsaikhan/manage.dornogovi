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
 * Энэ файл нь ширээний програмын preload дотроос дуудагдана — chrome.* API-гүй.
 *
 * Баталгаажуулах кодыг ЗӨВХӨН хэрэглэгч өөрөө оруулна — ДАН-ы OTP-г тойрох
 * боломжгүй бөгөөд оролдохгүй. Бид зөвхөн код илгээгдэх хүртэлх алхмуудыг
 * гүйцэтгээд, кодын талбарт фокус тавьж өгнө.
 */

function runDanAutofill(entry, { onFilled = () => {}, typeKeys = null, log = () => {} } = {}) {
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

        try {
            el.focus();
        } catch {
            // Фокус авахгүй байж болно.
        }

        Object.getOwnPropertyDescriptor(proto, 'value').set.call(el, value);

        el.dispatchEvent(new Event('input', { bubbles: true }));
        el.dispatchEvent(new Event('change', { bubbles: true }));
        el.dispatchEvent(new Event('blur', { bubbles: true }));
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
    /**
     * Үсгийн нүднүүд.
     *
     * Энэ маягтад тэдгээр нь `div.letter-selector` — дарахад үсгийн
     * жагсаалт гарч ирдэг сонгогч. Класс нь олдохгүй бол байрлалаар
     * нь хайна.
     */
    const letterBoxes = (numberField) => {
        const byClass = [...document.querySelectorAll('.letter-selector')].filter(visible);

        if (byClass.length >= 2) {
            return byClass.slice(0, 2);
        }

        return letterFields(numberField);
    };

    const letterFields = (numberField) => {
        /*
         * Байрлалаар нь олно — DOM-ийн дараалалд найдахгүй.
         *
         * Энэ маягтад үсгийн нүднүүд нь дугаарын талбарын ДАРАА бичигдсэн
         * байж, зүүн талд нь харагддаг (flex дараалал). Өмнө нь зөвхөн
         * өмнөх элементүүдээс хайдаг тул огт олддоггүй байв.
         */
        const box = numberField.getBoundingClientRect();

        return [...document.querySelectorAll('input, select')]
            .filter(visible)
            .filter((el) => el !== numberField && el.type !== 'password')
            .filter((el) => {
                const rect = el.getBoundingClientRect();

                // Нэг мөрөнд, дугаарын талбарын зүүн талд, нарийхан нүд.
                return Math.abs(rect.top - box.top) < 24
                    && rect.right <= box.left + 6
                    && rect.width < 90;
            })
            .sort((a, b) => a.getBoundingClientRect().left - b.getBoundingClientRect().left)
            .slice(-2);
    };

    /**
     * Хүний бичилт шиг оруулна.
     *
     * Нэг тэмдэгтийн нүднүүд нь товчлуурын үйлдлээр л утгаа хүлээн
     * авдаг байж болох тул эхлээд execCommand('insertText')-ээр
     * оролдоод, бүтэхгүй бол шууд утга онооно.
     */
    const typeInto = (el, value) => {
        try {
            el.focus();
            el.select?.();

            if (document.execCommand('insertText', false, value) && el.value === value) {
                el.dispatchEvent(new Event('change', { bubbles: true }));
                el.dispatchEvent(new KeyboardEvent('keyup', { key: value, bubbles: true }));

                return true;
            }
        } catch {
            // execCommand дэмжигдээгүй — доорх аргаар оролдоно.
        }

        setValue(el, value);

        return el.value === value;
    };

    /**
     * Зөвхөн монгол (кирилл) үсэг бичнэ.
     *
     * Хадгалсан регистр нь латин харагдацтай үсэгтэй байж болох тул
     * (жишээ нь Y, X) кирилл дүйцлээр нь солино — эс бөгөөс маягт
     * хүлээж авахгүй, нүд хоосон үлддэг.
     */
    const LATIN_TO_CYRILLIC = {
        A: 'А', B: 'В', C: 'С', E: 'Е', H: 'Н', I: 'И', J: 'Ж',
        K: 'К', M: 'М', N: 'Н', O: 'О', P: 'Р', S: 'С', T: 'Т',
        U: 'У', X: 'Х', Y: 'Ү', Z: 'З', D: 'Д', G: 'Г', L: 'Л',
        R: 'Р', F: 'Ф', V: 'В', W: 'В', Q: 'Х',
    };

    const toCyrillic = (letter) => {
        const upper = String(letter ?? '').toUpperCase();

        if (/^[А-ЯЁӨҮ]$/.test(upper)) {
            return upper;
        }

        return LATIN_TO_CYRILLIC[upper] ?? null;
    };

    /** Сонгогчийн одоогийн үсэг. */
    const chosenLetter = (el) => (el.value ?? text(el) ?? '').trim().toUpperCase();

    /**
     * Жагсаалтаас үсгийг сонгоно.
     *
     * Сонгогч дээр дарахад үсгийн жагсаалт гарч ирдэг тул тэндээс яг
     * тэр үсэгтэй мөрийг олж дарна. Жагсаалт нь сонгогчийн ойролцоо
     * гарч ирдэг тул зайгаар нь шүүж, санамсаргүй элемент дарахгүй.
     */
    const pickFromList = (selector, letter) => {
        const anchorRect = selector.getBoundingClientRect();

        const option = [...document.querySelectorAll('li, span, div, button, a, p')]
            .filter(visible)
            .filter((el) => el !== selector && el.children.length === 0)
            .filter((el) => text(el).toUpperCase() === letter)
            .find((el) => {
                const rect = el.getBoundingClientRect();

                return Math.abs(rect.left - anchorRect.left) < 320
                    && Math.abs(rect.top - anchorRect.top) < 460;
            });

        if (! option) {
            return false;
        }

        option.click();

        return true;
    };

    const setLetter = (el, rawLetter) => {
        const letter = toCyrillic(rawLetter);

        // Кирилл биш бол огт бичихгүй.
        if (! letter) {
            return;
        }

        if (el.tagName === 'SELECT') {
            const option = [...el.options].find((o) => (o.value || o.textContent).trim().toUpperCase() === letter);

            if (option) {
                el.value = option.value;
                el.dispatchEvent(new Event('change', { bubbles: true }));
            }

            return;
        }

        // Input биш сонгогч бол дарж жагсаалтаас нь сонгоно.
        if (el.tagName !== 'INPUT') {
            if (chosenLetter(el) === letter) {
                return;
            }

            el.click();

            // Жагсаалт гарч ирэх зав өгөөд сонгоно.
            setTimeout(() => pickFromList(el, letter), 120);
            setTimeout(() => {
                if (chosenLetter(el) !== letter) pickFromList(el, letter);
            }, 420);

            return;
        }

        /*
         * Эхлээд бодит товчлуураар — нэг тэмдэгтийн нүд зөвхөн түүнийг
         * хүлээн авдаг. Боломжгүй бол DOM-оор бичнэ.
         */
        if (typeKeys) {
            try {
                el.focus();
                el.select?.();
                typeKeys(letter);

                return;
            } catch {
                // Доорх аргаар үргэлжилнэ.
            }
        }

        typeInto(el, letter);
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


    /**
     * Давталтын хамгаалалт.
     *
     * «Төхөөрөмжид санасан» мэдээлэл нь хадгалагдсаар байдаг тул нэвтрэлт
     * амжилтгүй болоод нэвтрэх хуудас руу буцахад дахин бөглөөд дахин илгээж,
     * төгсгөлгүй давтдаг байв. Тиймээс нэг session-д НЭГ л удаа автоматаар
     * илгээнэ. Платформоос шинээр дарж ирсэн бол дахин зөвшөөрнө.
     */
    const ATTEMPT_KEY = 'md_autofill_attempt';
    const ATTEMPT_TTL_MS = 10 * 60 * 1000;

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
            // ignore
        }
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
        // Үсгүүдийг кирилл болгож жигдрүүлнэ.
        const letters = [...register.slice(0, 2)].map((ch) => toCyrillic(ch) ?? ch);
        const digits = register.slice(2);

        // 3. Үсэг + дугаар.
        const boxes = letterBoxes(numberField);

        // Оношлоход: аль талбарууд олдсоныг тэмдэглэнэ.
        try {
            const describe = (el) => [
                el.tagName.toLowerCase(),
                el.type,
                'max=' + el.maxLength,
                'ph=' + (el.placeholder || '-'),
                'w=' + Math.round(el.getBoundingClientRect().width),
            ].join('/');

            log('нүднүүд: ' + boxes.map(describe).join(' | ')
                + ' || дугаар: ' + describe(numberField));

            // Үсгийн нүд олдоогүй бол эргэн тойрны бүтцийг бүртгэнэ.
            if (boxes.length !== 2) {
                const row = numberField.parentElement?.parentElement
                    ?? numberField.parentElement
                    ?? document.body;

                const near = [...row.querySelectorAll('*')]
                    .filter((el) => el.getBoundingClientRect().width > 0)
                    .slice(0, 22)
                    .map((el) => [
                        el.tagName.toLowerCase(),
                        (String(el.className || '').split(' ')[0] || '-'),
                        'r=' + (el.getAttribute('role') || '-'),
                        'ce=' + (el.isContentEditable ? 1 : 0),
                        'w=' + Math.round(el.getBoundingClientRect().width),
                        't=' + (el.textContent || '').trim().slice(0, 6),
                    ].join('/'))
                    .join(' | ');

                log('бүтэц: ' + near);
            }
        } catch {
            // Бүртгэж чадсангүй.
        }
        let split = false;

        if (boxes.length === 2) {
            // Нэг нэгээр нь — хоёулаа зэрэг нээгдвэл эхнийх нь хаагдана.
            setLetter(boxes[0], letters[0]);
            setTimeout(() => setLetter(boxes[1], letters[1]), 700);

            // Үнэхээр бичигдсэн эсэхийг шалгана — үгүй бол бүтнээр нь өгнө.
            // Сонгогч нь оролт биш байж болох тул бичвэрээр нь шалгана.
            split = true;
        }

        /*
         * Үсгийн нүд ажиллаагүй бол дугаарын талбарын урт нь аль
         * хэлбэрийг хүлээж авахыг хэлнэ: 8 бол зөвхөн орон, эс бөгөөс
         * регистрийг бүтнээр нь.
         */
        const digitsOnly = split || numberField.maxLength === digits.length;

        setValue(numberField, digitsOnly ? digits : register);

        // 4. Нууц үг. (credential нь доор цэвэрлэгддэг тул хуулж авна.)
        const password = credential.password;

        setValue(pwField, password);

        /*
         * Маягт дахин зурагдахад бөглөсөн утга арилдаг тул хэсэг
         * хугацаанд хянаж, арилсныг нь дахин бичнэ.
         */
        const wanted = digitsOnly ? digits : register;
        let guards = 0;
        let letterTries = 0;
        const guard = setInterval(() => {
            if (++guards > 10 || ! numberField.isConnected) {
                clearInterval(guard);

                return;
            }

            // Үсгийг цөөхөн удаа л дахин оролдоно — гар оролт давхарлахгүй.
            if (boxes.length === 2 && letterTries < 3) {
                // Нэг удаад нэгийг нь засна.
                const wrong = chosenLetter(boxes[0]) !== letters[0] ? 0
                    : (chosenLetter(boxes[1]) !== letters[1] ? 1 : -1);

                if (wrong >= 0) {
                    letterTries++;
                    setLetter(boxes[wrong], letters[wrong]);
                }
            }

            if (guards === 6) {
                log('сонгосон үсэг: ' + boxes.map(chosenLetter).join(',') + ' (хүсэх: ' + letters.join(',') + ')');
            }

            if (numberField.value !== wanted) setValue(numberField, wanted);
            if (pwField.value !== password) setValue(pwField, password);
        }, 400);

        filled = true;

        try {
            onFilled();
        } catch {
            // Мэдэгдэж чадсангүй — бөглөлт хэвийн үргэлжилнэ.
        }

        const maySubmit = credential.source === 'pending' || ! alreadySubmitted();

        toast(maySubmit
            ? 'Manage: ДАН-ы мэдээллийг бөглөлөө. Баталгаажуулах кодоо оруулна уу.'
            : 'Manage: мэдээллийг бөглөлөө. «Нэвтрэх» дарна уу.');

        if (maySubmit) {
            markSubmitted();

            // 5. Код илгээх — маягт төлөвөө шинэчлэх зав өгөөд илгээнэ.
            setTimeout(() => {
                submitted = submit();

                // Код ирэх хүртэл хүлээгээд, талбарт нь фокус тавина.
                let tries = 0;
                const otpTimer = setInterval(() => {
                    if (focusOtpField() || ++tries > 25) clearInterval(otpTimer);
                }, 400);
            }, 2400);
        }

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

    start(entry);
}

module.exports = { runDanAutofill };
