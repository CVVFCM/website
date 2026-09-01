const fs = require("node:fs");
const path = require("node:path");

const config = require("./energy.config.js");

const OUTPUT_DIR = path.join(process.cwd(), "var", "energy");
const SCHEMA_VERSION = 2;

// A page load costs a few thousandths of a gram. Milligrams keep the table free of exponents.
const MG_PER_KG = 1e6;

function median(values) {
    if (values.length === 0) {
        return 0;
    }

    const sorted = [...values].sort((a, b) => a - b);
    const middle = Math.floor(sorted.length / 2);

    return sorted.length % 2 === 0 ? (sorted[middle - 1] + sorted[middle]) / 2 : sorted[middle];
}

/** Instruction counts span several orders of magnitude across layers; a bare integer is unreadable. */
function si(value) {
    const units = [
        [1e12, "T"],
        [1e9, "G"],
        [1e6, "M"],
        [1e3, "k"],
    ];

    for (const [scale, suffix] of units) {
        if (Math.abs(value) >= scale) {
            return `${(value / scale).toFixed(1)} ${suffix}`;
        }
    }

    return value.toFixed(0);
}

function mg(kg) {
    return (kg * MG_PER_KG).toFixed(4);
}

function table(headers, rows) {
    const widths = headers.map((header, column) =>
        Math.max(header.length, ...rows.map((row) => String(row[column]).length)));
    const line = (cells) => cells
        .map((cell, column) => (column === 0 ? String(cell).padEnd(widths[column]) : String(cell).padStart(widths[column])))
        .join("  ");

    return [line(headers), widths.map((width) => "-".repeat(width)).join("  "), ...rows.map(line)].join("\n");
}

/** kg CO2e for compute: instructions -> Wh -> kWh -> carbon at the configured grid intensity. */
function computeCO2(instructions, whPerInstruction) {
    return (instructions * whPerInstruction / 1000) * config.KG_CO2E_PER_KWH;
}

/**
 * Turns the raw per-sample deltas into the numbers a human reads: median, and the carbon that
 * follows from it.
 *
 * The idle floor is measured and reported but never subtracted. It was worth subtracting when the
 * dev stack's FrankenPHP file watcher burned 94 M instr/s; on the prod-like stack php idles at
 * exactly zero. What survived of the correction was a burst caught in the 2s window being
 * extrapolated as a steady rate, which understated /contact by 7.5%. Reporting it still earns its
 * place: a background job that ever does start mattering shows up here rather than silently
 * inflating every page.
 */
function summarise(page) {
    const targetNames = Object.keys(page.idle.targets);
    const backendTargets = config.BACKEND_TARGETS.filter((name) => targetNames.includes(name));

    const idleRatePerSecond = {};
    for (const name of targetNames) {
        idleRatePerSecond[name] = (page.idle.targets[name] / page.idle.durationMs) * 1000;
    }

    const samples = page.samples.map((sample) => {
        const backend = backendTargets.reduce((sum, name) => sum + sample.targets[name], 0);
        const browser = sample.targets[config.BROWSER_TARGET] ?? 0;

        const co2 = {
            backend: computeCO2(backend, config.WH_PER_INSTRUCTION),
            browser: computeCO2(browser, config.WH_PER_INSTRUCTION_CLIENT),
            network: (sample.bytes / 1e9) * config.KG_CO2E_PER_GB,
        };
        co2.total = co2.backend + co2.browser + co2.network;

        return {
            durationMs: sample.durationMs,
            bytes: sample.bytes,
            instructions: {backend, browser, perTarget: sample.targets},
            co2,
        };
    });

    const totals = samples.map((sample) => sample.co2.total);
    const medianTotal = median(totals);

    return {
        name: page.name,
        path: page.path,
        samples: page.samples.length,
        idle: page.idle,
        idleRatePerSecond: targetNames.reduce((sum, name) => sum + idleRatePerSecond[name], 0),
        idleRatePerTarget: idleRatePerSecond,
        medianDurationMs: median(samples.map((sample) => sample.durationMs)),
        medianBytes: median(samples.map((sample) => sample.bytes)),
        medianInstructions: {
            backend: median(samples.map((sample) => sample.instructions.backend)),
            browser: median(samples.map((sample) => sample.instructions.browser)),
        },
        co2: {
            backend: median(samples.map((sample) => sample.co2.backend)),
            browser: median(samples.map((sample) => sample.co2.browser)),
            network: median(samples.map((sample) => sample.co2.network)),
            total: medianTotal,
        },
        // Full range, max to min, as a fraction of the median. Deliberately the worst case rather
        // than an IQR: it never claims the measurement is tighter than its worst sample.
        spreadRatio: medianTotal === 0 ? 0 : (Math.max(...totals) - Math.min(...totals)) / medianTotal,
        measuredSamples: samples,
    };
}

const HEADERS = ["page", "backend", "browser", "network", "total", "spread", "idle floor"];

function row(page, label) {
    return [
        label,
        mg(page.co2.backend),
        mg(page.co2.browser),
        mg(page.co2.network),
        mg(page.co2.total),
        `${(page.spreadRatio * 100).toFixed(1)}%`,
        `${si(page.idleRatePerSecond)}/s`,
    ];
}

class EnergyReporter {
    constructor() {
        this.pages = [];
    }

    printsToStdio() {
        return true;
    }

    onTestEnd(test, result) {
        for (const attachment of result.attachments) {
            if (attachment.name === "energy" && attachment.body) {
                this.pages.push(JSON.parse(attachment.body.toString("utf8")));
            }
        }
    }

    onEnd() {
        if (this.pages.length === 0) {
            console.log("\nNo energy samples were collected.");

            return;
        }

        const summaries = this.pages.map(summarise);
        const runId = new Date().toISOString().replace(/[:.]/g, "-");

        console.log(`\n${this.render(summaries)}`);

        fs.mkdirSync(OUTPUT_DIR, {recursive: true});

        const jsonPath = path.join(OUTPUT_DIR, `${runId}.json`);
        fs.writeFileSync(jsonPath, `${JSON.stringify(this.artifact(summaries, runId), null, 2)}\n`);

        const markdownPath = path.join(OUTPUT_DIR, `${runId}.md`);
        fs.writeFileSync(markdownPath, this.markdown(summaries, runId));

        console.log(`\n  ${path.relative(process.cwd(), jsonPath)}`);
        console.log(`  ${path.relative(process.cwd(), markdownPath)}\n`);
    }

    render(summaries) {
        const rows = summaries.map((page) => row(page, page.name));

        return [
            `Energy — mg CO2e per page view, median of ${config.SAMPLES} loads, `
                + `${config.WARMUP} warmup discarded, cold cache`,
            "",
            table(HEADERS, rows),
            "",
            this.assumptions().join("\n"),
        ].join("\n");
    }

    assumptions() {
        return [
            `  backend  ${config.WH_PER_INSTRUCTION.toExponential(2)} Wh/instruction (server)`,
            `  browser  ${config.WH_PER_INSTRUCTION_CLIENT.toExponential(2)} Wh/instruction (visitor device)`,
            `  grid     ${config.KG_CO2E_PER_KWH} kg CO2e/kWh`,
            `  network  ${config.KG_CO2E_PER_GB.toExponential(2)} kg CO2e/GB (ADEME)`,
        ];
    }

    artifact(summaries, runId) {
        const perPage = (fields) => summaries.map((page) => ({
            name: page.name,
            path: page.path,
            ...fields(page),
        }));

        return {
            schemaVersion: SCHEMA_VERSION,
            runId,
            generatedAt: new Date().toISOString(),
            config: {
                whPerInstruction: config.WH_PER_INSTRUCTION,
                whPerInstructionClient: config.WH_PER_INSTRUCTION_CLIENT,
                kgCO2ePerKwh: config.KG_CO2E_PER_KWH,
                kgCO2ePerGb: config.KG_CO2E_PER_GB,
                samples: config.SAMPLES,
                warmup: config.WARMUP,
                idleMs: config.IDLE_MS,
                baseUrl: config.BASE_URL,
            },
            layers: {
                backend: {
                    targets: config.BACKEND_TARGETS,
                    pages: perPage((page) => ({
                        instructions: page.medianInstructions.backend,
                        kgCO2e: page.co2.backend,
                    })),
                },
                frontend: {
                    target: config.BROWSER_TARGET,
                    pages: perPage((page) => ({
                        instructions: page.medianInstructions.browser,
                        kgCO2e: page.co2.browser,
                    })),
                },
                network: {
                    source: "CDP Network.loadingFinished.encodedDataLength",
                    pages: perPage((page) => ({
                        bytes: page.medianBytes,
                        kgCO2e: page.co2.network,
                    })),
                },
            },
            pages: summaries,
        };
    }

    markdown(summaries, runId) {
        const rows = summaries.map((page) => row(page, `\`${page.path}\``));

        return [
            "# Energy",
            "",
            `Run \`${runId}\` — mg CO2e per page view. Median of ${config.SAMPLES} full page loads, `
                + `${config.WARMUP} warmup load discarded, cold cache every sample. Spread is the full `
                + "max-to-min range. The idle floor is measured and reported, never subtracted.",
            "",
            `| ${HEADERS.join(" | ")} |`,
            `| ${HEADERS.map(() => "---").join(" | ")} |`,
            ...rows.map((cells) => `| ${cells.join(" | ")} |`),
            "",
            "## Model",
            "",
            "Compute is priced instructions → Wh → CO2; network goes straight to CO2 from ADEME's",
            "per-GB factor. Not the reverse — that factor embeds embodied carbon rather than",
            "electricity alone, so dividing it by a grid intensity would not recover Wh.",
            "",
            ...this.assumptions().map((line) => `-${line.slice(1)}`),
            "",
            "Network counts every byte the browser fetched, third-party included. On the homepage",
            "that is mostly the Instagram and Facebook embeds, not assets this site serves.",
            "",
            "Instruction counts come from the php, database and browser container cgroups via",
            "`perf_event_open(2)`. They are ISA-dependent: an x86 container standing in for an ARM",
            "server or an ARM phone is an approximation, though a far steadier one than cycles or",
            "wall time. The browser is a headless shell, so screen, GPU compositing and radio are",
            "not counted and a real device costs more.",
            "",
        ].join("\n");
    }
}

module.exports = EnergyReporter;
