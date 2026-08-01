/**
 * Aventura Load Test — k6 smoke test
 *
 * Install: https://k6.io/docs/getting-started/installation/
 * Run: k6 run tests/load/k6-smoke.js
 * With env: k6 run -e BASE_URL=https://aventura.vn tests/load/k6-smoke.js
 */
import http from 'k6/http';
import { check, sleep } from 'k6';
import { Rate, Trend } from 'k6/metrics';

const BASE = __ENV.BASE_URL || 'http://127.0.0.1:8000';

const errorRate = new Rate('errors');
const pageLoadTime = new Trend('page_load_time');

export const options = {
    stages: [
        { duration: '30s', target: 10 },   // Ramp up to 10 users
        { duration: '1m', target: 50 },    // Hold 50 users
        { duration: '30s', target: 100 },  // Peak 100 users
        { duration: '1m', target: 100 },   // Hold peak
        { duration: '30s', target: 0 },    // Ramp down
    ],
    thresholds: {
        http_req_duration: ['p(95)<2000'],  // 95% requests < 2s
        errors: ['rate<0.05'],              // Error rate < 5%
        page_load_time: ['p(95)<3000'],     // 95% page loads < 3s
    },
};

export default function () {
    // 1. Landing page (public)
    let res = http.get(`${BASE}/`);
    check(res, { 'landing 200': (r) => r.status === 200 });
    errorRate.add(res.status !== 200);
    pageLoadTime.add(res.timings.duration);

    sleep(1);

    // 2. Login page
    res = http.get(`${BASE}/login`);
    check(res, { 'login page 200': (r) => r.status === 200 });
    errorRate.add(res.status !== 200);

    sleep(0.5);

    // 3. Health check (API)
    res = http.get(`${BASE}/api/health`);
    check(res, {
        'health 200': (r) => r.status === 200,
        'db healthy': (r) => JSON.parse(r.body).database === true,
        'cache healthy': (r) => JSON.parse(r.body).cache === true,
    });
    errorRate.add(res.status !== 200);

    sleep(0.5);

    // 4. News page (public)
    res = http.get(`${BASE}/tin-tuc`);
    check(res, { 'news 200': (r) => r.status === 200 });
    errorRate.add(res.status !== 200);

    sleep(1);
}

export function handleSummary(data) {
    const summary = {
        total_requests: data.metrics.http_reqs.values.count,
        avg_duration_ms: Math.round(data.metrics.http_req_duration.values.avg),
        p95_duration_ms: Math.round(data.metrics.http_req_duration.values['p(95)']),
        error_rate: (data.metrics.errors.values.rate * 100).toFixed(2) + '%',
        max_vus: data.metrics.vus_max.values.max,
    };

    console.log('\n=== AVENTURA LOAD TEST RESULTS ===');
    console.log(JSON.stringify(summary, null, 2));
    console.log('==================================\n');

    return {};
}
