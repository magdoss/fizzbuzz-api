import http from 'k6/http';
import { check } from 'k6';
import { Counter } from 'k6/metrics';

const base = __ENV.BASE_URL || 'http://localhost:8080';
const limit = __ENV.LIMIT || '100';
const rateLimited = new Counter('rate_limited');

// a 429 is an expected answer of the API, not a failure of the service
http.setResponseCallback(http.expectedStatuses(200, 429));

export const options = {
  vus: Number(__ENV.VUS || 50),
  duration: __ENV.DURATION || '30s',
  discardResponseBodies: true,
  summaryTrendStats: ['avg', 'med', 'p(95)', 'max'],
};

export default function () {
  // ten distinct requests, so the statistics upsert touches several rows like real traffic would
  const int1 = 2 + (__ITER % 10);
  const response = http.get(`${base}/fizzbuzz?int1=${int1}&int2=5&limit=${limit}&str1=fizz&str2=buzz`, {
    headers: { 'Accept-Encoding': 'gzip' },
  });

  rateLimited.add(response.status === 429 ? 1 : 0);
  check(response, { 'answered 200': (r) => r.status === 200 });
}
