# OpenMES Hook & Event System

OpenMES provides a comprehensive hook system to extend functionality without modifying core code. This supports a modular monolith architecture.

## 📋 Table of Contents

- [How Hooks Work](#how-hooks-work)
- [Industrial Events](#industrial-events)
- [Best Practices](#best-practices)

---

## How Hooks Work

OpenMES uses Laravel's event system. Each hook is an Event that you can listen to using Event Listeners.

---

## Industrial Events

### Work Order
- `WorkOrderCreated`: New order created.
- `WorkOrderCompleted`: Order qty finished.
- `WorkOrderBlocked`: Production halted.

### Machine & Step
- `StepStarted` / `StepCompleted`: Fired during operation.
- `MachineEventRecorded`: Telemetry heartbeat.

---

## Best Practices

1. **Focused Listeners**: One job per listener (e.g., just send email).
2. **Use Queues**: Implement `ShouldQueue` for heavy tasks.
3. **Precision**: Industrial events use microsecond timestamps (`timestamp(6)`).
