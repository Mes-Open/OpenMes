# OpenMES Kanca ve Etkinlik Sistemi

OpenMES, çekirdek kodu değiştirmeden işlevleri genişletmenize olanak tanıyan kapsamlı bir kanca sistemi sunar. Bu, modüler monolit mimarisini destekler.

## 📋 İçindekiler

- [Kancalar Nasıl Çalışır?](#kancalar-nasıl-çalışır)
- [Endüstriyel Etkinlikler](#endüstriyel-etkinlikler)
- [En İyi Uygulamalar](#en-iyi-uygulamalar)

---

## Kancalar Nasıl Çalışır?

OpenMES, Laravel'in etkinlik sistemini kullanır. Her kanca, Olay Dinleyicileri (Event Listeners) kullanarak dinleyebileceğiniz bir Etkinliktir (Event).

---

## Endüstriyel Etkinlikler

### İş Emri
- `WorkOrderCreated`: Yeni sipariş oluşturuldu.
- `WorkOrderCompleted`: Sipariş miktarı tamamlandı.
- `WorkOrderBlocked`: Üretim durduruldu.

### Makine ve Adım
- `StepStarted` / `StepCompleted`: Operasyon sırasında tetiklenir.
- `MachineEventRecorded`: Telemetri kalp atışı.

---

## En İyi Uygulamalar

1. **Odaklı Dinleyiciler**: Her dinleyici sadece bir işi yapmalıdır (örneğin; sadece e-posta gönderimi).
2. **Kuyruk Kullanın**: Zaman alan işlemler için `ShouldQueue` özelliğini uygulayın.
3. **Hassasiyet**: Endüstriyel olaylar mikro saniye hassasiyetinde (`timestamp(6)`) zaman damgası kullanır.
