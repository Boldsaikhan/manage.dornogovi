<script setup>
import { computed, watch } from 'vue';
import InputError from '@/Components/InputError.vue';

/**
 * «ТОМИЛОЛТЫН УДИРДАМЖ» — A4 цаасан дээр хэвлэгдэх байдлаараа бөглөх маягт.
 *
 * Бөглөх талбарууд нь хэвлэгдэх хуудасны яг тэр байрлалд байна: толгойн
 * «БАТЛАВ» хэсэг, дугаарласан 1–4 зүйл, төсвийн хүснэгт, тайлангийн хэсэг.
 */
const props = defineProps({
    form: { type: Object, required: true },
    meta: { type: Object, default: () => ({}) },
});

const lines = () => props.meta.lines ?? [];

/*
 * Албан тушаалын мөр олон бол (ЗДТГ-ын дарга) сүүлийн мөрийн хажууд нэр
 * нь орж, голд нь гарын үсгийн зай үлдэнэ. Нэг мөр бол (Засаг дарга) нэр
 * нь доороо байрлана — цаасан маягт хоёулаа ийм байдаг.
 */
const sideBySide = () => lines().length > 1;
const headLines = () => (sideBySide() ? lines().slice(0, -1) : lines());
const lastLine = () => lines()[lines().length - 1] ?? '';
const budgetKinds = () => props.meta.budget_kinds ?? [];

// Батлах эрхтэй удирдах албан хаагчид — утасны жагсаалтаас.
const leaders = () => Object.entries(props.meta.leaders ?? {});

// Сонгосон нэр байвал толгойд түүнийг харуулна.
const signerName = () => props.form.approved_by || props.meta.signer || '.....................';

const dotted = 'w-full resize-y border-0 border-b border-dotted border-black bg-transparent p-0 '
    + 'font-serif text-[13px] leading-relaxed focus:border-brand-navy-600 focus:ring-0';

/* ── Томилолт авч буй албан хаагч ─────────────────────────────────── */

const people = () => props.meta.people ?? [];

/**
 * Албан хаагч сонгоход нэр, албан тушаалыг нь бөглөөд үнэмлэхийн
 * бичвэрийг зурган дээрх загвараар бүрдүүлнэ.
 */
const pickPerson = (name) => {
    const person = people().find((p) => p.name === name);

    props.form.person_name = name;
    props.form.position = person?.position || '';

    refreshCertificateText();
};

/**
 * Бичвэрийг дахин бүрдүүлнэ.
 *
 * Гараар засварласан бичвэрийг дарж бичихгүй — өөрсдийн үүсгэсэн
 * хэвээр байгаа үед л шинэчилнэ.
 */
let generated = '';

const refreshCertificateText = () => {
    const current = String(props.form.certificate_text ?? '').trim();

    if (current !== '' && current !== generated.trim()) {
        return;
    }

    const person = people().find((p) => p.name === props.form.person_name);

    generated = buildCertificateText(person);
    props.form.certificate_text = generated;
};

/** Хоногийг өөрчлөхөд дуусах огноо нь дагаж тохирно. */
const days = computed({
    get: () => {
        const value = dayCount();

        return value === '…' ? '' : value;
    },
    set: (value) => {
        const count = Math.max(1, Number(value) || 1);

        if (! props.form.start_date) {
            return;
        }

        const end = new Date(props.form.start_date);
        end.setDate(end.getDate() + count - 1);

        props.form.end_date = end.toISOString().slice(0, 10);
    },
});

// Огноо, хоног, очих газар өөрчлөгдөхөд бичвэр дагаж шинэчлэгдэнэ.
watch(
    () => [props.form.start_date, props.form.end_date, props.form.destination],
    () => refreshCertificateText(),
);

const mn = (value) => {
    if (! value) return '…';

    const [, month, day] = String(value).split('-');

    return `${Number(month)} дугаар сарын ${Number(day)}`;
};

const buildCertificateText = (person) => {
    if (! person) return '';

    const org = person.org ? person.org : 'Дорноговь аймгийн ЗДТГ';
    const position = person.position || 'албан хаагч';
    const where = props.form.destination || '…';

    return `${org}-ын ${position} ${person.name} ${where} ${mn(props.form.start_date)}-ны `
        + `өдрөөс ${dayCount()} хоног ажиллуулахаар томилов.`;
};

/** Эхлэх, дуусах огнооноос хоногийг бодно. */
const dayCount = () => {
    const start = props.form.start_date;
    const end = props.form.end_date;

    if (! start || ! end) return '…';

    const diff = Math.round((new Date(end) - new Date(start)) / 86400000) + 1;

    return diff > 0 ? diff : '…';
};

// «БАТЛАВ» доторх сонголт — хэвлэгдэх нэртэй ижил харагдана.
const pickerClass = 'w-auto border-0 border-b border-dotted border-black bg-transparent p-0 pr-5 '
    + 'font-serif text-[12px] font-bold uppercase focus:border-brand-navy-600 focus:ring-0';
</script>

<template>
    <!-- Хэвлэхтэй ижил: ар тал, урд тал зэрэгцэж харагдана. -->
    <div class="grid w-full gap-5 xl:grid-cols-2">

    <!-- ══ АР ТАЛ — Албан томилолтын үнэмлэх ══ -->
    <div class="rounded-lg border border-slate-200 bg-white p-6 font-serif text-[13px] leading-relaxed text-black shadow-sm sm:p-8">
        <p class="mb-4 text-center font-sans text-[11px] font-semibold uppercase tracking-wide text-slate-400">
            Ар тал — албан томилолтын үнэмлэх
        </p>

        <div class="grid gap-8 sm:grid-cols-2">
            <div>
                <div class="mt-10 text-center text-[15px] font-bold uppercase leading-tight">
                    Албан томилолтын<br />үнэмлэх
                </div>
                <div class="mt-10 text-center text-[12px] font-bold">{{ meta.year }} он</div>

                <p class="mt-10 text-[12px]">Томилолтоор ажилласан тухай тэмдэглэл</p>

                <table class="mt-2 w-full border-collapse text-center text-[11px]">
                    <thead>
                        <tr>
                            <th rowspan="2" class="border border-black p-1">Хаана</th>
                            <th colspan="2" class="border border-black p-1">Сар, өдөр</th>
                            <th rowspan="2" class="border border-black p-1">Гарын үсэг</th>
                        </tr>
                        <tr>
                            <th class="border border-black p-1">Ирсэн</th>
                            <th class="border border-black p-1">Буцсан</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="i in 6" :key="'mark-' + i">
                            <td class="h-7 border border-black" />
                            <td class="border border-black" />
                            <td class="border border-black" />
                            <td class="border border-black" />
                        </tr>
                    </tbody>
                </table>

                <p class="mt-4 text-[12px]">
                    Албан томилолтын ажлын дүнг эх зардлыг ………хувиар тооцоо хийхийг зөвшөөрсөн.
                </p>

                <div class="mt-6 w-fit text-[12px] font-bold uppercase leading-snug">
                    <template v-for="(line, i) in headLines()" :key="'c1-' + i">{{ line }}<br /></template>
                    <div v-if="sideBySide()" class="flex justify-between gap-6">
                        <span class="whitespace-nowrap">{{ lastLine() }}</span>
                        <span class="whitespace-nowrap">{{ signerName() }}</span>
                    </div>
                    <div v-else class="mt-3 whitespace-nowrap text-right">{{ signerName() }}</div>
                </div>

                <p class="mt-6 text-center text-[12px]">………. оны …… сар …… өдөр</p>
            </div>

            <div>
                <!-- Дугаар нь бүртгэлийн Д/д — хэвлэхэд автоматаар тавигдана. -->
                <p class="text-center text-[12px] font-bold">
                    Дугаар {{ meta.number || '—' }}
                </p>

                <label class="mt-4 block font-sans text-[10px] font-semibold uppercase tracking-wide text-slate-400">
                    Албан хаагч
                </label>
                <select
                    v-if="people().length"
                    :value="form.person_name"
                    class="ui-input !py-1.5 font-sans !text-xs"
                    @change="pickPerson($event.target.value)"
                >
                    <option value="">— утасны жагсаалтаас сонгох —</option>
                    <option v-for="p in people()" :key="p.name + p.position" :value="p.name">
                        {{ p.name }}{{ p.position ? ' · ' + p.position : '' }}
                    </option>
                </select>
                <InputError :message="form.errors.person_name" class="font-sans" />

                <div class="mt-3 grid grid-cols-2 gap-2">
                    <div>
                        <label class="block font-sans text-[10px] font-semibold uppercase tracking-wide text-slate-400">
                            Эхлэх огноо
                        </label>
                        <input
                            v-model="form.start_date"
                            type="date"
                            class="ui-input !py-1.5 font-sans !text-xs"
                        />
                    </div>
                    <div>
                        <label class="block font-sans text-[10px] font-semibold uppercase tracking-wide text-slate-400">
                            Хэд хоног
                        </label>
                        <input
                            v-model="days"
                            type="number"
                            min="1"
                            max="365"
                            class="ui-input !py-1.5 font-sans !text-xs"
                        />
                    </div>
                </div>
                <InputError :message="form.errors.start_date" class="font-sans" />

                <label class="mt-3 block font-sans text-[10px] font-semibold uppercase tracking-wide text-slate-400">
                    Үнэмлэхийн бичвэр
                </label>
                <textarea
                    v-model="form.certificate_text"
                    rows="7"
                    :class="dotted"
                    placeholder="Дорноговь аймгийн ЗДТГ-ын … албан хаагч … сарын …-ны өдрөөс … хоног ажиллуулахаар томилов."
                />
                <InputError :message="form.errors.certificate_text" />

                <div class="mt-6 w-fit text-[12px] font-bold uppercase leading-snug">
                    <template v-for="(line, i) in headLines()" :key="'c2-' + i">{{ line }}<br /></template>
                    <div v-if="sideBySide()" class="flex justify-between gap-6">
                        <span class="whitespace-nowrap">{{ lastLine() }}</span>
                        <span class="whitespace-nowrap">{{ signerName() }}</span>
                    </div>
                    <div v-else class="mt-3 whitespace-nowrap text-right">{{ signerName() }}</div>
                </div>

                <p class="mt-6 text-center text-[12px]">………. оны …… сар …… өдөр</p>

                <p class="mt-10 text-center text-[12px] font-bold uppercase">Тусгай тэмдэглэл</p>
                <p class="mt-3 text-center text-[12px]">Хугацаа сунгах тухай</p>

                <p class="mt-4 text-[12px] leading-loose">
                    ……………………………………… газар, байгууллагын тодорхойлолт, хүсэлт
                    ……………………………………… ажил гүйцэтгэх шаардлагын дагуу томилолтын
                    хугацааг ……. хоногоор сунгав.
                </p>

                <p class="mt-6 text-[12px]">Зөвшөөрсөн дарга &nbsp;..................................</p>
            </div>
        </div>
    </div>

    <!-- ══ УРД ТАЛ — Томилолтын удирдамж ══ -->
    <div class="rounded-lg border border-slate-200 bg-white p-6 font-serif text-[13px] leading-relaxed text-black shadow-sm sm:p-8">
        <p class="mb-4 text-center font-sans text-[11px] font-semibold uppercase tracking-wide text-slate-400">
            Урд тал — томилолтын удирдамж
        </p>
        <!-- БАТЛАВ -->
        <!-- Өргөн нь хамгийн урт мөрөөрөө — нэр нь тэр мөрийн ирмэгтэй тэнцэнэ. -->
        <div class="ml-auto w-fit text-[12px] font-bold uppercase leading-snug">
            <!-- «БАТЛАВ» нь доорх бичвэрийнхээ голд байрлана. -->
            <span class="block text-center">БАТЛАВ</span>
            <template v-for="(line, i) in headLines()" :key="i">
                <span class="whitespace-nowrap">{{ line }}</span><br />
            </template>
            <div v-if="sideBySide()" class="flex items-end justify-between gap-10">
                <span class="whitespace-nowrap">{{ lastLine() }}</span>
                <select v-if="leaders().length" v-model="form.approved_by" :class="pickerClass">
                    <option value="">— сонгох —</option>
                    <option v-for="[name] in leaders()" :key="name" :value="name">{{ name }}</option>
                </select>
                <span v-else class="whitespace-nowrap">{{ signerName() }}</span>
            </div>
            <div v-else class="mt-3 text-right">
                <select v-if="leaders().length" v-model="form.approved_by" :class="pickerClass">
                    <option value="">— сонгох —</option>
                    <option v-for="[name] in leaders()" :key="name" :value="name">{{ name }}</option>
                </select>
                <span v-else class="whitespace-nowrap">{{ signerName() }}</span>
            </div>
            <InputError :message="form.errors.approved_by" class="font-sans normal-case" />
        </div>

        <h3 class="mt-8 text-center text-[13px] font-bold uppercase">Томилолтын удирдамж</h3>

        <div class="mt-3 text-right text-[12px] font-bold">{{ meta.year }} он</div>

        <!-- 1. Зорилго -->
        <div class="mt-5 flex gap-2">
            <span class="w-5 shrink-0 font-bold italic">1.</span>
            <div class="flex-1">
                <span class="font-bold italic underline">Зорилго:</span>
                <textarea
                    v-model="form.purpose"
                    rows="2"
                    :class="['mt-1', dotted]"
                    placeholder="Томилолтын зорилгоо бичнэ үү"
                />
                <InputError :message="form.errors.purpose" />
            </div>
        </div>

        <!-- 2. Бүрэлдэхүүн -->
        <div class="mt-4 flex gap-2">
            <span class="w-5 shrink-0 font-bold italic">2.</span>
            <div class="flex-1">
                <span class="font-bold italic underline">Бүрэлдэхүүн:</span>
                <textarea
                    v-model="form.composition"
                    rows="2"
                    :class="['mt-1', dotted]"
                    placeholder="Томилолтоор ажиллах албан хаагчид"
                />
                <InputError :message="form.errors.composition" />
            </div>
        </div>

        <!-- 3. Хугацаа -->
        <div class="mt-4 flex gap-2">
            <span class="w-5 shrink-0 font-bold italic">3.</span>
            <div class="flex-1">
                <span class="font-bold italic underline">Хугацаа:</span>
                <div class="mt-1 flex flex-wrap items-center gap-2">
                    <input
                        v-model="form.start_date"
                        type="date"
                        required
                        class="border-0 border-b border-dotted border-black bg-transparent p-0 font-serif text-[13px] focus:border-brand-navy-600 focus:ring-0"
                    />
                    <span>—</span>
                    <input
                        v-model="form.end_date"
                        type="date"
                        required
                        class="border-0 border-b border-dotted border-black bg-transparent p-0 font-serif text-[13px] focus:border-brand-navy-600 focus:ring-0"
                    />
                    <span class="text-slate-400">·</span>
                    <input
                        v-model="form.destination"
                        type="text"
                        required
                        placeholder="очих газар"
                        class="min-w-[40mm] flex-1 border-0 border-b border-dotted border-black bg-transparent p-0 font-serif text-[13px] focus:border-brand-navy-600 focus:ring-0"
                    />
                </div>
                <InputError :message="form.errors.start_date || form.errors.end_date || form.errors.destination" />
            </div>
        </div>

        <!-- 4. Томилолтын хүрээнд -->
        <div class="mt-4 flex gap-2">
            <span class="w-5 shrink-0 font-bold italic">4.</span>
            <div class="flex-1">
                <span class="font-bold italic underline">Томилолтын хүрээнд: /ажлын чиглэл/</span>
                <textarea
                    v-model="form.scope_of_work"
                    rows="4"
                    :class="['mt-1', dotted]"
                    placeholder="Хийж гүйцэтгэх ажлын чиглэл"
                />
                <InputError :message="form.errors.scope_of_work" />
            </div>
        </div>

        <!-- Төсөв -->
        <div class="mt-6 text-[12px]">Албан томилолтоор ажиллах төсөв</div>

        <table class="mt-1 w-full border-collapse text-[12px]">
            <thead>
                <tr>
                    <th class="w-[8%] border border-black p-1 text-center font-bold">№</th>
                    <th class="w-[24%] border border-black p-1 text-center font-bold">Зардлын төрөл</th>
                    <th class="border border-black p-1 text-center font-bold">Нэгжийн үнэ</th>
                    <th class="border border-black p-1 text-center font-bold">Тоо хэмжээ</th>
                    <th class="border border-black p-1 text-center font-bold">Ажиллах хоног</th>
                    <th class="border border-black p-1 text-center font-bold">Нийт дүн</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="(kind, i) in budgetKinds()" :key="kind" class="h-8">
                    <td class="border border-black p-1 text-center">{{ i + 1 }}</td>
                    <td class="border border-black p-1 text-center">{{ kind }}</td>
                    <td class="border border-black p-1" />
                    <td class="border border-black p-1" />
                    <td class="border border-black p-1" />
                    <td class="border border-black p-1" />
                </tr>
            </tbody>
        </table>
        <p class="mt-1 font-sans text-[11px] text-slate-500">Төсвийн хүснэгтийг хэвлэсний дараа гараар бөглөнө.</p>

        <!-- Тайлан -->
        <div class="mt-5 text-[12px] font-bold uppercase">Томилолтын тайлан:</div>
        <textarea
            v-model="form.report"
            rows="6"
            :class="['mt-1', dotted]"
            placeholder="Хоосон орхивол хэвлэхэд гараар бөглөх мөрүүд гарна"
        />

        <div class="mt-6 space-y-2 text-[12px]">
            <div>Тайлан гаргасан ................................................/&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;/</div>
            <div>Танилцсан................................................/&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;/</div>
        </div>

        <!-- Маягт дээр хэвлэгдэхгүй бүртгэлийн мэдээлэл -->
        <div class="mt-8 rounded-xl border border-dashed border-slate-300 bg-slate-50 p-4 font-sans">
            <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-slate-500">
                Бүртгэлийн мэдээлэл — маягт дээр хэвлэгдэхгүй
            </p>
            <div class="grid gap-3 sm:grid-cols-3">
                <div>
                    <label class="ui-label">Тушаалын дугаар</label>
                    <input v-model="form.order_number" type="text" class="ui-input" />
                    <InputError :message="form.errors.order_number" />
                </div>
                <div>
                    <label class="ui-label">Төлөв</label>
                    <select v-model="form.status" class="ui-input">
                        <option value="pending">Хүлээгдэж буй</option>
                        <option value="approved">Зөвшөөрсөн</option>
                        <option value="done">Дууссан</option>
                    </select>
                </div>
                <div>
                    <label class="ui-label">Тэмдэглэл</label>
                    <input v-model="form.note" type="text" class="ui-input" />
                </div>
            </div>
        </div>
    </div>

    </div>
</template>
