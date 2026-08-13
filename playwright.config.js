const {defineConfig, devices} = require("@playwright/test");

/**
 * Visual regression suite. Drives the dev server (`make up`) from inside the pinned Playwright
 * image, so a baseline captured locally is byte-comparable with a run in CI. The dev database is
 * built from the same fixtures, seeded to be reproducible.
 *
 * One project per viewport width. 1280x720 is the one that matters most: it is what a 1920x1080
 * screen reports at the 150% scaling most Windows laptops ship with.
 */
module.exports = defineConfig({
    testDir: "tests/visual",
    // Baselines sit next to the spec rather than in a per-platform folder: the browser always runs
    // in the same container image, so there is only ever one platform to describe.
    snapshotPathTemplate: "{testDir}/__screenshots__/{arg}-{projectName}{ext}",
    fullyParallel: false,
    // A screenshot that only passes on the second try is not a passing screenshot.
    retries: 0,
    workers: 1,
    forbidOnly: !!process.env.CI,
    reporter: process.env.CI ? [["html", {open: "never"}], ["list"]] : [["list"]],
    use: {
        baseURL: process.env.SCREENSHOT_BASE_URL || "https://localhost",
        // The dev certificate is a local mkcert one, unknown to the browser in the container.
        ignoreHTTPSErrors: true,
    },
    expect: {
        toHaveScreenshot: {
            // Text antialiasing and PNG quantisation move a few pixels around without anything
            // having changed. Small enough to still catch a shifted block, loose enough to survive
            // a font rasterising one shade differently.
            maxDiffPixelRatio: 0.002,
            animations: "disabled",
            caret: "hide",
            scale: "css",
        },
    },
    projects: [
        {
            name: "1920",
            use: {...devices["Desktop Chrome"], viewport: {width: 1920, height: 1080}},
        },
        {
            name: "1280",
            use: {...devices["Desktop Chrome"], viewport: {width: 1280, height: 720}},
        },
        {
            name: "400",
            use: {...devices["Desktop Chrome"], viewport: {width: 400, height: 900}},
        },
    ],
});
