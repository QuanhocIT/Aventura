/**
 * Aventura Load Test — Authenticated user flows
 *
 * Run: k6 run tests/load/k6-authenticated.js
 * This tests dashboard and API endpoints with session cookies
 */
import http from 'k6/http';
import { check, sleep } from 'k6';
import { Rate } from 'k6/metrics';

const BASE = __ENV.BASE_URL || 'http://127.0.0.1:8000';
const errorRate = new Rate('errors');

export const options = {
    stages: [
        { duration: '20s', target: 5 },
        { duration: '1m', target: 20 },
        { duration: '30s', target: 0 },
    ],
    thresholds: {
        http_req_duration: ['p(95)<3000'],
        errors: ['rate<0.1'],
    },
};

function login(email, password) {
    const loginPage = http.get(`${BASE}/login`);
    const token = loginPage.html().find('input[name="_token"]').attr('value') || '';

    return http.post(`${BASE}/login`, {
        email: email,
        password: password,
        _token: token,
    }, { redirects: 0 });
}

export default function () {
    const accounts = [
        'free@test.com',
        'starter@test.com',
        'pro@test.com',
        'enterprise@test.com',
    ];
    const email = accounts[Math.floor(Math.random() * accounts.length)];

    // Login
    const loginRes = login(email, 'password');
    check(loginRes, { 'login redirect': (r) => r.status === 302 });

    if (loginRes.status !== 302) {
        errorRate.add(true);
        return;
    }

    sleep(1);

    // Dashboard
    let res = http.get(`${BASE}/dashboard`);
    check(res, { 'dashboard 200': (r) => r.status === 200 });
    errorRate.add(res.status !== 200);

    sleep(1);

    // Orders page
    res = http.get(`${BASE}/orders`);
    check(res, { 'orders 200': (r) => r.status === 200 });
    errorRate.add(res.status !== 200);

    sleep(1);

    // Products page
    res = http.get(`${BASE}/products`);
    check(res, { 'products 200': (r) => r.status === 200 });
    errorRate.add(res.status !== 200);

    sleep(0.5);
}
