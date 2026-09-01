/**
 * Constants for the energy suite. Everything here is an assumption, so everything here is
 * overridable and everything here is reprinted next to the numbers it produced.
 *
 * Output is CO2 throughout. Compute is converted instructions -> Wh -> CO2; network goes straight to
 * CO2 from ADEME's per-GB factor. Not the reverse: that factor embeds embodied carbon rather than
 * electricity alone, so dividing it by a grid intensity to "recover Wh" would be wrong.
 */
module.exports = {
    /**
     * Wh per retired instruction.
     *
     * Derived for an Ampere Altra-class host, which is what production runs on: roughly 250 W across
     * 80 cores retiring ~4.5 G instructions per second each, so ~3.6e11 instructions per second for
     * the package. 250 / 3.6e11 = 6.9e-10 J = 1.9e-13 Wh. Rounded up.
     *
     * This is an estimate, not a measurement. Comparisons between pages and between runs are sound;
     * the absolute Wh is only as good as this constant, which is why the reporter prints it.
     */
    WH_PER_INSTRUCTION: Number(process.env.ENERGY_WH_PER_INSTRUCTION) || 2.0e-13,

    /**
     * Wh per retired instruction on a visitor's device — roughly 2.5x the server figure, because a
     * phone or laptop does far less work per watt than datacentre silicon: lower utilisation, and
     * device overheads a server does not carry.
     *
     * Understates a real device regardless: the browser measured here is a headless shell with no
     * screen, no GPU compositing and no radio.
     */
    WH_PER_INSTRUCTION_CLIENT: Number(process.env.ENERGY_WH_PER_INSTRUCTION_CLIENT) || 5.0e-13,

    /** French grid. Production is Scaleway fr-par and the audience is French. */
    KG_CO2E_PER_KWH: Number(process.env.ENERGY_KG_CO2E_PER_KWH) || 0.030,

    /** ADEME, data transfer. Carbon per gigabyte moved, not energy. */
    KG_CO2E_PER_GB: Number(process.env.ENERGY_KG_CO2E_PER_GB) || 1.24e-3,

    /** Which probe targets fold into which layer. The probe itself just counts cgroups. */
    BACKEND_TARGETS: ["php", "database"],
    BROWSER_TARGET: "browser",

    SAMPLES: Number(process.env.ENERGY_SAMPLES) || 10,

    /**
     * Discarded loads before measuring. The first request to a page warms opcache, APCu, the Sulu
     * structure cache and any media format Sulu generates on demand — none of which a returning
     * visitor pays for.
     */
    WARMUP: Number(process.env.ENERGY_WARMUP) || 1,

    /** Quiet window used to measure the background instruction rate. Reported, not subtracted. */
    IDLE_MS: Number(process.env.ENERGY_IDLE_MS) || 2000,

    PROBE_URL: process.env.ENERGY_PROBE_URL || "http://localhost:9777",

    BROWSER_WS: process.env.ENERGY_BROWSER_WS || "ws://127.0.0.1:9779/energy",

    BASE_URL: process.env.ENERGY_BASE_URL || "https://localhost",

    // Paths come from the fixtures, and are the same ones the visual suite pins.
    // `SELECT slug FROM ro_routes` lists them if a fixture is reworked.
    PAGES: [
        {name: "home", path: "/"},
        {name: "calendar", path: "/evenements"},
        {name: "event", path: "/evenements/regates/coupe-bernard-bozier/coupe-bernard-bozier-2027"},
        {name: "default-page", path: "/ecole-de-voile/laser/initiation"},
        {name: "contact", path: "/contact"},
    ],
};
