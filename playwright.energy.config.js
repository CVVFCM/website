const {defineConfig, devices} = require("@playwright/test");

const energy = require("./tests/energy/energy.config.js");

module.exports = defineConfig({
    testDir: "tests/energy",

    fullyParallel: false,
    workers: 1,

    retries: 0,
    forbidOnly: !!process.env.CI,
    reporter: [["list"], ["./tests/energy/energy-reporter.js"]],
    use: {
        baseURL: energy.BASE_URL,
        // Drives the `browser` service rather than a local Chromium, so the browser's cgroup holds
        // only Chromium. Launch options are ignored when connecting; context options still apply.
        connectOptions: {wsEndpoint: energy.BROWSER_WS},
        // The dev certificate is Caddy's internal CA, unknown to the browser in the container.
        ignoreHTTPSErrors: true,
    },
    projects: [
        {
            name: "1280",
            use: {...devices["Desktop Chrome"], viewport: {width: 1280, height: 720}},
        },
    ],
});
