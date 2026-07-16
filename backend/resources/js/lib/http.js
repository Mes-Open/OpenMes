// Shared CSRF + JSON fetch helpers for same-origin admin actions.

function getCsrf() {
    return document.querySelector('meta[name=csrf-token]')?.content ?? '';
}

// JSON fetch with the CSRF + XHR headers Laravel expects for stateful requests.
export async function apiCall(url, method, body) {
    return fetch(url, {
        method,
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': getCsrf(),
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: body === undefined ? undefined : JSON.stringify(body),
    });
}

// Read-only JSON GET (no CSRF needed); pairs with apiCall for writes.
export async function apiGet(url) {
    return fetch(url, {
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    });
}
