# Abschlussbericht: SEO-, Google-Shopping- & KI-Suchmaschinen-Architektur (GEO)

**Status:** Produktiv / Vollständig implementiert  
**Projekt:** Mein-Seelenfunke (`/home/ubuntuxina/meine-projekte/seelenfunke`)  
**Abteilung:** Marketing / SEO & Generative Engine Optimization (GEO)  
**Letzte Aktualisierung:** 26.10.2026  

---

## 1. Maschinenlesbare Endpunkte & Feeds ([`SeoController.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Http/Controllers/SeoController.php))

| Endpunkt | Typ | Zweck & Suchmaschinen-Zielgruppe |
| :--- | :--- | :--- |
| **`/sitemap.xml`** | XML (`Sitemap + Image + Video`) | Dynamische Sitemap inkl. aller Produktbilder (auch `*-dimensions.jpg` Maßblätter), Produktvideos (`<video:video>`), Blog-Beiträge, Landingpages und 8 saisonalen Anlass-Filterseiten (`/shop?filterHoliday=...`). |
| **`/feed/google-shopping.xml`** | XML (`RSS 2.0 + xmlns:g`) | Vollständiger **Google Merchant Center Feed** für kostenlose Google Shopping Einträge & Shopping Ads inkl. `<g:id>`, `<g:price>`, `<g:sale_price>`, `<g:additional_image_link>` (inkl. Maßblatt), `<g:product_width>`, `<g:product_height>`, `<g:product_length>`, `<g:shipping_weight>` und `<g:identifier_exists>false</g:identifier_exists>`. |
| **`/feed/products.json`** | JSON | Strukturierter Echtzeit-Produktkatalog für KI-Shopping-Assistenten & Preisvergleichsportale (exakte Millimeter-Maße, Gewicht, Staffelpreise, Bewertungen, Maßblatt-URL & Video-URL). |
| **`/llms.txt`** | Markdown / Plaintext | Kompakte KI-Suchmaschinen-Zusammenfassung nach dem `llms.txt`-Standard für ChatGPT (`OAI-SearchBot`), Perplexity, Claude und Google Gemini. |
| **`/llms-full.txt`** | Markdown / Plaintext | Vollständige Wissensdatenbank mit allen Produkten, Varianten, Staffelrabatten, Lieferzeiten, Ratgeber-Artikeln und FAQs. |

Alle Endpunkte sind im `<head>` von [`frontend_layout.blade.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/resources/views/components/layouts/frontend_layout.blade.php) als `<link rel="alternate">` hinterlegt und in [`public/robots.txt`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/public/robots.txt) explizit für alle Crawler freigegeben.

---

## 2. Schema.org Structured Data (`JSON-LD`) auf allen Seiten

1. **Global (`frontend_layout.blade.php`):**
   - Vernetzter `@graph` aus `Organization` / `OnlineStore` / `LocalBusiness` (Standort Gifhorn, Geokoordinaten, Gründerin Alina Steinhauer) und `WebSite` inkl. `SearchAction` (`Sitelinks Searchbox`).
2. **Produktdetailseiten & Landingpages ([`Product::buildStructuredDataSchema()`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Models/Product/Product.php)):**
   - `BreadcrumbList` + `Product` inkl. `sku`, `mpn`, `width` / `height` / `depth` (`MMT`), `weight` (`GRM`), `Offer` / `AggregateOffer`, `OfferShippingDetails`, `MerchantReturnPolicy`, `AggregateRating`, `Review`.
   - **`VideoObject` (`subjectOf`):** Automatische Einbindung des stummen Produktvideos (`{slug}-promo.mp4`) für Google Video Rich Snippets.
   - **Produktspezifische `FAQPage`:** Automatisch generierte Fragen & Antworten zu den exakten Millimeter-Maßen (inkl. Hinweis auf das Maßblatt auf der letzten Galerieposition), der im Preis enthaltenen 2D/3D-Lasergravur und der Lieferzeit (1–3 Werktage ab Gifhorn).
3. **Shop & Anlass-Kollektionen ([`ProductFrontendFilterArea.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Product/ProductFrontendFilterArea.php)):**
   - Dynamische SEO-Titles, Meta-Descriptions, Canonicals und `CollectionPage` + `ItemList` JSON-LD für alle Feiertags-/Anlass-Filter (`Weihnachten`, `Ostern`, `Hochzeit`, `Geburtstag`, `Valentinstag`, `Muttertag`, `Vatertag`, `Halloween`), Kategorien und Sale-Aktionen.
4. **Magazin & Ratgeber ([`MarketingBlogIndex.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Marketing/MarketingBlogIndex.php), [`MarketingBlogShow.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Marketing/MarketingBlogShow.php)):**
   - `Blog` + `BlogPosting` + `BreadcrumbList` JSON-LD inkl. `wordCount`, `timeRequired` (Lesezeit) und interner Produktverlinkung.
5. **Statische Fachseiten:**
   - [`manufacture.blade.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/resources/views/frontend/pages/manufacture.blade.php): `AboutPage` + `Person` (Alina Steinhauer).
   - [`calculator.blade.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/resources/views/frontend/pages/calculator.blade.php): `WebApplication` (`BusinessApplication`) + `BreadcrumbList`.
   - [`contact.blade.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/resources/views/frontend/pages/contact.blade.php): `ContactPage` + `BreadcrumbList`.
   - [`versand.blade.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/resources/views/frontend/pages/versand.blade.php): `WebPage` + `BreadcrumbList`.
   - [`faq_section.blade.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/resources/views/frontend/pages/partials/faq_section.blade.php): `FAQPage` inkl. Fragen zu K9-Kristallglas, Fotogravur, Edelstahl-Hundemarken/Schlüsselanhängern, Versandkosten und Express-Fertigung.
