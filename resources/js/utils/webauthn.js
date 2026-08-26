/**
 * WebAuthn (хуруу / нүүр / Windows Hello) туслах функцууд.
 */

export const isWebAuthnSupported = () => (
    typeof window !== 'undefined'
    && !! window.PublicKeyCredential
    && typeof navigator.credentials?.create === 'function'
    && typeof navigator.credentials?.get === 'function'
    && window.isSecureContext
);

const b64urlToBuffer = (value) => {
    const str = String(value ?? '').replace(/-/g, '+').replace(/_/g, '/');
    const pad = '='.repeat((4 - (str.length % 4)) % 4);
    const binary = atob(str + pad);
    const bytes = new Uint8Array(binary.length);
    for (let i = 0; i < binary.length; i += 1) {
        bytes[i] = binary.charCodeAt(i);
    }

    return bytes.buffer;
};

const bufferToB64url = (buffer) => {
    const bytes = new Uint8Array(buffer);
    let binary = '';
    bytes.forEach((b) => {
        binary += String.fromCharCode(b);
    });

    return btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/g, '');
};

/** Серверийн publicKey options → браузерын PublicKeyCredentialCreationOptions */
export const preparePublicKeyCreate = (publicKey) => {
    const pk = { ...publicKey };
    pk.challenge = b64urlToBuffer(pk.challenge);
    pk.user = {
        ...pk.user,
        id: b64urlToBuffer(pk.user.id),
    };
    if (Array.isArray(pk.excludeCredentials)) {
        pk.excludeCredentials = pk.excludeCredentials.map((c) => ({
            ...c,
            id: b64urlToBuffer(c.id),
        }));
    }

    return pk;
};

/** Серверийн publicKey options → браузерын PublicKeyCredentialRequestOptions */
export const preparePublicKeyGet = (publicKey) => {
    const pk = { ...publicKey };
    pk.challenge = b64urlToBuffer(pk.challenge);
    if (Array.isArray(pk.allowCredentials) && pk.allowCredentials.length) {
        pk.allowCredentials = pk.allowCredentials.map((c) => ({
            ...c,
            id: b64urlToBuffer(c.id),
        }));
    } else {
        delete pk.allowCredentials;
    }

    return pk;
};

export const credentialToCreatePayload = (credential) => {
    const response = credential.response;

    return {
        id: credential.id,
        rawId: bufferToB64url(credential.rawId),
        type: credential.type,
        clientDataJSON: bufferToB64url(response.clientDataJSON),
        attestationObject: bufferToB64url(response.attestationObject),
    };
};

export const credentialToAssertPayload = (credential) => {
    const response = credential.response;

    return {
        id: bufferToB64url(credential.rawId),
        rawId: bufferToB64url(credential.rawId),
        type: credential.type,
        clientDataJSON: bufferToB64url(response.clientDataJSON),
        authenticatorData: bufferToB64url(response.authenticatorData),
        signature: bufferToB64url(response.signature),
        userHandle: response.userHandle ? bufferToB64url(response.userHandle) : null,
    };
};

export const registerBiometric = async () => {
    const { data: options } = await window.axios.post(route('webauthn.register.options'));
    const publicKey = preparePublicKeyCreate(options.publicKey);
    const credential = await navigator.credentials.create({ publicKey });
    if (! credential) {
        throw new Error('Бүртгэл цуцлагдлаа.');
    }

    const payload = credentialToCreatePayload(credential);
    const { data } = await window.axios.post(route('webauthn.register'), payload);

    return data;
};

export const loginWithBiometric = async () => {
    const { data: options } = await window.axios.post(route('webauthn.login.options'));
    const publicKey = preparePublicKeyGet(options.publicKey);
    const credential = await navigator.credentials.get({ publicKey });
    if (! credential) {
        throw new Error('Нэвтрэлт цуцлагдлаа.');
    }

    const payload = credentialToAssertPayload(credential);
    const { data } = await window.axios.post(route('webauthn.login'), payload);

    return data;
};

/** Нэвтэрсэн хэрэглэгчийн биометрикийг баталгаажуулж assertion payload буцаана. */
export const assertBiometric = async () => {
    const { data: options } = await window.axios.post(route('webauthn.verify.options'));
    const publicKey = preparePublicKeyGet(options.publicKey);
    const credential = await navigator.credentials.get({ publicKey });
    if (! credential) {
        throw new Error('Баталгаажуулалт цуцлагдлаа.');
    }

    return credentialToAssertPayload(credential);
};
