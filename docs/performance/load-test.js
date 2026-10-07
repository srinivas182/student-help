/**
 * k6 load test for DX Student Help.
 *
 * Models an evening peak: mostly students reading and asking, some tutors
 * working the queue, a few admins. Run against staging, never production.
 *
 *   k6 run -e BASE_URL=https://student-help.rightally.io docs/performance/load-test.js
 */
import http from 'k6/http';
import { check, group, sleep } from 'k6';
import { Rate, Trend } from 'k6/metrics';

const failures = new Rate('failed_requests');
const pageLoad = new Trend('page_load_ms');

const BASE = __ENV.BASE_URL || 'http://localhost';

export const options = {
    scenarios: {
        // Ramps to 500 virtual users, each doing roughly what a learner does
        evening_peak: {
            executor: 'ramping-vus',
            startVUs: 0,
            stages: [
                { duration: '1m', target: 50 },
                { duration: '2m', target: 200 },
                { duration: '3m', target: 500 },
                { duration: '2m', target: 500 },
                { duration: '1m', target: 0 },
            ],
            gracefulRampDown: '30s',
        },
    },
    thresholds: {
        // A learner on a slow connection should still see a page in good time
        http_req_duration: ['p(95)<800', 'p(99)<2000'],
        failed_requests: ['rate<0.01'],
    },
};

export default function () {
    group('landing page', () => {
        const response = http.get(`${BASE}/`);
        pageLoad.add(response.timings.duration);
        failures.add(response.status !== 200);
        check(response, { 'landing loads': (r) => r.status === 200 });
    });

    sleep(Math.random() * 3 + 1);

    group('login page', () => {
        const response = http.get(`${BASE}/login`);
        failures.add(response.status !== 200);
        check(response, { 'login loads': (r) => r.status === 200 });
    });

    sleep(Math.random() * 2 + 1);

    group('health check', () => {
        const response = http.get(`${BASE}/up`);
        failures.add(response.status !== 200);
    });

    sleep(Math.random() * 5 + 2);
}

export function handleSummary(data) {
    const p95 = data.metrics.http_req_duration.values['p(95)'];
    const rate = data.metrics.failed_requests ? data.metrics.failed_requests.values.rate : 0;

    return {
        stdout: `
DX Student Help load test
-------------------------
Requests:        ${data.metrics.http_reqs.values.count}
Peak VUs:        ${data.metrics.vus_max.values.max}
p95 duration:    ${p95.toFixed(0)} ms
p99 duration:    ${data.metrics.http_req_duration.values['p(99)'].toFixed(0)} ms
Failure rate:    ${(rate * 100).toFixed(2)}%

${p95 < 800 && rate < 0.01 ? 'PASS — comfortable at this level' : 'INVESTIGATE — thresholds exceeded'}
`,
    };
}
