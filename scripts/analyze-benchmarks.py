#!/usr/bin/env python3
"""
Analyze separate cold start and warm invocation benchmarks
"""

import csv
import statistics
from pathlib import Path

def load_cold_start_data(csv_file='cold_start_results.csv'):
    """Load cold start benchmark data"""
    if not Path(csv_file).exists():
        return None

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
    return data

def load_warm_data(csv_file='warm_results.csv'):
    """Load warm invocation benchmark data"""
    if not Path(csv_file).exists():
        return None

    data = []
    with open(csv_file, 'r') as f:
        reader = csv.DictReader(f)
        for row in reader:
            data.append({
                'memory': int(row['Memory']),
                'billed_ms': float(row['BilledMs']),
                'memory_used': float(row['MemoryUsedMB']),
            })
    return data

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

    # Analyze cold starts
    cold_data = load_cold_start_data()
    if cold_data:
        cold_stats = analyze_cold_starts(cold_data)

        print("## Cold Start Performance\n")
        print(f"Memory: {cold_stats['memory']}MB")
        print(f"Samples: {cold_stats['samples']}")
        print(f"Average total duration: {cold_stats['avg_billed_ms']:.1f}ms")
        print(f"Median total duration: {cold_stats['median_billed_ms']:.1f}ms")
        print(f"Standard deviation: {cold_stats['stdev_billed_ms']:.1f}ms")
        print(f"Average init time: {cold_stats['avg_init_ms']:.1f}ms")
        print(f"Median init time: {cold_stats['median_init_ms']:.1f}ms")
        print(f"Average memory used: {cold_stats['avg_memory_used']:.0f}MB")
        print(f"Average cost per cold start: ${cold_stats['avg_cost']:.8f}")
        print(f"Cost per 1M cold starts: ${cold_stats['cost_per_million']:.2f}")
    else:
        print("## Cold Start Performance\n")
        print("No cold start data available. Run `./scripts/benchmark-cold-start.sh` first.")

    print()

    # Analyze warm invocations
    warm_data = load_warm_data()
    if warm_data:
        warm_stats = analyze_warm_invocations(warm_data)

        print("## Warm Invocation Performance\n")
        print(f"Memory: {warm_stats['memory']}MB")
        print(f"Samples: {warm_stats['samples']}")
        print(f"Average duration: {warm_stats['avg_billed_ms']:.1f}ms")
        print(f"Median duration: {warm_stats['median_billed_ms']:.1f}ms")
        print(f"Standard deviation: {warm_stats['stdev_billed_ms']:.2f}ms")
        print(f"P95 duration: {warm_stats['p95_billed_ms']:.1f}ms")
        print(f"P99 duration: {warm_stats['p99_billed_ms']:.1f}ms")
        print(f"Average memory used: {warm_stats['avg_memory_used']:.0f}MB")
        print(f"Average cost per invocation: ${warm_stats['avg_cost']:.8f}")
        print(f"Cost per 1M requests: ${warm_stats['cost_per_million']:.2f}")
    else:
        print("## Warm Invocation Performance\n")
        print("No warm invocation data available. Run `./scripts/benchmark-warm.sh` first.")

    # Combined analysis
    if cold_data and warm_data:
        print("\n## Combined Analysis\n")

        # Calculate blended cost for different cold start percentages
        print("### Blended Cost by Cold Start Percentage\n")
        print("| Cold Start % | Avg Duration | Cost/Request | Cost/1M Requests |")
        print("|--------------|--------------|--------------|------------------|")

        for cold_pct in [0.1, 0.5, 1, 5, 10]:
            warm_pct = 100 - cold_pct
            blended_duration = (cold_stats['avg_billed_ms'] * cold_pct / 100) + \
                             (warm_stats['avg_billed_ms'] * warm_pct / 100)
            blended_cost = (cold_stats['avg_cost'] * cold_pct / 100) + \
                          (warm_stats['avg_cost'] * warm_pct / 100)
            blended_cost_per_million = blended_cost * 1000000

            print(f"| {cold_pct:.1f}% | {blended_duration:.1f}ms | "
                  f"${blended_cost:.8f} | ${blended_cost_per_million:.2f} |")

        print(f"\n### Efficiency Metrics\n")
        print(f"Cold start overhead: {cold_stats['avg_billed_ms'] - warm_stats['avg_billed_ms']:.1f}ms "
              f"({((cold_stats['avg_billed_ms'] / warm_stats['avg_billed_ms'] - 1) * 100):.0f}% slower)")
        print(f"Init time as % of cold start: {(cold_stats['avg_init_ms'] / cold_stats['avg_billed_ms'] * 100):.1f}%")

if __name__ == '__main__':
    main()