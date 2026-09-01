// Command energy-probe reports retired CPU instructions per container cgroup over HTTP, so a test
// run can price a page load in instructions rather than in seconds. Instructions are the only
// figure comparable across machines: identical work retires the same count on a P-core and an
// E-core, but takes ~45% more cycles on the E-core.
//
// Counters are opened once and left free-running. Callers GET /counters either side of the work.
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
	"time"
	"unsafe"

	"golang.org/x/sys/unix"
)

const (
	cgroupRoot  = "/sys/fs/cgroup"
	pmuRoot     = "/sys/bus/event_source/devices"
	onlineCPUs  = "/sys/devices/system/cpu/online"
	defaultAddr = "127.0.0.1:9777"
	httpTimeout = 5 * time.Second
)

// A hybrid Intel part exposes cpu_core and cpu_atom and no plain "cpu"; uniform hosts, including
// the ARM ones this models, expose only "cpu". Absent names are skipped.
var pmuNames = []string{"cpu", "cpu_core", "cpu_atom"}

type pmu struct {
	Name string `json:"name"`
	Type uint32 `json:"type"`
	CPUs []int  `json:"cpus"`

	config uint64
}

type target struct {
	Name       string `json:"name"`
	Slice      string `json:"slice"`
	CgroupPath string `json:"cgroupPath"`

	fds []int
}

type probe struct {
	pmus    []pmu
	targets []*target
}

func main() {
	healthcheck := flag.Bool("healthcheck", false, "check this container's own /healthz and exit")
	flag.Parse()

	addr := defaultAddr
	if fromEnv := os.Getenv("PROBE_ADDR"); fromEnv != "" {
		addr = fromEnv
	}

	if *healthcheck {
		if err := selfCheck(addr); err != nil {
			log.Fatalf("energy-probe: %v", err)
		}

		return
	}

	if err := serve(addr, os.Getenv("TARGETS")); err != nil {
		log.Fatalf("energy-probe: %v", err)
	}
}

func serve(addr, targetsSpec string) error {
	p, err := newProbe(targetsSpec)
	if err != nil {
		return err
	}

	for _, pm := range p.pmus {
		log.Printf("pmu %s (type %d) cpus %v", pm.Name, pm.Type, pm.CPUs)
	}
	for _, t := range p.targets {
		log.Printf("target %s -> %s (%d counters)", t.Name, t.CgroupPath, len(t.fds))
	}

	mux := http.NewServeMux()
	mux.HandleFunc("/healthz", p.handleHealth)
	mux.HandleFunc("/counters", p.handleCounters)

	log.Printf("listening on %s", addr)

	return (&http.Server{Addr: addr, Handler: mux, ReadHeaderTimeout: httpTimeout}).ListenAndServe()
}

// Every failure here is fatal on purpose: a probe missing one PMU still returns a plausible number.
func newProbe(targetsSpec string) (*probe, error) {
	pmus, err := discoverPMUs()
	if err != nil {
		return nil, err
	}

	targets, err := parseTargets(targetsSpec)
	if err != nil {
		return nil, err
	}

	for _, t := range targets {
		if t.CgroupPath, err = findCgroup(t.Slice); err != nil {
			return nil, fmt.Errorf("target %s: %w", t.Name, err)
		}
		if t.fds, err = openCounters(t.CgroupPath, pmus); err != nil {
			return nil, fmt.Errorf("target %s: %w", t.Name, err)
		}
	}

	return &probe{pmus: pmus, targets: targets}, nil
}

// parseTargets reads TARGETS, "name=cgroupParent,name=cgroupParent". Values are the cgroup_parent
// each container is pinned to, not container ids: an id exists only once the container does, which
// would force this to start in a second pass.
func parseTargets(spec string) ([]*target, error) {
	var targets []*target

	for _, pair := range strings.Split(spec, ",") {
		if pair = strings.TrimSpace(pair); pair == "" {
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

	for _, name := range pmuNames {
		typ, err := readUint(filepath.Join(pmuRoot, name, "type"))
		if errors.Is(err, fs.ErrNotExist) {
			continue
		}
		if err != nil {
			return nil, fmt.Errorf("pmu %s: %w", name, err)
		}

		// Only a hybrid PMU publishes the CPUs it owns; a uniform one owns every online CPU.
		cpus, err := readCPUList(filepath.Join(pmuRoot, name, "cpus"))
		if err != nil {
			if cpus, err = readCPUList(onlineCPUs); err != nil {
				return nil, fmt.Errorf("pmu %s: %w", name, err)
			}
		}

		// The PMU goes in config's high bits, never in attr.Type: the core PMU registers as type 4,
		// which is PERF_TYPE_RAW, so attr.Type=4 would silently count raw event 0x1 instead.
		config := uint64(unix.PERF_COUNT_HW_INSTRUCTIONS)
		if name != "cpu" {
			config |= uint64(typ) << 32
		}

		pmus = append(pmus, pmu{Name: name, Type: uint32(typ), CPUs: cpus, config: config})
	}

	if len(pmus) == 0 {
		return nil, fmt.Errorf("no hardware PMU found under %s", pmuRoot)
	}

	return pmus, nil
}

// findCgroup locates a pinned cgroup_parent by name. Searched rather than built into a path: the
// two cgroup drivers spell it differently, and systemd reads a dash as hierarchy, so
// "cvvfcm-php.slice" lands under /sys/fs/cgroup/cvvfcm.slice/.
func findCgroup(slice string) (string, error) {
	var found string

	err := filepath.WalkDir(cgroupRoot, func(path string, entry fs.DirEntry, err error) error {
		switch {
		case err != nil: // an unreadable subtree is not a reason to stop looking
			return nil
		case !entry.IsDir() || path == cgroupRoot:
			return nil
		// Exact: a prefix match would let "cvvfcm-php" accept "cvvfcm-php-worker".
		case entry.Name() == slice, entry.Name() == slice+".slice":
			found = path

			return fs.SkipAll
		}

		return nil
	})
	if err != nil {
		return "", err
	}
	if found == "" {
		return "", fmt.Errorf("no cgroup %q or %q under %s — is cgroup_parent set on that service?",
			slice, slice+".slice", cgroupRoot)
	}

	return found, nil
}

// openCounters opens one instruction counter per (PMU, CPU). PERF_FLAG_PID_CGROUP rejects cpu == -1,
// which is why this is per-CPU rather than one descriptor per PMU.
func openCounters(cgroupPath string, pmus []pmu) ([]int, error) {
	cgroupFd, err := unix.Open(cgroupPath, unix.O_RDONLY|unix.O_CLOEXEC, 0)
	if err != nil {
		return nil, fmt.Errorf("open cgroup %s: %w", cgroupPath, err)
	}
	defer unix.Close(cgroupFd) // perf takes its own reference on the cgroup

	var fds []int
	for _, pm := range pmus {
		for _, cpu := range pm.CPUs {
			attr := &unix.PerfEventAttr{
				Type:   unix.PERF_TYPE_HARDWARE,
				Size:   uint32(unsafe.Sizeof(unix.PerfEventAttr{})),
				Config: pm.config,
				// Lets a multiplexed counter be scaled instead of silently undercounting.
				Read_format: unix.PERF_FORMAT_TOTAL_TIME_ENABLED | unix.PERF_FORMAT_TOTAL_TIME_RUNNING,
			}

			fd, err := unix.PerfEventOpen(attr, cgroupFd, cpu, -1, unix.PERF_FLAG_PID_CGROUP|unix.PERF_FLAG_FD_CLOEXEC)
			if err != nil {
				return nil, fmt.Errorf("perf_event_open %s cpu=%d: %w "+
					"(needs CAP_SYS_ADMIN: at kernel.perf_event_paranoid=3, CAP_PERFMON is not enough)",
					pm.Name, cpu, err)
			}

			fds = append(fds, fd)
		}
	}

	return fds, nil
}

// Both halves of a hybrid CPU are summed because each PMU is blind to the CPUs it does not own.
func (t *target) read() (uint64, error) {
	var total uint64

	for i, fd := range t.fds {
		value, err := readCounter(fd)
		if err != nil {
			return 0, fmt.Errorf("counter %d: %w", i, err)
		}

		total += value
	}

	return total, nil
}

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
	case running < enabled:
		return uint64(float64(value) * float64(enabled) / float64(running)), nil
	default:
		return value, nil
	}
}

// State is read-only once newProbe returns and a perf read is an independent snapshot, so the
// handlers need no locking.
func (p *probe) handleHealth(w http.ResponseWriter, _ *http.Request) {
	counters := 0
	for _, t := range p.targets {
		counters += len(t.fds)
	}

	writeJSON(w, http.StatusOK, map[string]any{
		"status":   "ok",
		"pmus":     p.pmus,
		"targets":  p.targets,
		"counters": counters,
	})
}

// Flat name -> retired instructions. Callers read this either side of the work and subtract.
func (p *probe) handleCounters(w http.ResponseWriter, _ *http.Request) {
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

// selfCheck backs the container healthcheck, so the address lives here rather than in a Dockerfile
// curl invocation.
func selfCheck(addr string) error {
	client := &http.Client{Timeout: httpTimeout}

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

func readUint(path string) (uint64, error) {
	raw, err := os.ReadFile(path)
	if err != nil {
		return 0, err
	}

	return strconv.ParseUint(strings.TrimSpace(string(raw)), 10, 32)
}

// readCPUList parses the kernel's range syntax: "4", "0-3", "0-3,8-11".
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
