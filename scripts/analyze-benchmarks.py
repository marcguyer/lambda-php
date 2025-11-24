#!/usr/bin/env python3
"""
Analyze separate cold start and warm invocation benchmarks across multiple memory configurations
"""

import csv
import statistics
from pathlib import Path
import glob

def load_cold_start_data_multi():
    """Load cold start benchmark data for all memory configurations"""
    data_by_memory = {}

    for csv_file in glob.glob('cold_start_php-lambda-http_*mb.csv'):
        if not Path(csv_file).exists():
            continue

        data = []
        with open(csv_file, 'r') as f:
            reader = csv.DictReader(f)
            for row in reader:
                data.append({
                    'memory': int(row['Memory']),
                    'billed_ms': float(row['BilledMs']),
                    'init_ms': float(row['InitMs']),
                    'memory_used': float(row['MemoryUsedMB']),
                })
        if data:
            memory = data[0]['memory']
            data_by_memory[memory] = data

    return data_by_memory if data_by_memory else None

def load_warm_data_multi():
    """Load warm invocation benchmark data for all memory configurations"""
    data_by_memory = {}

    for csv_file in glob.glob('warm_php-lambda-http_*mb.csv'):
        if not Path(csv_file).exists():
            continue

        data = []
        with open(csv_file, 'r') as f:
            reader = csv.DictReader(f)
            for row in reader:
                data.append({
                    'memory': int(row['Memory']),
                    'duration_ms': float(row['DurationMs']),
                    'billed_ms': float(row['BilledMs']),
                    'memory_used': float(row['MemoryUsedMB']),
                })
        if data:
            memory = data[0]['memory']
            data_by_memory[memory] = data

    return data_by_memory if data_by_memory else None

def calculate_cost(memory_mb, duration_ms):
    """Calculate cost for ARM64 Lambda invocation"""
    # $0.0000133334 per GB-second (ARM64)
    gb_seconds = (memory_mb / 1024) * (duration_ms / 1000)
    return gb_seconds * 0.0000133334

def analyze_cold_starts(data):
    """Analyze cold start performance"""
    if not data:
        return None

    memory = data[0]['memory']
    billed_times = [d['billed_ms'] for d in data]
    init_times = [d['init_ms'] for d in data]
    memory_used = [d['memory_used'] for d in data]

    avg_billed = statistics.mean(billed_times)
    median_billed = statistics.median(billed_times)
    stdev_billed = statistics.stdev(billed_times) if len(billed_times) > 1 else 0

    avg_init = statistics.mean(init_times)
    median_init = statistics.median(init_times)

    avg_memory = statistics.mean(memory_used)
    avg_cost = calculate_cost(memory, avg_billed)

    return {
        'memory': memory,
        'samples': len(data),
        'avg_billed_ms': avg_billed,
        'median_billed_ms': median_billed,
        'stdev_billed_ms': stdev_billed,
        'avg_init_ms': avg_init,
        'median_init_ms': median_init,
        'avg_memory_used': avg_memory,
        'avg_cost': avg_cost,
        'cost_per_million': avg_cost * 1000000,
    }

def analyze_warm_invocations(data):
    """Analyze warm invocation performance"""
    if not data:
        return None

    memory = data[0]['memory']
    billed_times = [d['billed_ms'] for d in data]
    memory_used = [d['memory_used'] for d in data]

    avg_billed = statistics.mean(billed_times)
    median_billed = statistics.median(billed_times)
    stdev_billed = statistics.stdev(billed_times) if len(billed_times) > 1 else 0
    p95_billed = statistics.quantiles(billed_times, n=20)[18] if len(billed_times) >= 20 else max(billed_times)
    p99_billed = statistics.quantiles(billed_times, n=100)[98] if len(billed_times) >= 100 else max(billed_times)

    avg_memory = statistics.mean(memory_used)
    avg_cost = calculate_cost(memory, avg_billed)

    return {
        'memory': memory,
        'samples': len(data),
        'avg_billed_ms': avg_billed,
        'median_billed_ms': median_billed,
        'stdev_billed_ms': stdev_billed,
        'p95_billed_ms': p95_billed,
        'p99_billed_ms': p99_billed,
        'avg_memory_used': avg_memory,
        'avg_cost': avg_cost,
        'cost_per_million': avg_cost * 1000000,
    }

def main():
    print("\n# PHP 8.4 Lambda Performance Analysis\n")

    # Analyze warm invocations
    warm_data_multi = load_warm_data_multi()
    if warm_data_multi:
        print("## Warm Invocation Performance (Pure CPU)\n")
        print("| Memory | Avg | Median | P95 | P99 | Min | Max | Cost/1M |")
        print("|--------|-----|--------|-----|-----|-----|-----|---------|")

        warm_stats_by_mem = {}
        for memory in sorted(warm_data_multi.keys()):
            stats = analyze_warm_invocations(warm_data_multi[memory])
            warm_stats_by_mem[memory] = stats
            print(f"| {memory}MB | {stats['avg_billed_ms']:.1f}ms | {stats['median_billed_ms']:.0f}ms | "
                  f"{stats['p95_billed_ms']:.0f}ms | {stats['p99_billed_ms']:.0f}ms | "
                  f"{min([d['duration_ms'] for d in warm_data_multi[memory]]):.0f}ms | "
                  f"{max([d['duration_ms'] for d in warm_data_multi[memory]]):.0f}ms | "
                  f"${stats['cost_per_million']:.4f} |")
    else:
        print("## Warm Invocation Performance\n")
        print("No warm data available. Run `./scripts/benchmark-warm.sh` first.")
        warm_stats_by_mem = {}

    print()

    # Analyze cold starts
    cold_data_multi = load_cold_start_data_multi()
    if cold_data_multi:
        print("## Cold Start Performance (Init + CPU, includes 100ms I/O)\n")
        print("| Memory | Avg Total | Avg Init | Median | Min | Max | Cost/1M |")
        print("|--------|-----------|----------|--------|-----|-----|---------|")

        cold_stats_by_mem = {}
        for memory in sorted(cold_data_multi.keys()):
            stats = analyze_cold_starts(cold_data_multi[memory])
            cold_stats_by_mem[memory] = stats
            print(f"| {memory}MB | {stats['avg_billed_ms']:.1f}ms | {stats['avg_init_ms']:.1f}ms | "
                  f"{stats['median_billed_ms']:.0f}ms | "
                  f"{min([d['billed_ms'] for d in cold_data_multi[memory]]):.0f}ms | "
                  f"{max([d['billed_ms'] for d in cold_data_multi[memory]]):.0f}ms | "
                  f"${stats['cost_per_million']:.4f} |")
        print("\nNote: Cold start times include 100ms simulated I/O. Subtract 100ms for pure init+CPU time.")
    else:
        print("## Cold Start Performance\n")
        print("No cold start data available. Run `./scripts/benchmark-cold-start.sh` first.")
        cold_stats_by_mem = {}

    # Recommendations
    if warm_stats_by_mem:
        print("\n## Recommendations\n")

        # Find best performance and best cost
        best_perf_mem = min(warm_stats_by_mem.keys(), key=lambda k: warm_stats_by_mem[k]['avg_billed_ms'])
        best_cost_mem = min(warm_stats_by_mem.keys(), key=lambda k: warm_stats_by_mem[k]['cost_per_million'])

        print(f"**Best Performance:** {best_perf_mem}MB ({warm_stats_by_mem[best_perf_mem]['avg_billed_ms']:.1f}ms avg)")
        print(f"**Best Cost:** {best_cost_mem}MB (${warm_stats_by_mem[best_cost_mem]['cost_per_million']:.4f} per 1M requests)")

        print("\n**Key Insights:**")
        print("- Warm performance dominates cost for high-throughput workloads")
        print("- Cold starts are rare when running thousands of requests per container")
        print("- CPU-bound workloads benefit from higher memory (more vCPU)")

        # Compare 256MB vs others
        if 256 in warm_stats_by_mem:
            print(f"\n**256MB Analysis:**")
            print(f"- Avg warm: {warm_stats_by_mem[256]['avg_billed_ms']:.1f}ms")
            print(f"- Cost: ${warm_stats_by_mem[256]['cost_per_million']:.4f} per 1M requests")
            if 128 in warm_stats_by_mem:
                speedup = warm_stats_by_mem[128]['avg_billed_ms'] / warm_stats_by_mem[256]['avg_billed_ms']
                cost_diff = warm_stats_by_mem[256]['cost_per_million'] - warm_stats_by_mem[128]['cost_per_million']
                print(f"- {speedup:.1f}x faster than 128MB, +${cost_diff:.4f} per 1M requests")

if __name__ == '__main__':
    main()