# OpenMES Kanca (Hook) ve Etkinlik Sistemi

OpenMES, çekirdek kodu değiştirmeden işlevleri genişletmenize olanak tanıyan kapsamlı bir kanca sistemi sunar. Bu, özel modüller ve entegrasyonlar oluşturmak için mükemmeldir.

## 📋 İçindekiler

- [Kancalar Nasıl Çalışır?](#kancalar-nasıl-çalışır)
- [Modül Oluşturma](#modül-oluşturma)
- [Mevcut Kancalar](#mevcut-kancalar)
  - [İş Emri Kancaları](#iş-emri-kancaları)
  - [Parti Kancaları](#parti-kancaları)
  - [Parti Adım Kancaları](#parti-adım-kancaları)
  - [Kullanıcı Kancaları](#kullanıcı-kancaları)
  - [Hat Kancaları](#hat-kancaları)
  - [Süreç Şablonu Kancaları](#süreç-şablonu-kancaları)
  - [CSV İçe Aktarma Kancaları](#csv-içe-aktarma-kancaları)
- [En İyi Uygulamalar](#en-iyi-uygulamalar)

---

## Kancalar Nasıl Çalışır?

OpenMES, Laravel'in etkinlik (event) sistemini kullanır. Her kanca, bir Etkinliktir ve Etkinlik Dinleyicileri (Event Listeners) kullanarak bu etkinlikleri dinleyebilirsiniz.

### Temel Kullanım

1. Modülünüzde bir etkinlik dinleyicisi oluşturun
2. Modülünüzün servis sağlayıcısına (service provider) kaydedin
3. Etkinlik gerçekleştiğinde dinleyici otomatik olarak çağrılır

### Örnek

```php
// modules/MyModule/Listeners/NotifyOnWorkOrderComplete.php
namespace Modules\MyModule\Listeners;

use App\Events\WorkOrder\WorkOrderCompleted;

class NotifyOnWorkOrderComplete
{
    public function handle(WorkOrderCompleted $event): void
    {
        $workOrder = $event->workOrder;

        // Özel mantığınız burada
        // örn. bildirim gönderin, harici sistemi güncelleyin vb.
    }
}
```

---

## Mevcut Kancalar

### İş Emri Kancaları

#### `WorkOrderCreated`
**Ne zaman tetiklenir:** Yeni bir iş emri oluşturulduğunda
**Konum:** `App\Events\WorkOrder\WorkOrderCreated`
**Mevcut veriler:**
- `$event->workOrder` - Oluşturulan WorkOrder modeli

**Kullanım örnekleri:**
- Üretim ekibine bildirim gönderin
- Harici ERP sistemini güncelleyin
- İş emri için QR kodları oluşturun
- Otomatik iş akışlarını tetikleyin

---

#### `WorkOrderUpdated`
**Ne zaman tetiklenir:** Bir iş emri güncellendiğinde
**Konum:** `App\Events\WorkOrder\WorkOrderUpdated`
**Mevcut veriler:**
- `$event->workOrder` - Güncellenen WorkOrder modeli
- `$event->changes` - Değişen öznitelikler dizisi

---

#### `WorkOrderCompleted`
**Ne zaman tetiklenir:** Bir iş emri tamamlandı olarak işaretlendiğinde
**Konum:** `App\Events\WorkOrder\WorkOrderCompleted`
**Mevcut veriler:**
- `$event->workOrder` - Tamamlanan WorkOrder modeli

---

### Parti Kancaları

#### `BatchCreated`
**Ne zaman tetiklenir:** Yeni bir parti oluşturulduğunda
**Konum:** `App\Events\Batch\BatchCreated`
**Mevcut veriler:**
- `$event->batch` - Oluşturulan Batch modeli

---

#### `BatchCompleted`
**Ne zaman tetiklenir:** Bir parti tamamlandığında
**Konum:** `App\Events\Batch\BatchCompleted`
**Mevcut veriler:**
- `$event->batch` - Tamamlanan Batch modeli

---

## En İyi Uygulamalar

### 1. Dinleyicileri Odaklı Tutun
Her dinleyici bir işi iyi yapmalıdır. Birden fazla eylem gerçekleştirmeniz gerekiyorsa, birden fazla dinleyici oluşturun.

✅ **İyi:**
```php
Event::listen(WorkOrderCompleted::class, SendCompletionEmail::class);
Event::listen(WorkOrderCompleted::class, UpdateInventory::class);
```

### 2. Ağır İşlemler İçin Kuyruğa Alınmış Dinleyicileri Kullanın
Dinleyiciniz zaman alan işlemler gerçekleştiriyorsa, `ShouldQueue` arayüzünü uygulayın.

### 3. Hataları Zarafetle Karşılayın
Dinleyici mantığınızı her zaman try-catch bloklarına alın.

---

## Destek

Kanca sistemiyle ilgili sorular veya sorunlar için:
- GitHub Issues: https://github.com/Mes-Open/OpenMes/issues
- Dokümantasyon: https://github.com/Mes-Open/OpenMes
