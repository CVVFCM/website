const {test, expect} = require("@playwright/test");

const config = require("./energy.config.js");

test.describe.configure({mode: "serial"});

async function counters() {
    const response = await fetch(`${config.PROBE_URL}/counters`);
    if (!response.ok) {
        throw new Error(`energy-probe /counters returned ${response.status}`);
    }

    return response.json();
}

function delta(before, after) {
    const targets = {};
    for (const name of Object.keys(after)) {
        targets[name] = after[name] - before[name];
    }

    return targets;
}

async function measureLoad(browser, path) {
    const context = await browser.newContext({
        baseURL: config.BASE_URL,
        // The dev certificate is Caddy's internal CA, unknown to the browser in the container.
        ignoreHTTPSErrors: true,
    });
    const page = await context.newPage();

    // encodedDataLength is what actually crossed the wire: zstd-compressed bodies, HPACK-compressed
    // headers. request.sizes() reports raw header text instead, which over-counts badly under HTTP/2.
    let bytes = 0;
    const session = await context.newCDPSession(page);
    await session.send("Network.enable");
    session.on("Network.loadingFinished", (event) => {
        bytes += event.encodedDataLength;
    });

    try {
        const before = await counters();
        const startedAt = Date.now();

        const response = await page.goto(path, {waitUntil: "networkidle"});

        const durationMs = Date.now() - startedAt;
        const after = await counters();

        expect(response.status(), `${path} must answer 200`).toBe(200);

        // A fatal error inside the FrankenPHP worker comes back as a 200 carrying a stack trace,
        // and a stack trace retires instructions like anything else.
        const body = await page.locator("body").innerText();
        expect(body, `${path} renders a PHP error`).not.toMatch(/Fatal error|Uncaught|Exception/);

        return {durationMs, bytes, targets: delta(before, after)};
    } finally {
        await context.close();
    }
}

for (const target of config.PAGES) {
    test(`${target.name} energy`, async ({browser}, testInfo) => {
        testInfo.setTimeout((config.SAMPLES + config.WARMUP) * 30_000 + config.IDLE_MS + 30_000);

        // Background rate first, while nothing is being asked of the site. Reported, not subtracted:
        // it is a trust signal, and on the prod-like stack it is very close to zero.
        const idleBefore = await counters();
        const idleStartedAt = Date.now();
        await new Promise((resolve) => setTimeout(resolve, config.IDLE_MS));
        const idle = {durationMs: Date.now() - idleStartedAt, targets: delta(idleBefore, await counters())};

        for (let i = 0; i < config.WARMUP; i++) {
            await measureLoad(browser, target.path);
        }

        const samples = [];
        for (let i = 0; i < config.SAMPLES; i++) {
            samples.push(await measureLoad(browser, target.path));
        }

        // A probe holding counters for a container that has since been recreated reports zero
        // rather than failing, and zero reads as "this page is free". Refuse that reading.
        for (const name of Object.keys(idle.targets)) {
            const counted = samples.reduce((sum, sample) => sum + sample.targets[name], 0);
            expect(counted, `${name} counted no instructions — probe is stale, restart energy-probe`).toBeGreaterThan(0);
        }

        const transferred = samples.reduce((sum, sample) => sum + sample.bytes, 0);
        expect(transferred, `${target.path} transferred no bytes — CDP is not reporting`).toBeGreaterThan(0);

        // Raw only. The reporter owns medians and the energy conversion, so the JSON artifact keeps
        // what was measured separate from what was derived from it.
        await testInfo.attach("energy", {
            contentType: "application/json",
            body: JSON.stringify({name: target.name, path: target.path, idle, samples}),
        });
    });
}
