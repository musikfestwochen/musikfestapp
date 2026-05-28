# People Count Documentation

People Count is an event-operations module in Musikfestapp.

It maps sensors to areas inside events and tracks area occupancy over time.

## What People Count Is

At high level, module connects four things:

1. **Events**
   Time-bounded contexts (for example one festival day or one venue program block).

2. **Areas**
   Logical spaces inside event (for example gate, zone, tent, stage perimeter).

3. **Sensors**
   Counting devices that produce in/out intervals over time.

4. **Assignments**
   Rules that define which sensor counts for which area and in which time range.

People Count then converts those raw interval streams into usable area occupancy history.

## What It Covers

- events with a defined time range
- areas inside those events
- sensor-to-area assignments with active windows
- direction mapping (normal or flipped)
- resets and occupancy history

## How Aggregation Fits In

Aggregation is core calculation engine inside People Count.

It decides, for each time window, what should cumulative area count be after applying:

- event boundaries
- assignment validity
- direction mapping
- interval inclusion rules
- reset priority

Read next:

- [Aggregation Overview](overview.md) for concept-level explanation
- [Aggregation Algorithm](aggregation-algorithm.md) for exact technical rules

## Audience

- operations teams
- product teams
- engineers
