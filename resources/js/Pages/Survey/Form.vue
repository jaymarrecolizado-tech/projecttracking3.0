<script setup>
import PublicSurveyLayout from '@/Layouts/PublicSurveyLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';

const props = defineProps({
    site: Object,
    survey: Object,
    submitUrl: String,
    startedAt: Number,
});

const startedAt = ref(props.startedAt ?? Date.now());
const shownAt = ref(Date.now());

const ratingQuestions = computed(() => (props.survey.questions ?? []).filter((q) => q.type === 'rating'));
const textQuestions = computed(() => (props.survey.questions ?? []).filter((q) => q.type === 'text'));

// Counted from the question set, never hardcoded: the copy used to say "Four"
// while the form rendered whatever the seeder defined, so a v2 question set
// would have lied to the public on its first screen.
const intro = computed(() => {
    const count = ratingQuestions.value.length + textQuestions.value.length;
    return `${count} quick question${count === 1 ? '' : 's'} about the connection you are using right now. It takes under a minute.`;
});

const form = useForm({
    ratings: Object.fromEntries(ratingQuestions.value.map((q) => [q.key, null])),
    comments: '',
    // Honeypot — hidden from humans, irresistible to bots.
    website: '',
    elapsed_ms: 0,
});

onMounted(() => {
    shownAt.value = Date.now();
});

const ratingLabels = { 1: 'Very poor', 2: 'Poor', 3: 'Okay', 4: 'Good', 5: 'Very good' };

function submit() {
    form.elapsed_ms = Date.now() - startedAt.value;
    form.post(props.submitUrl);
}
</script>

<template>
  <Head :title="`${survey.title} — ${site.name}`" />

  <PublicSurveyLayout :site-name="site.name">
    <div class="rounded-xl border border-slate-200 bg-white p-5 sm:p-6">
      <h1 class="text-[17px] font-bold text-slate-900 leading-snug">{{ survey.title }}</h1>
      <p class="mt-1.5 text-[13px] text-slate-500 leading-relaxed">
        {{ intro }}
      </p>

      <form class="mt-6 space-y-7" @submit.prevent="submit">
        <!-- Honeypot: visually and semantically hidden -->
        <div class="hidden" aria-hidden="true">
          <label for="website">Website</label>
          <input id="website" v-model="form.website" type="text" tabindex="-1" autocomplete="off" />
        </div>

        <fieldset v-for="q in ratingQuestions" :key="q.key" class="space-y-2.5">
          <legend class="text-[13px] font-medium text-slate-800 leading-snug">
            {{ q.label }}
          </legend>
          <div class="grid grid-cols-5 gap-1.5">
            <button
              v-for="n in 5" :key="n" type="button"
              class="flex flex-col items-center justify-center rounded-lg border py-3 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500/50"
              :class="form.ratings[q.key] === n
                ? 'border-accent-600 bg-accent-50 text-accent-800'
                : 'border-slate-300 bg-white text-slate-600 hover:border-slate-400'"
              :aria-pressed="form.ratings[q.key] === n"
              :aria-label="`${n} of 5 — ${ratingLabels[n]}`"
              @click="form.ratings[q.key] = n"
            >
              <span class="text-base font-bold tabular-nums">{{ n }}</span>
              <span class="mt-0.5 text-[9px] leading-none text-center px-0.5">{{ ratingLabels[n] }}</span>
            </button>
          </div>
          <p v-if="form.errors[`ratings.${q.key}`]" class="text-[12px] text-red-600" role="alert">
            Please choose a rating.
          </p>
        </fieldset>

        <!-- ponytail: every text question binds to the single `comments` field,
             which is what the write path stores. A second text question would
             overwrite the first — bind by q.key and fold into the column in
             SiteSurveyService when the question set actually grows one. -->
        <div v-for="q in textQuestions" :key="q.key" class="space-y-2">
          <label :for="`q-${q.key}`" class="block text-[13px] font-medium text-slate-800 leading-snug">
            {{ q.label }}
          </label>
          <textarea
            :id="`q-${q.key}`" v-model="form.comments" rows="3" maxlength="2000"
            class="w-full rounded-lg border-slate-300 text-[13px] focus:border-accent-500 focus:ring-accent-500/40"
            placeholder="Optional"
          ></textarea>
        </div>

        <p v-if="form.errors.ratings" class="text-[12px] text-red-600" role="alert">
          {{ form.errors.ratings }}
        </p>
        <p v-if="form.errors.website" class="text-[12px] text-red-600" role="alert">
          Submission was rejected. Please try again.
        </p>

        <button
          type="submit" :disabled="form.processing"
          class="w-full rounded-lg bg-accent-600 px-4 py-3.5 text-[14px] font-semibold text-white transition-colors hover:bg-accent-700 disabled:opacity-60 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500/50"
        >
          {{ form.processing ? 'Sending…' : 'Submit feedback' }}
        </button>
      </form>
    </div>
  </PublicSurveyLayout>
</template>
