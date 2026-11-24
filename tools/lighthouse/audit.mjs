// Signs in to the panels of a running copy of the system and audits their pages with
// Lighthouse. Prints the four scores of every page and what keeps each from 100.
//   node audit.mjs            (the system must answer on port 8000; see .github/workflows/lighthouse.yml)
import puppeteer from 'puppeteer';
import lighthouse from 'lighthouse';

const admin = 'http://admin.tabib.test:8000';
const tenant = 'http://demo.tabib.test:8000';
const password = 'ci-password-1';

const groups = [
    { name: 'sign-in pages', pages: [admin + '/admin/login', admin + '/register', tenant + '/login'] },
    { name: 'administrator panel', login: [admin + '/admin/login', 'ci@example.com'], pages: [admin + '/admin', admin + '/admin/tenants', admin + '/admin/settings', admin + '/admin/tenants/1'] },
    { name: 'Clinic panel', login: [tenant + '/login', 'owner@example.com'], pages: [tenant + '/', tenant + '/users', tenant + '/users/create', tenant + '/branches', tenant + '/settings'] },
];
// not about the pages: this copy runs on plain http on a made-up host name
const skip = new Set(['is-on-https', 'redirects-http', 'uses-http2', 'uses-long-cache-ttl', 'uses-text-compression']);
const form = (process.argv[2] || 'desktop');

const browser = await puppeteer.launch({ headless: 'new', args: ['--no-sandbox', '--disable-gpu', '--unsafely-treat-insecure-origin-as-secure=' + admin + ',' + tenant] });
let worst = 100;
for (const group of groups) {
    const context = await browser.createBrowserContext();
    const page = await context.newPage();
    if (group.login) {
        await page.goto(group.login[0], { waitUntil: 'networkidle0' });
        await page.type('#email', group.login[1]);
        await page.type('#password', password);
        await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle0' }), page.click('button.btn-gradient')]);
        if (page.url().includes('login')) { console.log(`!! could not sign in to the ${group.name}`); continue; }
    }
    console.log(`\n=== ${group.name} (${form})`);
    for (const url of group.pages) {
        const flags = { disableStorageReset: true, logLevel: 'error', output: 'json', onlyCategories: ['performance', 'accessibility', 'best-practices', 'seo'] };
        const config = form === 'desktop'
            ? { extends: 'lighthouse:default', settings: { formFactor: 'desktop', screenEmulation: { mobile: false, width: 1350, height: 940, deviceScaleFactor: 1, disabled: false }, throttling: { rttMs: 40, throughputKbps: 10240, cpuSlowdownMultiplier: 1 } } }
            : undefined;
        let result;
        try { result = (await lighthouse(url, flags, config, page)).lhr; } catch (e) { console.log(`${url}  FAILED: ${e.message}`); continue; }
        const c = result.categories;
        const score = (k) => Math.round((c[k].score ?? 0) * 100);
        console.log(`\n${url.replace(/^http:\/\/[^/]+/, '')}   perf ${score('performance')}  a11y ${score('accessibility')}  best ${score('best-practices')}  seo ${score('seo')}   (status ${result.audits['http-status-code']?.score === 1 ? 'ok' : '?'}, final ${result.finalDisplayedUrl.replace(/^http:\/\/[^/]+/, '')})`);
        for (const key of ['accessibility', 'best-practices', 'seo', 'performance']) {
            for (const ref of c[key].auditRefs) {
                const audit = result.audits[ref.id];
                if ((!group.live && skip.has(ref.id)) || audit.score === null || audit.score >= (key === 'performance' ? 0.9 : 1) || (key === 'performance' && !ref.weight)) continue;
                worst = Math.min(worst, score(key));
                const items = (audit.details?.items || []).slice(0, 4).map((i) => {
                    const node = i.node || i.source || {};
                    return '      - ' + String(node.snippet || i.url || i.description || i.sourceLocation?.url || JSON.stringify(i)).replace(/\s+/g, ' ').slice(0, 230) + (node.explanation ? '  [' + node.explanation.replace(/\s+/g, ' ').slice(0, 160) + ']' : '');
                });
                console.log(`   [${key}] ${ref.id}: ${audit.title}${audit.displayValue ? ' (' + audit.displayValue + ')' : ''}`);
                if (items.length) console.log(items.join('\n'));
            }
        }
        for (const shift of (result.audits['layout-shifts']?.details?.items || []).slice(0, 3)) {
            console.log('   shift ' + (shift.score ?? '') + ': ' + String(shift.node?.snippet || '').replace(/\s+/g, ' ').slice(0, 200) + ' ' + JSON.stringify(shift.subItems?.items?.map((i) => i.cause || i.extra?.value || i) || []).slice(0, 300));
        }
    }
    await context.close();
}
await browser.close();
console.log(`\nLowest score with something to fix: ${worst}`);
