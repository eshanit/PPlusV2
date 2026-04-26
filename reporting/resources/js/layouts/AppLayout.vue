<script setup>
import Button from '../components/ui/Button.vue';
import { Link, usePage } from '@inertiajs/vue3';
import {
    Activity,
    BarChart3,
    ChevronRight,
    ClipboardList,
    Clock,
    FileSpreadsheet,
    Gauge,
    LayoutDashboard,
    Target,
    TrendingUp,
    UserCheck,
    X,
} from 'lucide-vue-next';
import { ref } from 'vue';

const page = usePage();
const mobileOpen = ref(false);

const sections = [
    {
        label: 'Overview',
        items: [
            { href: '/', label: 'Dashboard', icon: LayoutDashboard },
            { href: '/exports', label: 'Exports', icon: FileSpreadsheet },
        ],
    },
    {
        label: 'Journey Reports',
        items: [
            { href: '/journey-status', label: 'Journey Status', icon: Target },
            { href: '/low-score-watchlist', label: 'Low-Score Watchlist', icon: Activity },
            { href: '/gap-overview', label: 'Gap Overview', icon: ClipboardList },
        ],
    },
    {
        label: 'Trend Analysis',
        items: [
            { href: '/needs-attention', label: 'Needs Attention', icon: Clock },
            { href: '/score-trajectory', label: 'Score Trajectory', icon: TrendingUp },
            { href: '/time-to-competence', label: 'Time to Competence', icon: Gauge },
            { href: '/cohort-progress', label: 'Cohort Progress', icon: BarChart3 },
        ],
    },
    {
        label: 'Evaluators',
        items: [
            { href: '/evaluator-activity', label: 'Evaluator Activity', icon: UserCheck },
        ],
    },
];

function isActive(href) {
    if (href === '/') return page.url === '/' || page.url === '/dashboard';
    return page.url.startsWith(href);
}

function closeMobile() {
    mobileOpen.value = false;
}
</script>

<template>
    <div class="flex min-h-screen">
        <aside class="fixed inset-y-0 left-0 z-50 flex w-56 flex-col border-r bg-card lg:static">
            <div class="flex h-14 items-center justify-between gap-2 border-b px-4">
                <div class="flex items-center gap-2">
                    <div class="rounded-md bg-primary p-1.5 text-primary-foreground">
                        <LayoutDashboard class="size-4" />
                    </div>
                    <span class="text-sm font-semibold">PEN-Plus</span>
                </div>
                <button class="lg:hidden" @click="closeMobile">
                    <X class="size-4 text-muted-foreground" />
                </button>
            </div>

            <nav class="flex-1 overflow-y-auto py-3 px-2">
                <div v-for="section in sections" :key="section.label" class="mb-4">
                    <p class="mb-1 px-3 text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">
                        {{ section.label }}
                    </p>
                    <Link
                        v-for="item in section.items"
                        :key="item.href"
                        :href="item.href"
                        @click="closeMobile"
                        :class="[
                            'flex items-center gap-2.5 rounded-md px-3 py-2 text-sm transition-colors',
                            isActive(item.href)
                                ? 'bg-primary/10 font-medium text-primary'
                                : 'text-muted-foreground hover:bg-secondary/60 hover:text-foreground',
                        ]"
                    >
                        <component :is="item.icon" class="size-4 shrink-0" />
                        {{ item.label }}
                    </Link>
                </div>
            </nav>

            <div class="border-t p-3">
                <Button as="a" href="/admin" variant="outline" size="sm" class="w-full justify-center">
                    Admin Panel ↗
                </Button>
            </div>
        </aside>

        <div
            v-if="mobileOpen"
            class="fixed inset-0 z-40 bg-black/50 lg:hidden"
            @click="closeMobile"
        />

        <div class="flex flex-1 flex-col">
            <header class="flex h-14 items-center gap-4 border-b bg-card px-4 lg:hidden">
                <button @click="mobileOpen = true" class="text-muted-foreground">
                    <ChevronRight class="size-5" />
                </button>
                <span class="text-sm font-medium">PEN-Plus Reporting</span>
            </header>

            <main class="flex-1">
                <slot />
            </main>
        </div>
    </div>
</template>