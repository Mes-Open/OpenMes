# Finite-capacity scheduling

OpenMES can calculate a feasible production schedule from the data it already
holds. It does not replace the planner board. Drag & drop keeps working, and
nothing changes on the shop floor until a Supervisor or Admin applies a
generated plan.

Open it from **Schedule → Planner → Automatic scheduling** (`/admin/schedule/auto`).

The screens sit under the **Schedule** tab of the admin panel. An Admin has it;
a Supervisor gets it only once it is granted in **Settings → Access matrix**
(by default a Supervisor sees just the order screens). The same applies to the
planner board itself.

## Effective planning capacity

Each line has a **planning efficiency (%)**, editable on the line form. The
default is 100.

```text
calendar capacity  = active shift time − scheduled maintenance
effective capacity = calendar capacity × planning efficiency
```

The capacity view shows both figures. Load and overload are measured against
effective capacity. Crews read the same factor from the workforce provider when
it exposes `planning_efficiency_pct`.

The capacity view has three axes: line, crew and **workstation**. The
workstation axis compares each station's effective capacity (its line's shifts
minus line and station maintenance, × the line's efficiency) with the operations
committed on it by applied automatic schedules. Work that was only ever placed
by hand has no operations, so it shows on the line axis and not here.

On the line and crew axes, an order the scheduler placed counts by its
committed operations: each step loads the line of the station that runs it, as
standard work (the working time it occupies × the line's efficiency). Its
overall time window is not counted, because after automatic scheduling it
also spans steps on other lines and the waits between them. An order placed
only by hand still counts by its time window on its own line.

Planning efficiency is deliberately **not OEE**. Calendar capacity already
removes the availability losses OpenMES knows about. Multiplying by a full OEE
figure would count those losses twice. A value derived from historical OEE may
inform the setting, but nothing changes it automatically.

## What gets scheduled

The scheduler works on operations, not whole work orders. Each remaining step of
each active order's **frozen process snapshot** becomes one operation. The same
snapshot is used to create batch steps, so there is no second routing model.

When batches already exist:

- finished steps are left out;
- a running step is pinned from the start of the horizon for the rest of its estimate;
- a workstation already assigned to a batch step is treated as fixed.

Duration comes from the step's standard times when present:
`setup + run per unit × planned quantity`. Otherwise the step's estimated
duration is used. A step with neither is reported as having no estimate. The
scheduler never guesses a duration.

## How a slot is chosen

The heuristic is earliest-feasible-slot. It is deterministic: the same data
always gives the same plan.

1. Orders are taken by priority (highest first), then priority score, then the
   earliest due date, then id.
2. If stock cannot cover an order (the same shortage check the planner shows),
   none of its operations are placed. Stock is netted across the run in
   scheduling order: an order that is covered takes its quantities before the
   next one is checked, so two orders cannot both count on the same stock. An
   order that is short takes nothing. No material-ready date is invented, so
   the order stays blocked by material.
3. Steps follow routing order. Each step starts no earlier than the previous
   step ends. If a step cannot be placed, every later step of that order is
   reported as `infeasible_predecessor`. Richer dependency graphs (parallel
   operations, lags) belong to #15.
4. Eligible resources for a step are tried in this order:
   - the workstation fixed by the routing or the batch step;
   - otherwise the active workstations of its equipment class (`workstation_type_id`);
   - otherwise the order's line as a whole.

   The operation lands on whichever resource finishes it first. Ties go to the
   order's own line, then to the lower workstation id. A workstation of another
   class is never used.
5. On a resource, a slot must meet all of these conditions:
   - it fits the shift calendar, minus line or station maintenance, stretched by
     the line's planning efficiency;
   - it does not overlap committed work;
   - it leaves enough operators, and enough qualified operators when the step
     requires skills. This only applies where the workforce provider tracks people.

   An operation may pause across non-working time, but it never interleaves
   with another job.

Committed work means one of the following:

- operations committed by an earlier apply;
- the minute plan of an order that was never scheduled operation by operation.

Scope `unplanned` (the default) places only orders without a time plan. Scope
`all` replans every active order that has not started.

## Workforce and skills

People are counted through `App\Extension\Contracts\WorkforceCapacityProvider`.

```php
public function availableOperators(?int $lineId, CarbonInterface $start, CarbonInterface $end): ?int;
public function qualifiedOperators(?int $lineId, CarbonInterface $start, CarbonInterface $end, array $skillIds): ?int;
```

A return value of `null` means "not tracked" and never constrains the schedule.
This contract is separate from `WorkforceProvider` so that existing
implementations of that interface keep loading.

Core binds `AssignedStaffCapacityProvider`, which counts from data OpenMES
already holds:

- the operator accounts assigned to the line (Admin → Lines → Operators), the
  active, non-logistics workers assigned to its workstations, and the workers of
  any crew the workforce module assigns to it — each person once, an account
  linked to a worker counting as that worker;
- less the workers `WorkforceProvider::absentWorkerIds()` reports away on any day
  of the window;
- for qualification, those holding every required skill, when the workforce
  module adds `Worker::skills()`.

A line nobody is assigned to answers `null`, so an installation that never
assigned workers schedules on machine capacity alone, exactly as before.
Without `Worker::skills()` qualification is not tracked. Crew break windows are
left to the shift calendar. A workforce module may bind its own provider
instead.

### One person on several lines

Someone assigned to two lines is one person: the scheduler never books them on
both at the same moment. When a capacity provider also implements
`App\Extension\Contracts\WorkforceRosterAware`, it names the people it counts:

```php
public function assignedOperatorKeys(): array;   // line id => person keys
public function presentOperatorKeys(?int $lineId, CarbonInterface $start, CarbonInterface $end): ?array;
public function qualifiedOperatorKeys(?int $lineId, CarbonInterface $start, CarbonInterface $end, array $skillIds): ?array;
```

A key names one person on every line (core uses `w:<worker id>`, or
`u:<user id>` for an account with no worker). Lines that share anyone form a
group. A window on one line of a group fits only if the operators booked across
the whole group at each moment, plus the new step's operators, can all be
given distinct people eligible for their line. This is a bipartite matching
check, so booked work is rerouted to its own people when that frees a shared
person. Another line that is already short (an absence after its work was
committed) keeps that shortage to itself and never takes this line's own
people.

When a step has to wait for people held on another line, its reason says so:
*"Waited for operators busy on Line A."*, with the window it was refused. An
unplaced step's people reason names those lines too: *"1 busy with other work on
Line A."*, or *"3 busy with other work, 2 of them on Line A."* when part of the
work is the line's own. The JSON carries `busy_lines` (`line_id`, `line_name`, `operators`).

Each line's presence is read for the window being tried. With line-specific
shift calendars, an absence near a shift-date boundary can be attributed to the
neighbouring day for the other lines in the group.

A skilled step runs the same check with only the qualified people allowed to
take it (`qualifiedOperatorKeys()`), so its only qualified person is not
counted as free while another line holds them.

A person also works one step at a time, whatever skills they hold. Where the
provider names its people and records skills, every step booked in the window
(on the line or anywhere in its group) is a set of places that only people
holding that step's skills may fill, and the new step must fit on top with
distinct people. So when the only person holding both *Welding* and *Painting*
is on a Welding step, a Painting step waits for them even on another free
machine. Bookings record counts, not names, so each stretch of constant booked
load is matched on its own: one person may cover different steps in different
stretches of a long window, but every stretch is staffable. Work committed
outside the run carries no skills and can be anyone's on its line.

A step that waited for a qualified person busy on its own line says so:
*"Waited: only 1 qualified operator for Painting (Jan Kowalski), busy with other
work."* (`waited_for_qualified_operators`, with `available`, `required_skills`,
`qualified_workers` and the window). When the qualified person is held on
another line it adds where: *"…, busy with other work on CNC Turning."*
(`busy_lines`). A skilled step that finds nobody free at all is reported by its
qualified people too when they are short, since that is what the planner can act
on. A step left unplaced for lack of qualified
people names the scarcest of its skills on the line and who holds it, absences
ignored: *"Painting is held only by Jan Kowalski."* (`bottleneck_skill`,
`skill_holders`). When several stations of one line would finish a step at
the same time, the one only people held back wins the tie, so the reason shown
is the one that bound; across lines the order's own line still wins. When only
some of the qualified people were busy the reason says so: *"Waited: 2 of 3
qualified operators for Welding (…) busy with other work."*

Operations already committed to the plan hold people with their step's skills,
read from the order's frozen routing, so generating again does not hand a
committed Painting step to someone who cannot paint. An order planned only as a
whole (a minute plan, no operations) holds anyone on its line, as before.

The `workforce` section lists, per line, `single_holder_skills`: the skills some
step of the run that may run on that line requires, held there by exactly one
person assigned to the line
(`skill_id`, `skill_name`, `holder_name`). The *Staffing* panel shows each as
*"Painting — single qualified operator: Jan Kowalski"*. Providers that explain
their counts (`ExplainsWorkforceCapacity`) supply `skillHolders()` and
`operatorNames()` for this.

A provider that only returns counts does not implement the interface, and
each line keeps counting its own people, exactly as before.

Untick **Count available operators** on the simulation form (API:
`respect_workforce: false`) to schedule a run on machines alone.

A step's required skills come from its process segment. They are frozen into
the work-order snapshot as `required_skill_ids`, so later edits to the segment
do not change who may run an order that has already been released. A step
without skills is never constrained by qualification.

## Changeover rules

**Schedule → Automatic scheduling → Changeover Rules** defines optional,
sequence-dependent setup times. A rule matches one attribute of the previous job
and the next job on the same resource. The attribute is either the product type
code or the product category. Either side may be left empty to mean "any". A rule
can be narrowed to one equipment class or one workstation.

When several rules match, the most specific one wins. Specificity is ranked
from highest to lowest:

1. a rule for this workstation;
2. a rule for this equipment class;
3. a named "from" value;
4. a named "to" value.

The winning rule's minutes replace the routing's standard setup for that
transition. With no rule, the standard `setup_time_minutes` applies unchanged.
The scheduler accounts for setup time, but it does not reorder jobs to
minimise it. An idle gap before a job that is already placed is used only if
the new job does not lengthen that job's changeover beyond the setup it
reserved.

## What-if workflow

1. **Simulate**: this stores a draft. Only one draft exists at a time, and a new
   one discards the previous. The draft does not change placements, timestamps,
   workstation assignments or batch steps.
2. **Review**: check placed, late and infeasible counts, resource load over the
   horizon, and every operation with its reasons.
3. **Apply** (Supervisor/Admin):
   - Each fully schedulable order gets its new `planned_start_at` /
     `planned_end_at`, and a line if it had none: the line where most of its
     scheduled time runs. Steps on another line's workstations are reported
     as one count. These are written through the
     planner's own update path, so the change log, undo, `WorkOrderScheduled`
     and the audit trail work as they do for a drag on the board.
   - `due_date` is never changed.
   - Stations picked from an equipment class are assigned to batch steps through
     pool dispatch.
   - Orders with infeasible steps keep their placement. Their reasons are still
     committed.
   - A draft is refused if any of its orders changed after it was generated,
     or anything else it was calculated from: work planned around it,
     shifts, maintenance, changeover rules, line efficiency or the active
     stations. The check compares content, not timestamps, so a change in the
     same second still counts.
4. **Discard**: removes the draft and leaves the committed schedule untouched.

A Supervisor/Admin can move a scheduled operation to another active workstation
of the same equipment class. The new station must be free of other operations
and offer the same working time in the operation's window (its line's shifts,
less line and station maintenance). Such overrides are audited.

Moving an order by hand on the planner drops its committed operations. The next
run then works around its new minute plan.

## Statuses and reasons

Each operation has a `status`:

- `scheduled`
- `scheduled_late`
- `infeasible_resource_capacity`
- `infeasible_material`
- `infeasible_workforce_capacity`
- `infeasible_qualification`
- `infeasible_no_eligible_workstation`
- `infeasible_predecessor`
- `infeasible_unestimated`

It also has a list of `reasons`. Each reason contains a machine-readable `code`
(`resource_capacity`, `material`, `late`, `changeover`, …), a translated
`message`, and details where they apply: `shortages`, `material_ready_at: null`,
`due_date` or `changeover_rule_id`.

An order is late when its last operation ends after the end of its `due_date`.

### Delayed by staffing

A step that is placed (`scheduled` or `scheduled_late`) but later than the
machines alone allowed gets a last reason with code `delayed_by_staffing`:
*"Delayed by staffing: 2026-10-12 06:00 → 2026-10-13 06:00 — Needs 3
operators, 2 available (1 absent: Jan Kowalski)."* It carries `machine_start`
(the earliest start the calendar, conflicts and changeovers allowed on the
resource the step landed on), `planned_start`, `delay_minutes`, the people
counts and `absent_workers` for that machine window, and the full people
reason as `cause`. The status does not change. A step pushed only by machines,
or a run that ignores people, gets no such reason.

## Staffing per shift

Every simulation's `summary.staffing` (and the `staffing` key of the
simulation API) holds:

- `shifts` — one row per active line and shift run in the horizon: `date`
  (the date the run started), `shift_name`, `tracked`, `assigned`, `present`,
  `absent` (`{id, name}`), `person_hours` and `peak_operators` booked in the
  run, `blocked_operations`, and `shortfall`. The shortfall is the larger of
  the booked peak minus the people present and, for each people reason whose
  window starts in the run, what the step needed on top of what was booked
  minus who was there. Untracked lines have `null` counts and shortfall. Rows
  with no demand, absence or shortfall are left out.
- `absences` — each absent person with their `dates`, `lines` and the
  `affected_orders` whose people reasons name them.

Counts are per line: a person assigned to two lines appears on both. Drafts
generated before this breakdown existed have `staffing: null`.

## API

All endpoints require the `Admin` or `Supervisor` role (Sanctum).

| Method | Path | Purpose |
|---|---|---|
| GET | `/api/v1/schedule/operations` | Committed operation plan. Filters: `work_order_id`, `workstation_id`, `line_id`, `from`, `to`, `status[]` |
| POST | `/api/v1/schedule/simulations` | Generate a draft (`start`, `horizon_days` ≤ 90, `scope`, `respect_material`, `respect_workforce`, `work_order_ids[]`) |
| GET | `/api/v1/schedule/simulations/{id}` | A simulation with summary and operations |
| POST | `/api/v1/schedule/simulations/{id}/apply` | Commit a draft (409 when stale or not a draft) |
| POST | `/api/v1/schedule/simulations/{id}/discard` | Discard a draft |
| PUT | `/api/v1/schedule/operations/{id}` | Override the workstation (`workstation_id`) |

The completion forecast (#241) and schedule suggestions (#39) should read
`GET /schedule/operations`, or call `FiniteScheduler::schedule()` directly.
They should not re-implement constraints. Schedule feasibility stays
deterministic and independent of any AI feature.
