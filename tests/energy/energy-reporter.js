const fs = require("node:fs");
const path = require("node:path");

const config = require("./energy.config.js");

const OUTPUT_DIR = path.join(process.cwd(), "var", "energy");
const SCHEMA_VERSION = 1;

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

function table(headers, rows) {
    const widths = headers.map((header, column) =>
        Math.max(header.length, ...rows.map((row) => String(row[column]).length)));
    const line = (cells) => cells
        .map((cell, column) => (column === 0 ? String(cell).padEnd(widths[column]) : String(cell).padStart(widths[column])))
        .join("  ");

    return [line(headers), widths.map((width) => "-".repeat(width)).join("  "), ...rows.map(line)].join("\n");
}

/**
 * Turns the raw per-sample deltas into the numbers a human reads: median, and the energy that
 * follows from it.
 *
 * The idle floor is measured and reported but never subtracted. It was worth subtracting when the
 * dev stack's FrankenPHP file watcher burned 94 M instr/s; on the prod-like stack php idles at
 * exactly zero and the database at ~0.02% of a sample. What survived of the correction was a burst
 * caught in the 2s window being extrapolated as a steady rate, which understated /contact by 7.5%.
 * Reporting it still earns its place: a background job that ever does start mattering shows up here
 * rather than silently inflating every page.
 */
function summarise(page) {
    const targetNames = Object.keys(page.idle.targets);

    const idleRatePerSecond = {};
    for (const name of targetNames) {
        idleRatePerSecond[name] = (page.idle.targets[name] / page.idle.durationMs) * 1000;
    }

    const samples = page.samples.map((sample) => ({
        durationMs: sample.durationMs,
        targets: sample.targets,
        total: targetNames.reduce((sum, name) => sum + sample.targets[name], 0),
    }));

    const totals = samples.map((sample) => sample.total);
    const medianTotal = median(totals);

    const perTarget = {};
    for (const name of targetNames) {
        perTarget[name] = {
            instructions: median(samples.map((sample) => sample.targets[name])),
            idleRatePerSecond: idleRatePerSecond[name],
        };
    }

    return {
        name: page.name,
        path: page.path,
        samples: page.samples.length,
        idle: page.idle,
        perTarget,
        medianTotalInstructions: medianTotal,
        medianDurationMs: median(samples.map((sample) => sample.durationMs)),
        wh: medianTotal * config.WH_PER_INSTRUCTION,
        idleRatePerSecond: targetNames.reduce((sum, name) => sum + idleRatePerSecond[name], 0),
        // Full range, max to min, as a fraction of the median. Deliberately the worst case rather
        // than an IQR: it never claims the measurement is tighter than its worst sample.
        spreadRatio: medianTotal === 0 ? 0 : (Math.max(...totals) - Math.min(...totals)) / medianTotal,
        measuredSamples: samples,
    };
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
        const targetNames = Object.keys(summaries[0].perTarget);
        const runId = new Date().toISOString().replace(/[:.]/g, "-");

        console.log(`\n${this.render(summaries, targetNames)}`);

        fs.mkdirSync(OUTPUT_DIR, {recursive: true});

        const jsonPath = path.join(OUTPUT_DIR, `${runId}.json`);
        fs.writeFileSync(jsonPath, `${JSON.stringify(this.artifact(summaries, targetNames, runId), null, 2)}\n`);

        const markdownPath = path.join(OUTPUT_DIR, `${runId}.md`);
        fs.writeFileSync(markdownPath, this.markdown(summaries, targetNames, runId));

        console.log(`\n  ${path.relative(process.cwd(), jsonPath)}`);
        console.log(`  ${path.relative(process.cwd(), markdownPath)}\n`);
    }

    render(summaries, targetNames) {
        const headers = ["page", ...targetNames.map((name) => `${name} instr`), "total instr", "Wh", "spread", "idle floor"];
        const rows = summaries.map((page) => [
            page.name,
            ...targetNames.map((name) => si(page.perTarget[name].instructions)),
            si(page.medianTotalInstructions),
            page.wh.toExponential(2),
            `${(page.spreadRatio * 100).toFixed(1)}%`,
            `${si(page.idleRatePerSecond)}/s`,
        ]);

        return [
            `Backend energy — median of ${config.SAMPLES} samples per page, ${config.WARMUP} warmup load discarded`,
            "",
            table(headers, rows),
            "",
            `Wh per instruction: ${config.WH_PER_INSTRUCTION.toExponential(2)} (estimate — override with ENERGY_WH_PER_INSTRUCTION)`,
        ].join("\n");
    }

    artifact(summaries, targetNames, runId) {
        return {
            schemaVersion: SCHEMA_VERSION,
            runId,
            generatedAt: new Date().toISOString(),
            config: {
                whPerInstruction: config.WH_PER_INSTRUCTION,
                samples: config.SAMPLES,
                warmup: config.WARMUP,
                idleMs: config.IDLE_MS,
                baseUrl: config.BASE_URL,
            },
            // Keyed by layer so the frontend, network and API phases land beside this one instead of
            // forcing a schema migration.
            layers: {
                backend: {
                    targets: targetNames,
                    pages: summaries,
                },
            },
        };
    }

    markdown(summaries, targetNames, runId) {
        const headers = ["page", ...targetNames.map((name) => `${name} instr`), "total instr", "Wh", "spread", "idle floor"];
        const rows = summaries.map((page) => [
            `\`${page.path}\``,
            ...targetNames.map((name) => si(page.perTarget[name].instructions)),
            si(page.medianTotalInstructions),
            page.wh.toExponential(2),
            `${(page.spreadRatio * 100).toFixed(1)}%`,
            `${si(page.idleRatePerSecond)}/s`,
        ]);

        return [
            "# Backend energy",
            "",
            `Run \`${runId}\` — median of ${config.SAMPLES} full page loads per page, `
                + `${config.WARMUP} warmup load discarded. Spread is the full max-to-min range. The idle `
                + "floor is measured and reported, never subtracted.",
            "",
            `| ${headers.join(" | ")} |`,
            `| ${headers.map(() => "---").join(" | ")} |`,
            ...rows.map((row) => `| ${row.join(" | ")} |`),
            "",
            "## Model",
            "",
            `Energy is \`instructions x ${config.WH_PER_INSTRUCTION.toExponential(2)} Wh\`. That constant is an `
                + "estimate for Ampere Altra-class hardware, not a measurement: comparisons between pages and "
                + "between runs are meaningful, the absolute Wh is only as good as the constant.",
            "",
            `Instructions are retired-instruction counts read from the ${targetNames.join(" and ")} container `
                + "cgroups via `perf_event_open(2)`, summed across every PMU on the host.",
            "",
        ].join("\n");
    }
}

module.exports = EnergyReporter;
