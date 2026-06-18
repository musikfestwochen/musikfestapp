# Area Aggregation Algorithm Refactor Plan

## Summary

Refactor area aggregation so interval-heavy work happens in grouped database queries, while PHP keeps window planning, resets, and cumulative count state. Goal is to reduce query count and wall time without increasing memory usage or changing documented aggregation semantics.

The main design constraint is controlled memory: aggregation may use more memory when it buys fewer database round-trips, but must stay below the configured worker memory limit of `1024MB`. Work is processed per area and per window chunk so memory growth remains predictable.

## In Scope

- Keep current correctness rules from `docs/peoplecount/aggregation-algorithm.md`.
- Keep checksum-based full area invalidation for config changes.
- Make interval counts unique by `(sensor_id, ts_from, ts_to)` and use latest-wins upsert on ingest.
- Aggregate per-window net deltas in the database.
- Apply resets and cumulative counts in PHP.
- Use database features supported by both MariaDB and SQLite.
- Add late-arrival watermark support.
- Benchmark query count, runtime, and memory after refactor.

## Out Of Scope

- Dirty-range tracking for config changes.
- Partitioning historical interval tables.
- Config-driven aggregation rules.
- Splitting one sensor interval across multiple aggregation windows.
- Large service modularization beyond seams needed for planner, net query, and writer.

## Behavior Notes

If aggregation granularity is `1 minute` and sensor data arrives as a `5 minute` interval, current documented behavior stays unchanged: the interval contributes to the window containing `ts_from` only. It is not distributed across five 1-minute windows.

Duplicate interval counts are removed from aggregation by design, not by query-time dedupe. Ingest writes one authoritative row per `(sensor_id, ts_from, ts_to)`.

## Architectural Overview

Aggregation is split into four responsibilities:

- **Planner:** builds aggregation windows for one area from event bounds, granularity, resets, existing aggregate rows, and late-arrival state. It decides which windows need recalculation, but does not calculate interval counts.
- **Net query:** calculates per-window net deltas in the database for one chunk of planned windows. It joins temporary chunk windows with assignments and interval counts, filters by `ts_from`, assignment activity, and window bounds, then groups by window.
- **Cumulative applier:** walks planned windows in PHP order. It applies reset values, adds each window's net delta, and produces final cumulative area counts.
- **Writer:** persists calculated rows in batches using the unique `(area_id, period_start, period_end)` key.

The expensive operation is reducing many interval rows to one net delta per window. That belongs in the database because the database can filter, join, and group without hydrating interval rows into PHP objects.

The stateful operation is cumulative count calculation. That stays in PHP because reset precedence and running totals are easier to reason about in a chronological loop.

Processing shape is:

1. load one area with assignments, resets, event, and relevant aggregate metadata
2. invalidate existing aggregate rows if checksum or window-size rules require it
3. plan recalculation windows
4. process windows in chunks
5. for each chunk, insert chunk windows into a temporary table
6. run one grouped net query for the chunk
7. apply cumulative state in PHP
8. bulk upsert aggregate rows
9. update area `data_watermark` after successful aggregation

This keeps memory controlled by chunk size. Interval rows are not loaded as models. The largest in-memory structures are planned windows, assignment/reset metadata, grouped net deltas, and one write batch.

The temp-window-table approach is intentionally boring. It avoids vendor-specific time bucketing functions and works on both MariaDB and SQLite.

# Implementation Plan

## Migration: add indexes, `data_watermark`, unique aggregate key, cleanup + unique interval key.

Intent: prepare schema for fast range reads, idempotent bulk writes, late-arrival detection, and duplicate prevention. Existing duplicate interval rows must be cleaned before adding the unique interval key, keeping highest `received_at` and then highest `id` as tie-breaker.

## Ingest: switch interval writes to `upsert`.

Intent: make latest-wins duplicate policy permanent at write time. Aggregation should read normal interval rows without query-time dedupe.

## Planner: generate windows chunkably, preserve reset behavior.

Intent: isolate window/reset planning while keeping current rules. Windows should be generated per area and processed in bounded chunks so 1-minute granularity over long events stays within the `1024MB` worker memory limit.

## Net query: temp window table + grouped DB aggregate per chunk.

Intent: replace per-window/per-assignment interval queries with one grouped query per window chunk. Database computes net deltas by joining chunk windows, assignments, and interval counts, applying assignment bounds and direction flips.

## Cumulative applier: PHP applies resets and running counts.

Intent: keep stateful logic in PHP because reset priority and cumulative counts are sequential. Input is planned windows plus DB net deltas; output is aggregate rows ready for writing.

## Writer: chunked `upsert` aggregated rows.

Intent: replace per-window `updateOrCreate()` with batched writes keyed by `(area_id, period_start, period_end)`. Delete affected rows first when window boundaries can change, then upsert calculated rows.

## Watermark: late-arrival support only, no dirty ranges.

Intent: store latest incorporated `received_at` per area and extend recalculation start when late interval data affects already-aggregated history. Config changes still use full checksum invalidation.

## Benchmark: prove query count no longer scales with interval rows.

Intent: rerun aggregation benchmark and compare runtime, query count, query time, and memory against current commit summary. Success means query count scales mostly with areas, chunks, and write batches, not interval row count.
