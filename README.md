<div align="center">

# OpenMES

### Açık Kaynak Üretim Yürütme Sistemi (MES)

*Küçük üreticiler için güçlü, esnek ve tablet uyumlu MES*

[![Lisans: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![Laravel](https://img.shields.io/badge/Laravel-12-FF2D20?logo=laravel&logoColor=white)](https://laravel.com)
[![Livewire](https://img.shields.io/badge/Livewire-4-4E56A6?logo=livewire&logoColor=white)](https://livewire.laravel.com)
[![PostgreSQL](https://img.shields.io/badge/PostgreSQL-14+-336791?logo=postgresql&logoColor=white)](https://www.postgresql.org)

</div>

---

## OpenMES Nedir?

**OpenMES**, kurumsal karmaşıklığa ihtiyaç duymadan güçlü üretim takibine ihtiyaç duyan **küçük üreticiler** (ahşap işleme, metal işleme, montaj atölyeleri) için özel olarak tasarlanmış modern, açık kaynaklı bir Üretim Yürütme Sistemidir (Manufacturing Execution System).

### Neden OpenMES?

- 🎯 **Küçük Üreticiler İçin Tasarlandı** - Gereksiz özellikler yok, sadece ihtiyacınız olanlar var.
- 📱 **Önce Tablet Tasarımı** - Atölye operatörleri için dokunmatik optimizasyon.
- 🔒 **Güvenlik Odaklı** - İlk günden itibaren OWASP Top 10 uyumlu.
- 📊 **Gerçek Zamanlı Görünürlük** - Her hatta neler olduğunu anında görün.
- 🆓 **Tamamen Açık Kaynak** - MIT lisanslı, hiçbir firmaya bağımlılık yok.
- 🚀 **Dakikalar İçinde Kurulum** - Tek bir Docker komutuyla yayına alın.

---

## Özellikler

### Üretim Yönetimi

- **Çoklu Hat Üretimi** - Aynı anda birden fazla üretim hattını yönetin.
- **İş Emri Takibi** - Tam iş emri yaşam döngüsü yönetimi.
- **Parti Üretimi** - Birden fazla parti ile kısmi tamamlama desteği.
- **Süreç Şablonları** - Yeniden kullanılabilir, adım adım süreç tanımları.
- **CSV İçe Aktarma** - Esnek sütun eşleme ile toplu iş emri içe aktarma.
- **Gerçek Zamanlı Durum** - Canlı üretim durumu güncellemeleri.

### Operatör Deneyimi

- **Adım Adım Rehberlik** - Her operasyon için net talimatlar.
- **Sıralı İş Akışı** - Hataları önlemek için süreç sırasını zorunlu kılın.
- **Tek Dokunuşla İşlemler** - Başlat, tamamla, sorun bildir işlemlerini tek dokunuşla yapın.
- **PWA Desteği** - Tabletlerinize kurun, çevrimdışı çalışın.
- **Çevrimdışı Mod** - Ağ olmadığında işlemleri kuyruğa alın.
- **Tablet Optimizasyonu** - Büyük dokunma hedefleri (48px+), minimum metin girişi.

### Sorun & Andon Sistemi

- **Sorun Raporlama** - Operatörler her adımdan anında sorun bildirebilir.
- **Otomatik Engelleme** - Kritik sorunlar üretimi otomatik olarak durdurur.
- **Sorun Eskalasyonu** - Sorunları bildirimlerle amirlere iletin.
- **Çözüm Takibi** - Tam sorun yaşam döngüsü (Açık → Onaylandı → Çözüldü → Kapalı).
- **Ön Tanımlı Kategoriler** - Malzeme eksikliği, kalite sorunları, araç arızaları vb.

### Analiz & Raporlama

- **Amir Paneli** - Gerçek zamanlı KPI'lar ve üretim metrikleri.
- **Etkileşimli Grafikler** - Çıktı, döngü süresi, sorun eğilimleri, adım performansı.
- **Üretim Raporları** - Özet, parti tamamlama, duruş süresi raporları.
- **CSV Dışa Aktarma** - Daha fazla analiz için tüm raporları dışa aktarın.
- **İzlenebilirlik** - Her eylem için tam denetim izi.

### Güvenlik & Uyum

- **Değiştirilemez Denetim Günlükleri** - PostgreSQL tarafından zorunlu kılınır, değiştirilemez.
- **Tam İzlenebilirlik** - Her eylemi, kullanıcıyı ve zaman damgasını takip edin.
- **Rol Tabanlı Erişim** - Admin, Amir, Operatör rolleri.
- **Hat Bazlı Filtreleme** - Operatörler sadece atandıkları hatları görür.
- **Uyumluluk Hazır** - ISO 9001, AS9100 uyumlu denetim izi.

---

## Genişletilebilirlik ve Modüller

OpenMES genişletilmek üzere tasarlanmıştır! Çekirdek kodu değiştirmeden özel işlevler eklemek için kapsamlı **kanca (hook) sistemimizi** kullanın.

### Kanca (Hook) Sistemi

- Üretim yaşam döngüsünün tamamını kapsayan **40'tan fazla etkinlik**.
- **İş Emri kancaları** - Oluşturuldu, Güncellendi, Tamamlandı, Engellendi.
- **Parti kancaları** - Oluşturuldu, Tamamlandı, İptal Edildi.
- **Adım kancaları** - Başlatıldı, Tamamlandı, Sorun Bildirildi.
- **Kullanıcı kancaları** - Hatta Atandı, Oluşturuldu, Güncellendi.
- **Hat kancaları** - Oluşturuldu, Etkinleştirildi, Devre Dışı Bırakıldı.
- **Süreç Şablonu kancaları** - Şablon ve Adım yönetimi.
- **CSV İçe Aktarma kancaları** - Başlatıldı, Tamamlandı, Başarısız Oldu.

📚 **Tam Dokümantasyon**: [HOOKS.md](HOOKS.md)
📁 **Modül Örnekleri**: [modules/](modules/)

---

## Mimari

OpenMES **çok basit** bir Laravel monolit mimarisi kullanır:

```
┌─────────────────┐
│  Laravel Uyg.   │  :80 (her şeye hizmet eder)
│  (Blade + API)  │
└────────┬────────┘
         │
    ┌────▼─────┐
    │ PostgreSQL│
    └──────────┘
```

**Teknoloji Yığını:**
- **Arka Uç (Backend)**: Blade şablonlarıyla Laravel 12
- **Ön Uç (Frontend)**: Tailwind CSS 4 + etkileşim için Alpine.js
- **Gerçek Zamanlı**: Dinamik bileşenler için Livewire 4
- **Grafikler**: Analizler için Chart.js
- **Veritabanı**: Değiştirilemez denetim günlükleriyle PostgreSQL 14+
- **Kurulum**: Docker Compose (Sadece 2 konteyner!)

---

## 🚀 Kurulum

### Gereksinimler

- Docker ve Docker Compose (20.10+)
- Git

### Kurulum 🎯

**Klonlayın, tarayıcıyı açın, yapılandırın!** Hiçbir CLI komutu gerekmez!

```bash
# 1. Depoyu klonlayın
git clone https://github.com/Mes-Open/OpenMes.git
cd OpenMes

# 2. Docker konteynerlerini başlatın
docker-compose up -d
```

**İşte bu kadar!** Şimdi tarayıcınızda **http://localhost** adresini açın.

---

## 📄 Lisans

OpenMES, **MIT Lisansı** ile lisanslanmış açık kaynaklı bir yazılımdır.

Bu, şunları yapabileceğiniz anlamına gelir:
- ✅ Ticari olarak kullanın
- ✅ Değiştirin
- ✅ Dağıtın
- ✅ Özel olarak kullanın

Tam ayrıntılar için [LICENSE](LICENSE) dosyasına bakın.

---

<div align="center">

**Üretim topluluğu için ❤️ ile inşa edildi**

Üreticiler tarafından, üreticiler için yapıldı

⭐ OpenMES'i yararlı bulursanız lütfen bir yıldız verin!

</div>
