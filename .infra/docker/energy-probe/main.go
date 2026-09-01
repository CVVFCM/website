// Command energy-probe counts retired CPU instructions per container, so a Playwright run can price
// a page load in instructions rather than in wall time.
//
// Wall time and CPU time are not portable: on a hybrid x86 laptop the same work reports anywhere
// between 3.4 and 5.6 G instructions per CPU-second depending on whether it landed on a P-core or an
// E-core and where turbo was. Instructions retired is the invariant, and it is what production on
// ARM can be compared against.
//
// Counting is scoped to a cgroup via perf_event_open(2) with PERF_FLAG_PID_CGROUP. In that mode the
// kernel refuses cpu == -1, so there is one file descriptor per (PMU, CPU) pair per container.
// Counters are opened once at startup and left free-running; callers read /counters before and after
// the work and subtract.
package main

import (
	"encoding/binary"
	"encoding/json"
	"errors"
	"flag"
	"fmt"
	"io/fs"
	"log"
	"net/http"
	"os"
	"path/filepath"
	"strconv"
	"strings"
	"sync"
	"time"
	"unsafe"

	"golang.org/x/sys/unix"
)

const (
	cgroupRoot  = "/sys/fs/cgroup"
	pmuRoot     = "/sys/bus/event_source/devices"
	onlineCPUs  = "/sys/devices/system/cpu/online"
	readTimeout = 5 * time.Second
)

// candidatePMUs is ordered widest-first. A hybrid Intel part exposes cpu_core and cpu_atom and no
// plain "cpu"; everything else — including the ARM hosts this is meant to model — exposes only
// "cpu". Missing entries are skipped, so the same binary covers both.
var candidatePMUs = []string{"cpu", "cpu_core", "cpu_atom"}

type pmu struct {
	Name string `json:"name"`
	Type uint32 `json:"type"`
	CPUs []int  `json:"cpus"`
	// Config is always requested as PERF_TYPE_HARDWARE. Passing the PMU's own sysfs type is a trap:
	// the core PMU registers as type 4, which is PERF_TYPE_RAW, so type=4 config=1 silently asks for
	// raw event 0x1 instead of instructions and returns a valid descriptor that counts zero. The
	// hybrid extension (kernel >= 5.13) puts the PMU type in bits 63:32 of config instead.
	Config uint64 `json:"config"`
}

type counter struct {
	fd  int
	pmu string
	cpu int
}

type target struct {
	Name       string `json:"name"`
	Slice      string `json:"slice"`
	CgroupPath string `json:"cgroupPath"`

	counters []counter
}

type probe struct {
	mu      sync.Mutex
	pmus    []pmu
	targets []*target
}

func main() {
	healthcheck := flag.Bool("healthcheck", false, "probe this container's own /healthz and exit")
	flag.Parse()

	addr := envOr("PROBE_ADDR", "127.0.0.1:9777")

	if *healthcheck {
		if err := selfCheck(addr); err != nil {
			fmt.Fprintln(os.Stderr, err)
			os.Exit(1)
		}
		return
	}

	p, err := newProbe(os.Getenv("TARGETS"))
	if err != nil {
		log.Fatalf("energy-probe: %v", err)
	}

	for _, pm := range p.pmus {
		log.Printf("pmu %s type=%d cpus=%v", pm.Name, pm.Type, pm.CPUs)
	}
	for _, t := range p.targets {
		log.Printf("target %s slice=%s cgroup=%s counters=%d", t.Name, t.Slice, t.CgroupPath, len(t.counters))
	}

	mux := http.NewServeMux()
	mux.HandleFunc("/healthz", p.handleHealth)
	mux.HandleFunc("/counters", p.handleCounters)

	log.Printf("listening on %s", addr)
	server := &http.Server{Addr: addr, Handler: mux, ReadHeaderTimeout: readTimeout}
	log.Fatal(server.ListenAndServe())
}

// newProbe resolves every target's cgroup and opens its counters. Any failure here is fatal by
// design: a probe that silently counts three of four PMUs reports a number that looks plausible and
// is wrong.
func newProbe(targetsSpec string) (*probe, error) {
	pmus, err := discoverPMUs()
	if err != nil {
		return nil, err
	}

	targets, err := parseTargets(targetsSpec)
	if err != nil {
		return nil, err
	}

	p := &probe{pmus: pmus, targets: targets}
	for _, t := range p.targets {
		path, err := findCgroup(t.Slice)
		if err != nil {
			return nil, fmt.Errorf("target %s: %w", t.Name, err)
		}
		t.CgroupPath = path

		if err := t.open(pmus); err != nil {
			return nil, fmt.Errorf("target %s: %w", t.Name, err)
		}
	}

	return p, nil
}

// parseTargets reads the TARGETS env, "name=cgroupParent,name=cgroupParent". The values are the
// `cgroup_parent` each container is pinned to in compose, not container ids: an id is only knowable
// after the container exists, which would force the probe to be started in a second pass.
func parseTargets(spec string) ([]*target, error) {
	var targets []*target
	for _, pair := range strings.Split(spec, ",") {
		pair = strings.TrimSpace(pair)
		if pair == "" {
			continue
		}

		name, slice, ok := strings.Cut(pair, "=")
		if !ok || name == "" || slice == "" {
			return nil, fmt.Errorf("malformed TARGETS entry %q, want name=cgroupParent", pair)
		}

		targets = append(targets, &target{Name: name, Slice: slice})
	}

	if len(targets) == 0 {
		return nil, errors.New("TARGETS is empty, nothing to measure")
	}

	return targets, nil
}

func discoverPMUs() ([]pmu, error) {
	var pmus []pmu
	for _, name := range candidatePMUs {
		dir := filepath.Join(pmuRoot, name)

		raw, err := os.ReadFile(filepath.Join(dir, "type"))
		if err != nil {
			continue
		}

		typ, err := strconv.ParseUint(strings.TrimSpace(string(raw)), 10, 32)
		if err != nil {
			return nil, fmt.Errorf("pmu %s: unreadable type: %w", name, err)
		}

		// Hybrid PMUs declare the CPUs they own. A uniform PMU does not, and owns all of them.
		cpus, err := readCPUList(filepath.Join(dir, "cpus"))
		if err != nil {
			if cpus, err = readCPUList(onlineCPUs); err != nil {
				return nil, fmt.Errorf("pmu %s: no cpu list: %w", name, err)
			}
		}

		config := uint64(unix.PERF_COUNT_HW_INSTRUCTIONS)
		if name != "cpu" {
			// Hybrid part: the PMU has to be named explicitly, or the kernel picks one half.
			config |= uint64(typ) << 32
		}

		pmus = append(pmus, pmu{Name: name, Type: uint32(typ), CPUs: cpus, Config: config})
	}

	if len(pmus) == 0 {
		return nil, fmt.Errorf("no hardware PMU found under %s", pmuRoot)
	}

	return pmus, nil
}

// readCPUList parses the kernel's range syntax, "0-3", "4", "0-3,8-11".
func readCPUList(path string) ([]int, error) {
	raw, err := os.ReadFile(path)
	if err != nil {
		return nil, err
	}

	var cpus []int
	for _, part := range strings.Split(strings.TrimSpace(string(raw)), ",") {
		if part == "" {
			continue
		}

		lo, hi, isRange := strings.Cut(part, "-")
		first, err := strconv.Atoi(lo)
		if err != nil {
			return nil, fmt.Errorf("bad cpu list %q: %w", raw, err)
		}

		last := first
		if isRange {
			if last, err = strconv.Atoi(hi); err != nil {
				return nil, fmt.Errorf("bad cpu list %q: %w", raw, err)
			}
		}

		for cpu := first; cpu <= last; cpu++ {
			cpus = append(cpus, cpu)
		}
	}

	if len(cpus) == 0 {
		return nil, fmt.Errorf("empty cpu list in %s", path)
	}

	return cpus, nil
}

// findCgroup locates a pinned cgroup parent by name. The probe runs with cgroupns=host, so the real
// paths are visible. The name is searched for rather than built into a path because the two cgroup
// drivers spell it differently and systemd nests further still: cgroup_parent "cvvfcm-php.slice"
// lands at /sys/fs/cgroup/cvvfcm.slice/cvvfcm-php.slice, since systemd reads the dash as hierarchy.
func findCgroup(slice string) (string, error) {
	var found string

	err := filepath.WalkDir(cgroupRoot, func(path string, entry fs.DirEntry, err error) error {
		if err != nil {
			return nil // an unreadable subtree is not a reason to stop looking
		}
		if !entry.IsDir() || path == cgroupRoot {
			return nil
		}
		// Exact match: a prefix match would make "cvvfcm-php" also accept "cvvfcm-php-worker".
		if entry.Name() == slice || entry.Name() == slice+".slice" {
			found = path
			return fs.SkipAll
		}

		return nil
	})
	if err != nil {
		return "", err
	}
	if found == "" {
		return "", fmt.Errorf("no cgroup named %q or %q under %s — is cgroup_parent set on that service?",
			slice, slice+".slice", cgroupRoot)
	}

	return found, nil
}

func (t *target) open(pmus []pmu) error {
	cgroupFd, err := unix.Open(t.CgroupPath, unix.O_RDONLY|unix.O_CLOEXEC, 0)
	if err != nil {
		return fmt.Errorf("open cgroup %s: %w", t.CgroupPath, err)
	}
	// Held for the process lifetime: perf_event_open takes the cgroup by descriptor, and closing it
	// would not detach the counters but would make reopening impossible.

	for _, pm := range pmus {
		for _, cpu := range pm.CPUs {
			attr := &unix.PerfEventAttr{
				Type:   unix.PERF_TYPE_HARDWARE,
				Size:   uint32(unsafe.Sizeof(unix.PerfEventAttr{})),
				Config: pm.Config,
				// Multiplexing is not expected — one event per PMU per CPU — but reading the
				// enabled/running pair makes a silent undercount impossible rather than invisible.
				Read_format: unix.PERF_FORMAT_TOTAL_TIME_ENABLED | unix.PERF_FORMAT_TOTAL_TIME_RUNNING,
			}

			fd, err := unix.PerfEventOpen(attr, cgroupFd, cpu, -1, unix.PERF_FLAG_PID_CGROUP|unix.PERF_FLAG_FD_CLOEXEC)
			if err != nil {
				return fmt.Errorf("perf_event_open pmu=%s cpu=%d: %w "+
					"(needs CAP_SYS_ADMIN: at kernel.perf_event_paranoid=3 CAP_PERFMON is not enough)",
					pm.Name, cpu, err)
			}

			t.counters = append(t.counters, counter{fd: fd, pmu: pm.Name, cpu: cpu})
		}
	}

	return nil
}

// read sums every counter for the target.
//
// The two halves of a hybrid CPU are summed because each PMU only counts on the CPUs it owns:
// cpu_core is blind to work on an E-core and cpu_atom is blind to work on a P-core. The retired
// count itself does not depend on which half ran the work — same ISA, same instructions, measured
// at 3,183,816,890 vs 3,184,527,945 for identical work, a difference of 0.02%. Only the cycles
// differ, by ~45%, which is exactly why this counts instructions and not time. Summing is about
// not losing whichever half the scheduler picked, not about the halves disagreeing.
func (t *target) read() (uint64, error) {
	var total uint64
	for _, c := range t.counters {
		value, err := readCounter(c.fd)
		if err != nil {
			return 0, fmt.Errorf("read pmu=%s cpu=%d: %w", c.pmu, c.cpu, err)
		}

		total += value
	}

	return total, nil
}

// readCounter returns one counter, scaled if the kernel had to multiplex it. Multiplexing is not
// expected here — one event per PMU per CPU never oversubscribes — but an unscaled read would
// undercount silently, which is the one failure mode worth paying three extra field reads to avoid.
func readCounter(fd int) (uint64, error) {
	// PERF_FORMAT_TOTAL_TIME_ENABLED|RUNNING without PERF_FORMAT_GROUP: value, enabled, running.
	var buf [24]byte

	n, err := unix.Read(fd, buf[:])
	if err != nil {
		return 0, err
	}
	if n != len(buf) {
		return 0, fmt.Errorf("short read: %d bytes", n)
	}

	value := binary.NativeEndian.Uint64(buf[0:8])
	enabled := binary.NativeEndian.Uint64(buf[8:16])
	running := binary.NativeEndian.Uint64(buf[16:24])

	switch {
	case running == 0:
		return 0, nil
	case enabled != running:
		return uint64(float64(value) * float64(enabled) / float64(running)), nil
	default:
		return value, nil
	}
}

func (p *probe) handleHealth(w http.ResponseWriter, _ *http.Request) {
	p.mu.Lock()
	defer p.mu.Unlock()

	counters := 0
	for _, t := range p.targets {
		counters += len(t.counters)
	}

	writeJSON(w, http.StatusOK, map[string]any{
		"status":   "ok",
		"pmus":     p.pmus,
		"targets":  p.targets,
		"counters": counters,
	})
}

func (p *probe) handleCounters(w http.ResponseWriter, _ *http.Request) {
	p.mu.Lock()
	defer p.mu.Unlock()

	// Flat name -> retired instructions. Callers read this before and after the work and subtract.
	readings := make(map[string]uint64, len(p.targets))
	for _, t := range p.targets {
		instructions, err := t.read()
		if err != nil {
			writeJSON(w, http.StatusInternalServerError, map[string]any{"error": err.Error()})
			return
		}
		readings[t.Name] = instructions
	}

	writeJSON(w, http.StatusOK, readings)
}

func writeJSON(w http.ResponseWriter, status int, payload any) {
	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(status)
	_ = json.NewEncoder(w).Encode(payload)
}

// selfCheck backs the container healthcheck: the binary already knows PROBE_ADDR, so it checks
// itself rather than the Dockerfile repeating the address in a curl invocation.
func selfCheck(addr string) error {
	client := &http.Client{Timeout: readTimeout}

	resp, err := client.Get("http://" + addr + "/healthz")
	if err != nil {
		return err
	}
	defer resp.Body.Close()

	if resp.StatusCode != http.StatusOK {
		return fmt.Errorf("healthz returned %s", resp.Status)
	}

	return nil
}

func envOr(key, fallback string) string {
	if value := os.Getenv(key); value != "" {
		return value
	}

	return fallback
}
