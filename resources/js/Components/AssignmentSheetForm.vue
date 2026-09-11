<script setup>
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
const budgetKinds = () => props.meta.budget_kinds ?? [];

// Батлах эрхтэй удирдах албан хаагчид — утасны жагсаалтаас.
const leaders = () => Object.entries(props.meta.leaders ?? {});

// Сонгосон нэр байвал толгойд түүнийг харуулна.
const signerName = () => props.form.approved_by || props.meta.signer || '.....................';

const dotted = 'w-full resize-y border-0 border-b border-dotted border-black bg-transparent p-0 '
    + 'font-serif text-[13px] leading-relaxed focus:border-brand-navy-600 focus:ring-0';
</script>

<template>
    <div class="mx-auto w-full max-w-[190mm] space-y-6">

    <!-- ══ АР ТАЛ — Албан томилолтын үнэмлэх ══ -->
    <div class="bg-white p-6 font-serif text-[13px] leading-relaxed text-black sm:p-10">
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

                <div class="mt-6 text-[12px] font-bold uppercase leading-snug">
                    <template v-for="(line, i) in lines()" :key="'c1-' + i">{{ line }}<br /></template>
                    <span class="ml-8 inline-block">{{ signerName() }}</span>
                </div>

                <p class="mt-6 text-center text-[12px]">………. оны …… сар …… өдөр</p>
            </div>

            <div>
                <!-- Дугаар нь бүртгэлийн Д/д — хэвлэхэд автоматаар тавигдана. -->
                <p class="text-center text-[12px] font-bold">
                    Дугаар {{ meta.number || '—' }}
                </p>

                <label class="mt-4 block font-sans text-[10px] font-semibold uppercase tracking-wide text-slate-400">
                    Үнэмлэхийн бичвэр
                </label>
                <textarea
                    v-model="form.certificate_text"
                    rows="7"
                    :class="dotted"
                    placeholder="Дорноговь аймгийн ЗДТГ-ын … албан хаагч … сарын …-ны өдрөөс … хоног ажиллуулахаар томилов."
                />
                <InputError :message="form.errors.certificate_text" />

                <div class="mt-6 text-[12px] font-bold uppercase leading-snug">
                    <template v-for="(line, i) in lines()" :key="'c2-' + i">{{ line }}<br /></template>
                    <span class="ml-8 inline-block">{{ signerName() }}</span>
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
    <div class="bg-white p-6 font-serif text-[13px] leading-relaxed text-black sm:p-10">
        <p class="mb-4 text-center font-sans text-[11px] font-semibold uppercase tracking-wide text-slate-400">
            Урд тал — томилолтын удирдамж
        </p>
        <!-- БАТЛАВ -->
        <div class="ml-auto w-3/5 text-[12px] font-bold uppercase leading-snug">
            БАТЛАВ<br />
            <template v-for="(line, i) in lines()" :key="i">{{ line }}<br /></template>
            <span class="mt-1 inline-block">{{ signerName() }}</span>
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
                <div v-if="leaders().length">
                    <label class="ui-label">Баталсан</label>
                    <select v-model="form.approved_by" class="ui-input">
                        <option value="">— сонгох —</option>
                        <option v-for="[name, label] in leaders()" :key="name" :value="name">
                            {{ label }}
                        </option>
                    </select>
                    <InputError :message="form.errors.approved_by" />
                </div>
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
