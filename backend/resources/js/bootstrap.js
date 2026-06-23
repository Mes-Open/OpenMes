import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

axios.interceptors.response.use(
    (response) => response,
    (error) => {
        const status = error.response?.status;
        if (status >= 400) {
            import('./lib/posthog').then(({ posthog }) => {
                if (!posthog.__loaded) return;
                posthog.capture('http_error', {
                    status,
                    method: error.config?.method?.toUpperCase(),
                    url: error.config?.url,
                    response_message: error.response?.data?.message,
                });
            });
        }
        return Promise.reject(error);
    },
);
