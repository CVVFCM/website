const {test, expect} = require("@playwright/test");

// Masked: content changes, geometry does not.
const MASKED = [".HomepageLive"];

// Removed before the shot: these change height on their own — the feeds are fetched over the
// network and cached for an hour, the toolbar belongs to the dev server. Masking keeps a region's
// height, which is not enough when the height is what moves.
const REMOVED = [".HomepageFacebook", ".HomepageInstagram", ".sf-toolbar", "#sfWebDebugToolbar"];

// Paths come from the fixtures. `SELECT slug FROM ro_routes` lists them if a fixture is reworked.
const PAGES = [
    {name: "home", path: "/", mask: MASKED},
    {name: "calendar", path: "/evenements", mask: []},
    {name: "event", path: "/evenements/regates/coupe-bernard-bozier/coupe-bernard-bozier-2027", mask: []},
    {name: "default-page", path: "/ecole-de-voile/laser/initiation", mask: []},
    {name: "contact", path: "/contact", mask: []},
];

for (const page of PAGES) {
    test(`${page.name} has not moved`, async ({page: browserPage}) => {
        const response = await browserPage.goto(page.path, {waitUntil: "networkidle"});
        expect(response.status(), `${page.path} must answer 200`).toBe(200);

        // A fatal error inside the FrankenPHP worker comes back as a 200 carrying a stack trace,
        // which screenshots perfectly happily.
        const body = await browserPage.locator("body").innerText();
        expect(body, `${page.path} renders a PHP error`).not.toMatch(/Fatal error|Uncaught|Exception/);

        // Lazy images never fire anything until the browser decides to fetch them, so waiting on
        // their load event deadlocks; and Sulu builds a media format on first request, so a cold run
        // can photograph a logo mid-generation. Pull them in, then wait for the pixels.
        await browserPage.evaluate(async () => {
            for (const image of document.querySelectorAll('img[loading="lazy"]')) {
                image.loading = "eager";
            }

            window.scrollTo(0, document.body.scrollHeight);
            window.scrollTo(0, 0);

            await Promise.all(Array.from(document.images).map((image) => image.decode().catch(() => {})));
        });

        await browserPage.waitForLoadState("networkidle");
        await browserPage.addStyleTag({content: `${REMOVED.join(", ")} { display: none !important; }`});

        await expect(browserPage).toHaveScreenshot(`${page.name}.png`, {
            fullPage: true,
            mask: page.mask.map((selector) => browserPage.locator(selector)),
        });
    });
}
