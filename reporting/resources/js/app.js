import './bootstrap';

import { createInertiaApp } from '@inertiajs/vue3';
import { createApp, h } from 'vue';
import VueApexCharts from 'vue3-apexcharts';

createInertiaApp({
    title: (title) => (title ? `${title} - PEN-Plus Reporting` : 'PEN-Plus Reporting'),
    resolve: (name) => {
        const pages = import.meta.glob('./pages/**/*.vue', { eager: true });

        return pages[`./pages/${name}.vue`];
    },
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(VueApexCharts)
            .mount(el);
    },
});
