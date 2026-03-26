<div align="center">

# OpenMES

### Endüstriyel Sınıf Açık Kaynak Üretim Yürütme Sistemi

*Küçük ve orta ölçekli üreticiler için gelişmiş, esnek ve tablet uyumlu MES*

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![Laravel](https://img.shields.io/badge/Laravel-12-FF2D20?logo=laravel&logoColor=white)](https://laravel.com)
[![Livewire](https://img.shields.io/badge/Livewire-4-4E56A6?logo=livewire&logoColor=white)](https://livewire.laravel.com)
[![PostgreSQL](https://img.shields.io/badge/PostgreSQL-14+-336791?logo=postgresql&logoColor=white)](https://www.postgresql.org)

</div>

---

## 🇹🇷 OpenMES Nedir?

**OpenMES**, modern atölyeler ve fabrikalar için tasarlanmış, yüksek hassasiyetli bir Üretim Yürütme Sistemidir. Basit bir iş takibi uygulamasından endüstriyel bir platforma dönüştürülen OpenMES; olay kaynaklı mimarisi, gerçek zamanlı makine entegrasyonu ve gelişmiş analitik motoruyla gerçek dünya üretim zorluklarını çözer.

### Neden OpenMES?

- 🏭 **Endüstriyel Güç** - Makine durumu takibi, araç ömür yönetimi ve yüksek hassasiyetli OEE.
- 📱 **Önce Tablet Tasarımı** - Atölye operatörleri için dokunmatik optimizasyon.
- 🔒 **Güvenlik & Uyum** - Granüler RBAC, denetim izleri ve güvenlik kapıları.
- 📊 **Dijital İkiz & Simülasyon** - Üretim akışını sanallaştırın ve darboğazları tahmin edin.
- 🚀 **Hızlı Kurulum** - Docker ile dakikalar içinde yayına alın.

---

## Özellikler

### Endüstriyel Entegrasyon
- **Çoklu Protokol Desteği**: MQTT ve Modbus TCP üzerinden doğrudan iletişim.
- **Makine Durum Takibi**: Gerçek zamanlı izleme (ÇALIŞIYOR, ARIZA, KURULUM, BOŞTA).
- **Olay Kaynaklı Çekirdek**: Tam veri bütünlüğü için mikrosaniye hassasiyeti.
- **Uç Bilişim**: Güvenilir olmayan ağlar için yerel arabelleğe alma ve bulut senkronizasyonu.

### Analiz ve İzlenebilirlik
- **Gerçek Zamanlı OEE**: Otomatik Kullanılabilirlik, Performans ve Kalite hesaplaması.
- **Arıza Zekası**: Bakım planlaması için MTBF ve MTTR analitiği.
- **İzlenebilirlik Grafiği**: Lottan seri numarasına tam "Doğum Sertifikası" takibi.
- **Kısıt Tabanlı Çizelgeleme**: Makine, araç ve beceriye dayalı akıllı planlama.

---

## Mimari

```
┌────────────────────────────────┐
│   Endüstriyel Kullanıcı Paneli │  (Gerçek Zamanlı Panel / Livewire)
└───────────────┬────────────────┘
                ▼
┌────────────────────────────────┐
│     OpenMES Çekirdek Motoru    │  (Olay Deposu / OEE Motoru / İzlenebilirlik)
└───────────────┬────────────────┘
                ▼
┌───────────────┴────────────────┐
│ İletişim ve Adaptör Katmanı    │  (MQTT / Modbus / OPC-UA Soyutlaması)
└───────────────┬────────────────┘
                ▼
┌───────────────┴────────────────┐
│     Fiziksel Fabrika Katı      │  (CNC / PLC / Montaj İstasyonları)
└────────────────────────────────┘
```

---

## 🚀 Kurulum

```bash
# 1. Depoyu klonlayın
git clone https://github.com/Mes-Open/OpenMes.git
cd OpenMes

# 2. Konteynerleri başlatın
docker-compose up -d
```

---

## 📄 Lisans

OpenMES, **MIT Lisansı** altında lisanslanmış açık kaynaklı bir yazılımdır.

---

<div align="center">

**Geleceğin fabrikaları için ❤️ ile inşa edildi**

Üreticiler tarafından, üreticiler için yapıldı

⭐ OpenMES'i yararlı bulursanız lütfen bir yıldız verin!

</div>
