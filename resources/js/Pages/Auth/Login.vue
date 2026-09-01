<script setup>
import Checkbox from '@/Components/Checkbox.vue';
import InputError from '@/Components/InputError.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    canResetPassword: {
        type: Boolean,
    },
    status: {
        type: String,
    },
    network: {
        type: Object,
        default: () => ({ sites: 0, active: 0, provinces: 0, upToday: 0 }),
    },
    recent: {
        type: Array,
        default: () => [],
    },
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};

const dotFor = {
    UP: 'bg-emerald-400',
    DOWN: 'bg-red-400',
    DOWN_SERVER: 'bg-red-400',
    NO_NMS: 'bg-amber-400',
    NO_DATA: 'bg-slate-500',
};
</script>

<template>
    <Head title="Sign in" />

    <div class="min-h-screen w-full lg:grid lg:grid-cols-2">
        <!-- ═══ Identity panel ═══ -->
        <div class="relative hidden lg:flex min-h-screen flex-col justify-between overflow-hidden bg-[#0F1B2D] text-white p-12">
            <!-- Blueprint grid -->
            <div class="absolute inset-0 pointer-events-none" :style="{
                backgroundImage: 'linear-gradient(rgba(255,255,255,0.045) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,0.045) 1px, transparent 1px)',
                backgroundSize: '36px 36px',
            }" aria-hidden="true"></div>

            <!-- Wordmark -->
            <div class="relative">
                <div class="flex items-center gap-3.5">
                    <div class="w-11 h-11 bg-white rounded-lg flex items-center justify-center">
                        <span class="text-[#0F1B2D] font-extrabold text-base tracking-tight">FW</span>
                    </div>
                    <div>
                        <div class="font-bold text-[15px] leading-tight tracking-tight">Free Public Internet Access Program <span class="text-slate-400">(FPIAP)</span></div>
                        <div class="text-[11px] text-slate-400 uppercase tracking-[0.14em] mt-0.5">FreeWiFi · Device Operations</div>
                    </div>
                </div>
            </div>

            <!-- Mission + live ledger -->
            <div class="relative max-w-xl">
                <h1 class="text-[2.6rem] leading-[1.12] font-bold tracking-tight text-white">
                    Every public WiFi site,<br />
                    <span class="text-slate-400">accounted for — every day.</span>
                </h1>
                <p class="mt-5 text-[15px] leading-relaxed text-slate-400 max-w-md">
                    The operations console for the FPIAP FreeWiFi program: daily site uptime,
                    field equipment, and reports for every barangay we connect.
                </p>

                <!-- Recent telemetry ledger -->
                <div v-if="recent.length" class="mt-10 max-w-md">
                    <div class="text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-500 mb-3">
                        Latest field reports
                    </div>
                    <div class="border-t border-white/10">
                        <div v-for="(r, i) in recent" :key="i"
                            class="flex items-center gap-3 py-2.5 border-b border-white/10 font-mono text-xs">
                            <span class="w-2 h-2 rounded-full shrink-0" :class="dotFor[r.status] || 'bg-slate-500'"></span>
                            <span class="text-slate-300 w-28 truncate">{{ r.code }}</span>
                            <span class="text-slate-500 flex-1 truncate">{{ r.name }}</span>
                            <span class="text-slate-500 hidden xl:inline">{{ r.municipality }}</span>
                            <span class="text-right tabular-nums" :class="{
                                'text-emerald-400': r.status === 'UP',
                                'text-red-400': r.status.startsWith('DOWN'),
                                'text-amber-400': r.status === 'NO_NMS',
                                'text-slate-500': r.status === 'NO_DATA',
                            }">{{ r.status.replace('_', ' ') }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Network readout + program footer -->
            <div class="relative">
                <div class="grid grid-cols-3 gap-6 max-w-md mb-10">
                    <div>
                        <div class="text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-500">Sites</div>
                        <div class="text-3xl font-bold tabular-nums mt-1">{{ network.sites }}</div>
                    </div>
                    <div>
                        <div class="text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-500">Provinces</div>
                        <div class="text-3xl font-bold tabular-nums mt-1">{{ network.provinces }}</div>
                    </div>
                    <div>
                        <div class="text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-500">UP Today</div>
                        <div class="text-3xl font-bold tabular-nums mt-1 text-emerald-400">{{ network.upToday }}</div>
                    </div>
                </div>
                <div class="border-t border-white/10 pt-5 text-xs text-slate-500 flex items-center justify-between gap-4">
                    <span>Department of Information and Communications Technology</span>
                    <span class="hidden lg:inline text-right">Free Public Internet Access Program · FreeWiFi · Broadband ng Masa</span>
                </div>
            </div>
        </div>

        <!-- ═══ Sign-in panel ═══ -->
        <div class="flex min-h-screen items-center justify-center bg-[#FAFAF8] px-6 py-12 sm:px-12">
            <div class="w-full max-w-sm">
                <!-- Compact brand for mobile -->
                <div class="lg:hidden flex items-center gap-3 mb-10">
                    <div class="w-10 h-10 bg-[#0F1B2D] rounded-lg flex items-center justify-center">
                        <span class="text-white font-extrabold text-sm">FW</span>
                    </div>
                    <div>
                        <div class="font-bold text-slate-900 text-sm leading-tight">Free Public Internet Access Program</div>
                        <div class="text-[11px] text-slate-500 uppercase tracking-[0.14em]">FPIAP · FreeWiFi · Device Operations</div>
                    </div>
                </div>

                <h2 class="text-2xl font-bold tracking-tight text-slate-900">Operations console</h2>
                <p class="mt-2 text-sm text-slate-500">Sign in with your DICT account to continue.</p>

                <div v-if="status" class="mt-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
                    {{ status }}
                </div>

                <form class="mt-8 space-y-5" @submit.prevent="submit">
                    <div>
                        <label for="email" class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1.5">Email</label>
                        <input
                            id="email"
                            v-model="form.email"
                            type="email"
                            required
                            autofocus
                            autocomplete="username"
                            class="w-full rounded-lg border-slate-300 bg-white text-sm shadow-none focus:border-[#0E5E6F] focus:ring-[#0E5E6F]/20"
                        />
                        <InputError class="mt-2" :message="form.errors.email" />
                    </div>

                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <label for="password" class="block text-xs font-semibold uppercase tracking-wider text-slate-500">Password</label>
                            <Link
                                v-if="canResetPassword"
                                :href="route('password.request')"
                                class="text-xs font-medium text-[#0E5E6F] hover:text-[#0a414c] hover:underline"
                            >
                                Forgot password?
                            </Link>
                        </div>
                        <input
                            id="password"
                            v-model="form.password"
                            type="password"
                            required
                            autocomplete="current-password"
                            class="w-full rounded-lg border-slate-300 bg-white text-sm shadow-none focus:border-[#0E5E6F] focus:ring-[#0E5E6F]/20"
                        />
                        <InputError class="mt-2" :message="form.errors.password" />
                    </div>

                    <label class="flex items-center gap-2.5 text-sm text-slate-600">
                        <Checkbox name="remember" v-model:checked="form.remember" />
                        Keep me signed in on this device
                    </label>

                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="w-full inline-flex justify-center items-center rounded-lg bg-[#0E5E6F] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#0a414c] focus:outline-none focus:ring-2 focus:ring-[#0E5E6F]/40 focus:ring-offset-2 disabled:opacity-60 transition-colors"
                    >
                        {{ form.processing ? 'Signing in…' : 'Sign in' }}
                    </button>
                </form>

                <p class="mt-10 text-[11px] leading-relaxed text-slate-400 border-t border-slate-200 pt-4">
                    Authorized DICT personnel only. All sign-ins and actions are logged and subject to audit.
                </p>
            </div>
        </div>
    </div>
</template>
