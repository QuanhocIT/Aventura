import { check, sleep } from 'k6';
import http from 'k6/http';

export const options = {
    stages: [
        { duration: '30s', target: 20 }, // Ramp-up to 20 users over 30s
        { duration: '1m', target: 50 },  // Sustained load with 50 users
        { duration: '15s', target: 0 },  // Ramp-down
    ],
    thresholds: {
        http_req_duration: ['p(95)<500'], // 95% of requests must complete below 500ms
        http_req_failed: ['rate<0.01'],   // Error rate must be under 1%
    },
};

const BASE_URL = __ENV.TARGET_URL || 'http://localhost';

export default function () {
    // 1. Healthcheck Probe
    const healthRes = http.get(`${BASE_URL}/api/health`);
    check(healthRes, {
        'health status is 200': (r) => r.status === 200,
    });

    // 2. Readiness Probe
    const readyRes = http.get(`${BASE_URL}/api/ready`);
    check(readyRes, {
        'ready status is 200 or 503': (r) => r.status === 200 || r.status === 503,
    });

    // 3. Simulated Public Status Page
    const statusRes = http.get(`${BASE_URL}/status`);
    check(statusRes, {
        'status page returns valid response': (r) => r.status === 200 || r.status === 302,
    });

    sleep(1);
}
