const {test, expect} = require("@playwright/test");

/**
 * Regions whose content is not ours to pin down. Masked rather than compared: Playwright paints
 * them over before diffing.
 *
 * Note what masking does NOT buy: a masked block that changes *height* still pushes everything
 * below it and fails the comparison. That is deliberate — a layout shift is a real regression, even
 * when its cause is a third party.
 */
const VOLATILE = [
    // Weather band: the date comes from Twig's `now` and the readings from the latest stored
    // observation. Its content moves, its geometry does not, so masking is enough.
    ".HomepageLive",
];

/**
 * Sections removed from the page before the shot rather than masked.
 *
 * Masking paints over a region but keeps its height, which is fine for text that changes inside a
 * fixed box. The social feeds are not that: they are fetched over the network, cached for an hour,
 * and fall back to an empty list when the call fails — so the number of posts, and with it the
 * height of the section, changes on its own. Everything below then shifts and the comparison fails
 * for a reason that has nothing to do with the site. Observed: a baseline recorded an hour earlier
 * failed by 413px once the Facebook cache expired.
 *
 * The cost is honest and worth stating: these two sections are not covered by the suite at all.
 */
const REMOVED = [".HomepageFacebook", ".HomepageInstagram"];

const PAGES = [
    {name: "accueil", path: "/", mask: VOLATILE},
    // Paths come from the fixtures, not from the dev database — they differ. `make reset-test`
    // followed by `SELECT slug FROM ro_routes` is how to find them again if a fixture is reworked.
    {name: "calendrier", path: "/evenements", mask: []},
    {name: "evenement", path: "/evenements/regates/coupe-bernard-bozier/coupe-bernard-bozier-2027", mask: []},
    // A leaf: DefaultPagesFixtures only publishes the deepest pages, so the intermediate levels of
    // the tree answer 404.
    {name: "page-defaut", path: "/ecole-de-voile/laser/initiation", mask: []},
    {name: "contact", path: "/contact", mask: []},
];

for (const page of PAGES) {
    test(`${page.name} n'a pas bougé`, async ({page: browserPage}) => {
        const response = await browserPage.goto(page.path, {waitUntil: "networkidle"});
        expect(response.status(), `${page.path} doit répondre 200`).toBe(200);

        // A 200 is not proof the page rendered: a fatal error inside the FrankenPHP worker comes
        // back as a 200 carrying a stack trace, which screenshots perfectly happily. Check the page
        // is the site before comparing pixels.
        const body = await browserPage.locator("body").innerText();
        expect(body, `${page.path} rend une erreur PHP`).not.toMatch(/Fatal error|Uncaught|Exception/);
        await expect(
            browserPage.locator("footer, .SiteFooter"),
            `${page.path} n'a pas de pied de page, la mise en page n'est pas celle du site`,
        ).toBeVisible();

        // Two things make a page photograph differently from one run to the next, and both are
        // about images.
        //
        // Lazy ones below the fold: waiting for their load event deadlocks, because a lazy image
        // the browser has not decided to fetch never fires anything and stays `complete === false`
        // for ever. Flip them to eager and pull them in rather than waiting to be told.
        //
        // And Sulu builds a media format the first time it is requested, so a first run can
        // photograph a logo mid-generation while the next gets it from cache — which is exactly how
        // the partner strip came out different on two consecutive runs. decode() resolves only once
        // the bytes are in and rasterised.
        await browserPage.evaluate(async () => {
            for (const image of document.querySelectorAll('img[loading="lazy"]')) {
                image.loading = "eager";
            }

            window.scrollTo(0, document.body.scrollHeight);
            window.scrollTo(0, 0);

            // A broken image rejects; that is the page's problem to show, not this helper's to hide,
            // and the screenshot will carry it either way.
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
