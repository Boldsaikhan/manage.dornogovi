/**
 * Монгол регистрийн дугаараас нас, хүйс гаргана (2 үсэг + 8 орон).
 * 2000+ төрсөн: сард +20. Хүйс: сүүлийн өмнөх орон сондгой=эр, тэгш=эм.
 */

const LATIN_TO_CYRILLIC = {
    A: 'А', B: 'Б', V: 'В', G: 'Г', D: 'Д', E: 'Е', J: 'Ж', Z: 'З',
    I: 'И', K: 'К', L: 'Л', M: 'М', N: 'Н', O: 'О', P: 'П', R: 'Р',
    S: 'С', T: 'Т', U: 'У', F: 'Ф', H: 'Х', C: 'Ц', W: 'Ш', Y: 'Ү',
    X: 'Ө', Q: 'Ө',
};

const normalizeRegister = (value) => {
    const raw = String(value ?? '').replace(/\s+/g, '').toUpperCase();

    if (! raw) {
        return null;
    }

    const letters = [...raw.slice(0, 2)]
        .map((ch) => LATIN_TO_CYRILLIC[ch] || ch)
        .join('');
    const digits = raw.slice(2).replace(/\D+/g, '').slice(0, 8);
    const register = `${letters}${digits}`;

    return /^[\u0410-\u042F\u0401\u04AE\u04E8]{2}\d{8}$/.test(register) ? register : null;
};

/**
 * @param {string|null|undefined} value
 * @param {number} [asOfYear]
 * @returns {{ register: string, birth: string, age: number, gender: string }|null}
 */
export const parseMongolianRegister = (value, asOfYear = new Date().getFullYear()) => {
    const register = normalizeRegister(value);

    if (! register) {
        return null;
    }

    const digits = register.slice(2);
    const yy = Number(digits.slice(0, 2));
    let mm = Number(digits.slice(2, 4));
    const dd = Number(digits.slice(4, 6));
    const genderDigit = Number(digits.slice(6, 7));
    const currentYy = asOfYear % 100;

    let year;

    if (mm >= 21 && mm <= 32) {
        mm -= 20;
        year = 2000 + yy;
    } else {
        year = yy + (yy <= currentYy ? 2000 : 1900);
    }

    const birth = new Date(year, mm - 1, dd);

    if (
        birth.getFullYear() !== year
        || birth.getMonth() !== mm - 1
        || birth.getDate() !== dd
    ) {
        return null;
    }

    const age = asOfYear - year;

    if (age < 0 || age > 120) {
        return null;
    }

    return {
        register,
        birth: `${year}-${String(mm).padStart(2, '0')}-${String(dd).padStart(2, '0')}`,
        age,
        gender: genderDigit % 2 === 1 ? 'эр' : 'эм',
    };
};
