<!DOCTYPE html>
<html lang="mn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="referrer" content="no-referrer">
    <title>{{ $system->name }} — нэвтэрч байна</title>
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        body {
            margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center;
            background: #f4f6f9; color: #2a3445; padding: 1.5rem;
            font-family: Figtree, ui-sans-serif, system-ui, "Segoe UI", Arial, sans-serif;
        }
        .card {
            width: 100%; max-width: 26rem; background: #fff; border: 1px solid #e2e7ef;
            border-radius: .75rem; padding: 1.5rem; box-shadow: 0 1px 2px rgba(0,0,0,.05);
        }
        h1 { margin: 0 0 .25rem; font-size: 1rem; color: #11161f; }
        p { margin: 0 0 1rem; font-size: .875rem; color: #6c7382; }
        label { display: block; font-size: .75rem; font-weight: 500; margin-bottom: .25rem; }
        .row { display: flex; gap: .5rem; margin-bottom: .75rem; }
        input[readonly] {
            flex: 1; min-width: 0; padding: .5rem .75rem; font-size: .875rem;
            border: 1px solid #c5cdd8; border-radius: .375rem; background: #f4f6f9; color: #2a3445;
        }
        button, a.btn {
            padding: .5rem .875rem; font-size: .8125rem; font-weight: 500; border-radius: .375rem;
            border: 1px solid #c5cdd8; background: #fff; color: #2a3445; cursor: pointer;
            text-decoration: none; display: inline-block; white-space: nowrap;
        }
        button:hover, a.btn:hover { background: #f4f6f9; }
        .primary { background: #f0941d; border-color: #f0941d; color: #fff; }
        .primary:hover { background: #d97d0a; }
        .actions { display: flex; gap: .5rem; margin-top: 1.25rem; }
        .hint { font-size: .75rem; color: #9aa3b2; margin-top: 1rem; }
    </style>
</head>
<body>

@if ($autoSubmit)
    {{-- Тохиргоо бүрэн: нуугдмал маягтаар шууд илгээж нэвтэрнэ. --}}
    <form id="login" method="POST" action="{{ $system->login_form_action }}">
        <input type="hidden" name="{{ $system->login_username_field }}" value="{{ $username }}">
        <input type="hidden" name="{{ $system->login_password_field }}" value="{{ $password }}">
        @foreach (($system->login_extra_fields ?? []) as $name => $value)
            <input type="hidden" name="{{ $name }}" value="{{ $value }}">
        @endforeach
    </form>

    <div class="card">
        <h1>{{ $system->name }}</h1>
        <p>Нэвтэрч байна…</p>
        <div class="actions">
            <button type="button" class="primary" onclick="document.getElementById('login').submit()">
                Гараар илгээх
            </button>
        </div>
    </div>

    <script>document.getElementById('login').submit();</script>
@else
    {{-- Тохиргоогүй: нууц үгийг хуулаад системийн нэвтрэх хуудас руу шууд шилжинэ. --}}
    <div class="card">
        <h1>{{ $system->name }}@if ($authType === 'dan') <small style="font-weight:500;color:#6c7382">— ДАН</small>@endif</h1>
        <p id="status">Нууц үгийг санах ойд хууллаа. Нэвтрэх хуудас руу шилжиж байна…</p>

        <label for="u">{{ $authType === 'dan' ? 'Регистрийн дугаар' : 'Нэвтрэх нэр' }}</label>
        <div class="row">
            <input id="u" type="text" value="{{ $username }}" readonly>
            <button type="button" onclick="copy('u', this)">Хуулах</button>
        </div>

        <label for="p">{{ $authType === 'dan' ? 'ДАН нууц үг' : 'Нууц үг' }}</label>
        <div class="row">
            <input id="p" type="password" value="{{ $password }}" readonly>
            <button type="button" onclick="toggle(this)">Харах</button>
            <button type="button" onclick="copy('p', this)">Хуулах</button>
        </div>

        <div class="actions">
            <a class="btn primary" id="go" href="{{ $entryUrl }}">Одоо шилжих</a>
            <button type="button" id="stay">Энд байх</button>
        </div>

        <p class="hint" id="hint">
            Нэвтрэх хуудсанд нууц үгийн талбар дээр Ctrl+V дарна.
            <br>
            <a href="{{ url('/dept-dashboard') }}">Автомат нэвтрэлтийн өргөтгөл татах</a> —
            нүүр дээрх «Өргөтгөл татах»-аар задгай файлаар суулгана.
        </p>
    </div>

    <script>
        function copy(id, btn) {
            const el = document.getElementById(id);
            navigator.clipboard.writeText(el.value).then(() => {
                const old = btn.textContent;
                btn.textContent = 'Хуулагдлаа';
                setTimeout(() => (btn.textContent = old), 1500);
            });
        }

        function toggle(btn) {
            const el = document.getElementById('p');
            const shown = el.type === 'text';
            el.type = shown ? 'password' : 'text';
            btn.textContent = shown ? 'Харах' : 'Нуух';
        }

        const target = document.getElementById('go').href;
        const status = document.getElementById('status');
        const stay = document.getElementById('stay');
        const hasExtension = document.documentElement.dataset.mdExtension === '1';

        let timer = null;

        const go = () => location.replace(target);

        // Нөөц зам (хуучин өргөтгөлтэй нийцүүлэх): хуудсаар дамжуулна.
        const postMessageFallback = (payload) => {
            window.addEventListener('message', (event) => {
                if (event.origin === window.location.origin && event.data?.type === 'md-autologin-ready') {
                    go();
                }
            });

            window.postMessage(
                {
                    type: 'md-autologin',
                    host: payload.host,
                    mode: payload.mode,
                    remember: payload.remember,
                    username: payload.username,
                    password: payload.password,
                },
                window.location.origin,
            );
        };

        stay.addEventListener('click', () => {
            clearTimeout(timer);
            status.textContent = 'Шилжихийг зогсоолоо. Нэр, нууц үгээ хуулж аваад "Одоо шилжих" дарна уу.';
            stay.disabled = true;
        });

        if (hasExtension) {
            // Өргөтгөл суусан: мэдээллийг түүнд шилжүүлээд, тэр нь маягтыг өөрөө бөглөнө.
            status.textContent = 'Нэвтэрч байна…';
            document.getElementById('hint').style.display = 'none';

            const payload = {
                type: 'store',
                host: new URL(target).hostname,
                mode: @json($authType),
                remember: @json($rememberDevice),
                username: document.getElementById('u').value,
                password: document.getElementById('p').value,
            };

            const extensionId = document.documentElement.dataset.mdExtensionId;

            // 1) Аюулгүй зам: мэдээллийг шууд өргөтгөл рүү илгээнэ.
            //    Хуудсан дээрх бусад скрипт, өргөтгөл харах боломжгүй.
            if (extensionId && window.chrome?.runtime?.sendMessage) {
                try {
                    chrome.runtime.sendMessage(extensionId, payload, () => go());
                } catch (e) {
                    postMessageFallback(payload);
                }
            } else {
                postMessageFallback(payload);
            }

            // Өргөтгөл хариу өгөхгүй бол ч гацахгүй.
            timer = setTimeout(go, 2000);
        } else {
            // Өргөтгөлгүй: нууц үгийг хуулаад нэвтрэх хуудас руу шилжинэ.
            timer = setTimeout(go, 1200);

            navigator.clipboard.writeText(document.getElementById('p').value).catch(() => {
                clearTimeout(timer);
                status.textContent = 'Нууц үгийг автоматаар хуулж чадсангүй. "Хуулах" товчийг дарна уу.';
            });
        }
    </script>
@endif

</body>
</html>
