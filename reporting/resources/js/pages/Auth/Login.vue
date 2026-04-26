<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import Button from '../../components/ui/Button.vue';
import { LayoutDashboard } from 'lucide-vue-next';

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

function submit() {
    form.post('/login', {
        onFinish: () => form.reset('password'),
    });
}
</script>

<template>
    <Head title="Login" />

    <div class="flex min-h-screen items-center justify-center bg-background px-4">
        <div class="w-full max-w-sm">
            <div class="mb-8 flex flex-col items-center gap-3 text-center">
                <div class="rounded-lg bg-primary p-3 text-primary-foreground">
                    <LayoutDashboard class="size-7" />
                </div>
                <div>
                    <h1 class="text-xl font-semibold">PEN-Plus Reporting</h1>
                    <p class="mt-1 text-sm text-muted-foreground">Sign in to access the dashboard</p>
                </div>
            </div>

            <form @submit.prevent="submit" class="space-y-4 rounded-xl border bg-card p-6 shadow-sm">
                <div class="space-y-1.5">
                    <label class="text-sm font-medium" for="email">Email</label>
                    <input
                        id="email"
                        v-model="form.email"
                        type="email"
                        required
                        autocomplete="email"
                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-ring focus:outline-none focus:ring-2"
                        :class="{ 'border-destructive': form.errors.email }"
                    />
                    <p v-if="form.errors.email" class="text-xs text-destructive">{{ form.errors.email }}</p>
                </div>

                <div class="space-y-1.5">
                    <label class="text-sm font-medium" for="password">Password</label>
                    <input
                        id="password"
                        v-model="form.password"
                        type="password"
                        required
                        autocomplete="current-password"
                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-ring focus:outline-none focus:ring-2"
                        :class="{ 'border-destructive': form.errors.password }"
                    />
                </div>

                <div class="flex items-center gap-2">
                    <input
                        id="remember"
                        v-model="form.remember"
                        type="checkbox"
                        class="h-4 w-4 rounded border-input"
                    />
                    <label for="remember" class="text-sm text-muted-foreground">Remember me</label>
                </div>

                <Button type="submit" class="w-full" :disabled="form.processing">
                    {{ form.processing ? 'Signing in...' : 'Sign In' }}
                </Button>
            </form>
        </div>
    </div>
</template>