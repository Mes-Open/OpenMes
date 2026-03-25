# OpenMES Hook & Event System / Kanca ve Etkinlik Sistemi

OpenMES provides a comprehensive hook system to extend functionality without modifying core code. This supports a modular monolith architecture.
OpenMES, çekirdek kodu değiştirmeden işlevleri genişletmenize olanak tanıyan kapsamlı bir kanca sistemi sunar.

## 📋 Table of Contents / İçindekiler

- [How Hooks Work / Kancalar Nasıl Çalışır?](#how-hooks-work)
- [Industrial Events / Endüstriyel Etkinlikler](#industrial-events)
- [Best Practices / En İyi Uygulamalar](#best-practices)

---

## How Hooks Work / Kancalar Nasıl Çalışır?

OpenMES uses Laravel's event system. Each hook is an Event that you can listen to using Event Listeners.
OpenMES, Laravel'in etkinlik sistemini kullanır. Her kanca, bir Etkinliktir.

---

## Industrial Events / Endüstriyel Etkinlikler

### Work Order / İş Emri
- `WorkOrderCreated`: New order created / Yeni sipariş oluşturuldu.
- `WorkOrderCompleted`: Order qty finished / Sipariş tamamlandı.
- `WorkOrderBlocked`: Production halted / Üretim durduruldu.

### Machine & Step / Makine ve Adım
- `StepStarted` / `StepCompleted`: Fired during operation / Operasyon sırasında tetiklenir.
- `MachineEventRecorded`: Telemetry heartbeat / Telemetri kalp atışı.

---

## Best Practices / En İyi Uygulamalar

1. **Focused Listeners**: One job per listener (e.g., just send email).
   **Odaklı Dinleyiciler**: Her dinleyici bir işi iyi yapmalıdır.
2. **Use Queues**: Implement `ShouldQueue` for heavy tasks.
   **Kuyruk Kullanın**: Zaman alan işlemler için kuyruk kullanın.
3. **Precision**: Industrial events use microsecond timestamps (`timestamp(6)`).
   **Hassasiyet**: Endüstriyel olaylar mikro saniye hassasiyetinde kaydedilir.
